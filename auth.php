<?php
// Auth — ไม่ใช้ PHP Session เลย ใช้ DB verify อย่างเดียว
require_once __DIR__.'/config.php';

$m = $_SERVER['REQUEST_METHOD'];

// POST /api/auth.php — Login
if($m==='POST'){
    $b = body();
    $username = isset($b['username'])?trim($b['username']):'';
    $password = isset($b['password'])?$b['password']:'';
    if(!$username||!$password) err('กรุณากรอก username และ password',400);

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM `users` WHERE `username`=? AND `status`=? LIMIT 1');
    $stmt->execute(array($username,'active'));
    $user = $stmt->fetch();

    if(!$user) err('ไม่พบผู้ใช้งาน หรือบัญชีถูกระงับ',401);

    // Verify password (bcrypt หรือ plain)
    $valid = false;
    if(!empty($user['password'])){
        if(function_exists('password_verify')){
            $valid = password_verify($password,$user['password']);
        } else {
            $valid = ($password===$user['password']);
        }
    }
    if(!$valid) err('รหัสผ่านไม่ถูกต้อง',401);

    // Update last_login
    try{ $db->prepare('UPDATE `users` SET `last_login`=NOW() WHERE `id`=?')->execute(array($user['id'])); }catch(Exception $e){}

    ok(array(
        'success'  => true,
        'user_id'  => $user['id'],
        'username' => $user['username'],
        'name'     => $user['name'],
        'role'     => $user['role'],
        'dept'     => isset($user['dept'])?$user['dept']:'',
    ));
}

// POST /api/auth.php?action=change_password
if($m==='POST'&&isset($_GET['action'])&&$_GET['action']==='change_password'){
    $b   = body();
    $uid = isset($b['user_id'])?(int)$b['user_id']:0;
    $old = isset($b['old_password'])?$b['old_password']:'';
    $new = isset($b['new_password'])?$b['new_password']:'';
    if(!$uid||!$old||!$new||strlen($new)<4) err('ข้อมูลไม่ครบ',400);

    $db   = getDB();
    $stmt = $db->prepare('SELECT `password` FROM `users` WHERE `id`=?');
    $stmt->execute(array($uid));
    $row  = $stmt->fetch();
    if(!$row||!password_verify($old,$row['password'])) err('รหัสผ่านเก่าไม่ถูกต้อง',401);

    $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?')
       ->execute(array(password_hash($new,PASSWORD_DEFAULT),$uid));
    ok(array('success'=>true,'message'=>'เปลี่ยนรหัสผ่านสำเร็จ'));
}

// POST /api/auth.php?action=set_password (admin only - verified by frontend role check)
if($m==='POST'&&isset($_GET['action'])&&$_GET['action']==='set_password'){
    $b   = body();
    $uid = isset($b['user_id'])?(int)$b['user_id']:0;
    $new = isset($b['new_password'])?$b['new_password']:'';
    if(!$uid||!$new||strlen($new)<4) err('ข้อมูลไม่ครบ',400);

    $db   = getDB();
    $stmt = $db->prepare('UPDATE `users` SET `password`=? WHERE `id`=?');
    $stmt->execute(array(password_hash($new,PASSWORD_DEFAULT),$uid));
    if(!$stmt->rowCount()) err('ไม่พบ user',404);
    ok(array('success'=>true,'message'=>'ตั้งรหัสผ่านสำเร็จ'));
}

err('Method not allowed',405);
