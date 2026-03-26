<?php
require_once __DIR__ . '/config.php';

$m = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

if ($m === 'GET' && (isset($_GET['ping']) || $action === 'ping')) {
    ok(array('success' => true, 'status' => 'ok', 'time' => gmdate('c')), 200);
}

if ($m === 'POST' && $action === 'change_password') {
    $auth = requireAuth();
    $b = body();
    $uid = isset($b['user_id']) ? (int)$b['user_id'] : 0;
    $old = isset($b['old_password']) ? $b['old_password'] : '';
    $new = isset($b['new_password']) ? $b['new_password'] : '';

    if (!$uid || !$old || !$new || strlen($new) < 4) err('ข้อมูลไม่ครบ', 400);
    if ($uid !== (int)$auth['uid']) err('Forbidden', 403);

    $db = getDB();
    $stmt = $db->prepare('SELECT `password` FROM `users` WHERE `id`=? AND `status`=? LIMIT 1');
    $stmt->execute(array($uid, 'active'));
    $row = $stmt->fetch();
    if (!$row || !password_verify($old, $row['password'])) err('รหัสผ่านเก่าไม่ถูกต้อง', 401);

    $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?')
       ->execute(array(password_hash($new, PASSWORD_DEFAULT), $uid));

    ok(array('success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'));
}

if ($m === 'POST' && $action === 'set_password') {
    requireAdmin();
    $b = body();
    $uid = isset($b['user_id']) ? (int)$b['user_id'] : 0;
    $new = isset($b['new_password']) ? $b['new_password'] : '';

    if (!$uid || !$new || strlen($new) < 4) err('ข้อมูลไม่ครบ', 400);

    $db = getDB();
    $stmt = $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?');
    $stmt->execute(array(password_hash($new, PASSWORD_DEFAULT), $uid));
    if (!$stmt->rowCount()) err('ไม่พบ user', 404);

    ok(array('success' => true, 'message' => 'ตั้งรหัสผ่านสำเร็จ'));
}

if ($m === 'POST' && $action === '') {
    $b = body();
    $username = isset($b['username']) ? trim($b['username']) : '';
    $password = isset($b['password']) ? $b['password'] : '';

    if (!$username || !$password) err('กรุณากรอก username และ password', 400);

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM `users` WHERE `username`=? AND `status`=? LIMIT 1');
    $stmt->execute(array($username, 'active'));
    $user = $stmt->fetch();
    if (!$user) err('ไม่พบผู้ใช้งาน หรือบัญชีถูกระงับ', 401);

    $valid = false;
    if (!empty($user['password']) && function_exists('password_verify')) {
        $valid = password_verify($password, $user['password']);
    } elseif (!empty($user['password'])) {
        $valid = ($password === $user['password']);
    }
    if (!$valid) err('รหัสผ่านไม่ถูกต้อง', 401);

    try {
        $db->prepare('UPDATE `users` SET `last_login`=NOW() WHERE `id`=?')->execute(array($user['id']));
    } catch (Exception $e) {
    }

    $token = createAuthToken($user);
    ok(array(
        'success' => true,
        'token' => $token,
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'role' => $user['role'],
        'dept' => isset($user['dept']) ? $user['dept'] : '',
        'expires_at' => gmdate('c', time() + 28800)
    ));
}

err('Method not allowed', 405);
