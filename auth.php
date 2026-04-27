<?php
// ============================================================
// Auth API
// 1. Bootstrap / request action
// 2. User lookup helpers
// 3. Login / change_password / set_password handlers
// ============================================================
require_once __DIR__ . '/config.php';

$m      = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

function findActiveUserByUsername($db, $username) {
    $st = $db->prepare('SELECT `id`,`username`,`name`,`role`,`dept`,`password`,`status` FROM `users` WHERE `username`=? AND `status`=? LIMIT 1');
    $st->execute(array($username, 'active'));
    return $st->fetch();
}

// ── Ping (health check) ────────────────────────────────────────
if ($m === 'GET' && (isset($_GET['ping']) || $action === 'ping')) {
    ok(array('success' => true, 'status' => 'ok', 'time' => gmdate('c')));
}

// ── Change own password ────────────────────────────────────────
if ($m === 'POST' && $action === 'change_password') {
    $auth = requireAuth();
    $b    = body();
    $uid  = isset($b['user_id'])      ? (int)$b['user_id']       : 0;
    $old  = isset($b['old_password']) ? (string)$b['old_password'] : '';
    $new  = isset($b['new_password']) ? (string)$b['new_password'] : '';

    if (!$uid || !$old || !$new || strlen($new) < 4) err('ข้อมูลไม่ครบ', 400);
    if ($uid !== (int)$auth['uid']) err('Forbidden', 403);

    $db   = getDB();
    $st   = $db->prepare('SELECT `password` FROM `users` WHERE `id`=? AND `status`=? LIMIT 1');
    $st->execute(array($uid, 'active'));
    $row  = $st->fetch();

    // ต้อง bcrypt เท่านั้น — ไม่รับ plain-text อีกต่อไป
    if (!$row || !password_verify($old, $row['password'])) {
        sleep(1);
        err('รหัสผ่านเก่าไม่ถูกต้อง', 401);
    }

    $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?')
       ->execute(array(password_hash($new, PASSWORD_DEFAULT), $uid));

    ok(array('success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'));
}

// ── Admin: set password for any user ──────────────────────────
if ($m === 'POST' && $action === 'set_password') {
    requireAdmin();
    $b   = body();
    $uid = isset($b['user_id'])      ? (int)$b['user_id']       : 0;
    $new = isset($b['new_password']) ? (string)$b['new_password'] : '';

    if (!$uid || !$new || strlen($new) < 4) err('ข้อมูลไม่ครบ', 400);

    $db = getDB();
    $st = $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?');
    $st->execute(array(password_hash($new, PASSWORD_DEFAULT), $uid));
    if (!$st->rowCount()) err('ไม่พบผู้ใช้', 404);

    ok(array('success' => true, 'message' => 'ตั้งรหัสผ่านสำเร็จ'));
}

// ── Login ──────────────────────────────────────────────────────
if ($m === 'POST' && $action === '') {
    $b        = body();
    $username = isset($b['username']) ? trim((string)$b['username']) : '';
    $password = isset($b['password']) ? (string)$b['password']      : '';

    if (!$username || !$password) err('กรุณากรอก username และ password', 400);

    $db   = getDB();
    $user = findActiveUserByUsername($db, $username);

    // ใช้ bcrypt เท่านั้น — ไม่มี plain-text fallback
    $valid = $user && !empty($user['password']) && password_verify($password, $user['password']);

    if (!$valid) {
        sleep(1); //늦춰서 brute-force ทำได้ยากขึ้น
        err('Username หรือ Password ไม่ถูกต้อง', 401);
    }

    try {
        $db->prepare('UPDATE `users` SET `last_login`=NOW() WHERE `id`=?')->execute(array($user['id']));
    } catch (Exception $e) {}

    ok(array(
        'success'    => true,
        'token'      => createAuthToken($user),
        'user_id'    => (int)$user['id'],
        'username'   => $user['username'],
        'name'       => $user['name'],
        'role'       => $user['role'],
        'dept'       => isset($user['dept']) ? $user['dept'] : '',
        'expires_at' => gmdate('c', time() + 28800)
    ));
}

err('Method not allowed', 405);
