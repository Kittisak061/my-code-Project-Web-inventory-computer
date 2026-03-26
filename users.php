<?php
require_once __DIR__ . '/config.php';

$db = getDB();
$m = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function findUserByIdentity($db, $username, $email, $ignoreId = 0) {
    $sql = 'SELECT `id` FROM `users` WHERE (`username`=? OR `email`=?)';
    $params = array($username, $email);
    if ($ignoreId > 0) {
        $sql .= ' AND `id`<>?';
        $params[] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

if ($m === 'GET') {
    requireAuth();
    $w = array();
    $p = array();

    if (!empty($_GET['role'])) { $w[] = '`role`=:r'; $p[':r'] = $_GET['role']; }
    if (!empty($_GET['status'])) { $w[] = '`status`=:s'; $p[':s'] = $_GET['status']; }
    if (!empty($_GET['q'])) {
        $q = '%' . $_GET['q'] . '%';
        $w[] = '(`name` LIKE :q OR `username` LIKE :q2 OR `email` LIKE :q3 OR `dept` LIKE :q4)';
        $p[':q'] = $q; $p[':q2'] = $q; $p[':q3'] = $q; $p[':q4'] = $q;
    }

    $sql = 'SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`last_login`,`created_at` FROM `users`'
         . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
         . ' ORDER BY `created_at` DESC';
    $st = $db->prepare($sql);
    $st->execute($p);
    ok($st->fetchAll(), 200);
}

if ($m === 'POST') {
    requireAdmin();
    $b = normalizePayloadFields(body(), array('name','username','email','dept','phone','status'));
    if (empty($b['username']) && !empty($b['email'])) {
        $emailParts = explode('@', (string)$b['email'], 2);
        $b['username'] = normalizeNullableText($emailParts[0]);
    }

    if (empty($b['name']) || empty($b['email']) || empty($b['username'])) {
        err('กรุณากรอก name, username และ email', 400);
    }
    if (empty($b['password']) || strlen($b['password']) < 4) {
        err('กรุณากรอก password อย่างน้อย 4 ตัว', 400);
    }

    $username = (string)$b['username'];
    $email = (string)$b['email'];
    if (findUserByIdentity($db, $username, $email)) {
        err('Username หรือ Email นี้มีอยู่แล้ว', 409);
    }

    $hash = password_hash($b['password'], PASSWORD_DEFAULT);
    $role = (isset($b['role']) && $b['role'] === 'admin') ? 'admin' : 'user';
    $status = gv($b, 'status', 'active');
    if ($status !== 'active' && $status !== 'inactive') $status = 'active';

    $st = $db->prepare('INSERT INTO `users`(`name`,`username`,`password`,`email`,`dept`,`role`,`phone`,`status`,`devices`) VALUES(:n,:u,:pw,:e,:d,:r,:p,:s,0)');
    $st->execute(array(
        ':n' => $b['name'],
        ':u' => $username,
        ':pw' => $hash,
        ':e' => $email,
        ':d' => gv($b, 'dept'),
        ':r' => $role,
        ':p' => gv($b, 'phone'),
        ':s' => $status
    ));

    $nid = $db->lastInsertId();
    $st = $db->prepare('SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`created_at` FROM `users` WHERE `id`=?');
    $st->execute(array($nid));
    ok($st->fetch(), 201);
}

if ($m === 'PUT') {
    $auth = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    $b = normalizePayloadFields(body(), array('name','username','email','dept','phone','status'));
    if (array_key_exists('username', $b) && empty($b['username']) && !empty($b['email'])) {
        $emailParts = explode('@', (string)$b['email'], 2);
        $b['username'] = normalizeNullableText($emailParts[0]);
    }

    $fields = array('name','username','email','dept','role','phone','status','devices');
    $sets = array();
    $p = array(':id' => $id);

    $newUsername = array_key_exists('username', $b) ? normalizeNullableText($b['username']) : null;
    $newEmail = array_key_exists('email', $b) ? normalizeNullableText($b['email']) : null;
    if (($newUsername !== null && $newUsername !== '') || ($newEmail !== null && $newEmail !== '')) {
        $current = $db->prepare('SELECT `username`,`email` FROM `users` WHERE `id`=?');
        $current->execute(array($id));
        $row = $current->fetch();
        if (!$row) err('ไม่พบ user', 404);
        $finalUsername = $newUsername !== null && $newUsername !== '' ? $newUsername : $row['username'];
        $finalEmail = $newEmail !== null && $newEmail !== '' ? $newEmail : $row['email'];
        if (findUserByIdentity($db, $finalUsername, $finalEmail, $id)) {
            err('Username หรือ Email นี้มีอยู่แล้ว', 409);
        }
    }

    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $sets[] = "`$f`=:$f";
            if ($f === 'role') {
                $p[":$f"] = ($b[$f] === 'admin') ? 'admin' : 'user';
            } elseif ($f === 'status') {
                $p[":$f"] = ($b[$f] === 'inactive') ? 'inactive' : 'active';
            } else {
                $p[":$f"] = $b[$f];
            }
        }
    }

    if (!empty($b['password']) && strlen($b['password']) >= 4) {
        $sets[] = '`password`=:pw';
        $p[':pw'] = password_hash($b['password'], PASSWORD_DEFAULT);
    }

    if ((int)$auth['uid'] === $id && isset($p[':role']) && $p[':role'] !== 'admin') {
        err('Cannot remove your own admin role', 400);
    }

    if (empty($sets)) err('ไม่มีข้อมูล', 400);

    $db->prepare('UPDATE `users` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
    $st = $db->prepare('SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`last_login`,`created_at` FROM `users` WHERE `id`=?');
    $st->execute(array($id));
    ok($st->fetch(), 200);
}

if ($m === 'DELETE') {
    $auth = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    if ((int)$auth['uid'] === $id) err('Cannot delete your own account', 400);

    $st = $db->prepare('DELETE FROM `users` WHERE `id`=?');
    $st->execute(array($id));
    if (!$st->rowCount()) err('ไม่พบ', 404);
    ok(array('success' => true), 200);
}

err('Method not allowed', 405);
