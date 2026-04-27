<?php
require_once __DIR__ . '/config.php';

// ============================================================
// Users API
// 1. Bootstrap
// 2. Lookup helpers
// 3. Main REST handlers
// ============================================================

$db = getDB();
$m = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- 1) Bootstrap / identity helpers ----------
function findUserByIdentity($db, $username, $email, $ignoreId = 0) {
    $username = normalizeNullableText($username);
    $email = normalizeNullableText($email);
    $where = array();
    $params = array();

    if ($username !== null) {
        $where[] = '`username`=?';
        $params[] = $username;
    }
    if ($email !== null) {
        $where[] = '`email`=?';
        $params[] = $email;
    }
    if (!$where) {
        return false;
    }

    $sql = 'SELECT `id` FROM `users` WHERE (' . implode(' OR ', $where) . ')';
    if ($ignoreId > 0) {
        $sql .= ' AND `id`<>?';
        $params[] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

function deriveUsernameFromEmail($email) {
    $email = normalizeNullableText($email);
    if ($email === null) return null;
    $parts = explode('@', (string)$email, 2);
    return normalizeNullableText($parts[0]);
}

// ---------- 2) Snapshot helpers for backup / audit ----------
function fetchUserSnapshot($db, $id) {
    $st = $db->prepare('SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`last_login`,`created_at` FROM `users` WHERE `id`=?');
    $st->execute(array($id));
    return $st->fetch();
}

// ---------- 3) Main REST handlers ----------
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
    $auth = requireAdmin();
    $b = normalizePayloadFields(body(), array('name','username','email','dept','phone','status'));
    if (empty($b['username'])) {
        $b['username'] = deriveUsernameFromEmail(gv($b, 'email'));
    }
    if (empty($b['username'])) {
        err('กรุณากรอก Username อย่างน้อย 1 ค่า', 400);
    }
    if (empty($b['password']) || strlen($b['password']) < 4) {
        err('กรุณากรอก password อย่างน้อย 4 ตัว', 400);
    }

    $username = (string)$b['username'];
    $email = gv($b, 'email');
    $displayName = gv($b, 'name', '');
    if (findUserByIdentity($db, $username, $email)) {
        err('Username หรือ Email นี้มีอยู่แล้ว', 409);
    }

    $hash = password_hash($b['password'], PASSWORD_DEFAULT);
    $role = (isset($b['role']) && $b['role'] === 'admin') ? 'admin' : 'user';
    $status = gv($b, 'status', 'active');
    if ($status !== 'active' && $status !== 'inactive') $status = 'active';

    try {
        $db->beginTransaction();
        $st = $db->prepare('INSERT INTO `users`(`name`,`username`,`password`,`email`,`dept`,`role`,`phone`,`status`,`devices`) VALUES(:n,:u,:pw,:e,:d,:r,:p,:s,0)');
        $st->execute(array(
            ':n' => $displayName,
            ':u' => $username,
            ':pw' => $hash,
            ':e' => $email,
            ':d' => gv($b, 'dept'),
            ':r' => $role,
            ':p' => gv($b, 'phone'),
            ':s' => $status
        ));

        $nid = $db->lastInsertId();
        $created = fetchUserSnapshot($db, $nid);
        writeBackupEntry($db, 'users', $nid, 'create', null, $created, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok($created, 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถเพิ่มผู้ใช้งานได้', 500);
    }
}

if ($m === 'PUT') {
    $auth = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    $before = fetchUserSnapshot($db, $id);
    if (!$before) err('ไม่พบ user', 404);
    $b = normalizePayloadFields(body(), array('name','username','email','dept','phone','status'));
    if (array_key_exists('username', $b) && empty($b['username']) && !empty($b['email'])) {
        $b['username'] = deriveUsernameFromEmail($b['email']);
    }

    $fields = array('name','username','email','dept','role','phone','status','devices');
    $sets = array();
    $p = array(':id' => $id);

    $newUsername = array_key_exists('username', $b) ? normalizeNullableText($b['username']) : null;
    $newEmail = array_key_exists('email', $b) ? normalizeNullableText($b['email']) : null;
    $finalUsername = ($newUsername !== null && $newUsername !== '') ? $newUsername : $before['username'];
    if ($finalUsername === null || $finalUsername === '') {
        err('กรุณากรอก Username อย่างน้อย 1 ค่า', 400);
    }
    $finalEmail = array_key_exists('email', $b) ? $newEmail : normalizeNullableText($before['email']);
    if (findUserByIdentity($db, $finalUsername, $finalEmail, $id)) {
        err('Username หรือ Email นี้มีอยู่แล้ว', 409);
    }

    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            if ($f === 'username' && ($b[$f] === null || $b[$f] === '')) {
                continue;
            }
            $sets[] = "`$f`=:$f";
            if ($f === 'role') {
                $p[":$f"] = ($b[$f] === 'admin') ? 'admin' : 'user';
            } elseif ($f === 'status') {
                $p[":$f"] = ($b[$f] === 'inactive') ? 'inactive' : 'active';
            } elseif ($f === 'name') {
                $p[":$f"] = $b[$f] !== null ? $b[$f] : '';
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

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE `users` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
        $after = fetchUserSnapshot($db, $id);
        writeBackupEntry($db, 'users', $id, 'update', $before, $after, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok($after, 200);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถแก้ไขผู้ใช้งานได้', 500);
    }
}

if ($m === 'DELETE') {
    $auth = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    if ((int)$auth['uid'] === $id) err('Cannot delete your own account', 400);
    $before = fetchUserSnapshot($db, $id);
    if (!$before) err('ไม่พบ', 404);

    try {
        $db->beginTransaction();
        $st = $db->prepare('DELETE FROM `users` WHERE `id`=?');
        $st->execute(array($id));
        if (!$st->rowCount()) {
            throw new RuntimeException('User delete failed');
        }
        writeBackupEntry($db, 'users', $id, 'delete', $before, null, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok(array('success' => true), 200);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถลบผู้ใช้งานได้', 500);
    }
}

err('Method not allowed', 405);
