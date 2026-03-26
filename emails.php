<?php
require_once __DIR__ . '/config.php';

$db = getDB();
$m = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$FIELDS = array(
  'dept','email','email_old','computer_name','computer_name_old','name','password',
  'status_user_email','user_computer',
  'mfa_email_user','mfa_ipad_app','mfa_enable_admin','mfa_status','mfa_announcement',
  'login_outlook','login_web_browser','login_ms_team','login_ipad','login_iphone','onedrive_list',
  'quota','used','type','note',
  'update_date','update_by'
);

function findEmailAccount($db, $email, $ignoreId = 0) {
  $sql = 'SELECT `id` FROM `email_accounts` WHERE `email`=?';
  $params = array($email);
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
  $w = array(); $p = array();
  if (!empty($_GET['status']))     { $w[] = '`status_user_email`=:s';  $p[':s'] = $_GET['status']; }
  if (!empty($_GET['dept']))       { $w[] = '`dept`=:d';               $p[':d'] = $_GET['dept']; }
  if (!empty($_GET['mfa_status'])) { $w[] = '`mfa_status`=:mfa';       $p[':mfa'] = $_GET['mfa_status']; }
  if (!empty($_GET['q'])) {
    $q = '%' . $_GET['q'] . '%';
    $w[] = '(`email` LIKE :q OR `name` LIKE :q2 OR `dept` LIKE :q3 OR `computer_name` LIKE :q4 OR `email_old` LIKE :q5)';
    $p[':q'] = $q; $p[':q2'] = $q; $p[':q3'] = $q; $p[':q4'] = $q; $p[':q5'] = $q;
  }
  $sql = 'SELECT * FROM `email_accounts`' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY `created_at` DESC';
  $st = $db->prepare($sql);
  $st->execute($p);
  ok($st->fetchAll(), 200);
}

if ($m === 'POST') {
  $auth = requireAdmin();
  $b = normalizePayloadFields(body(), $FIELDS);
  if (empty($b['email']) || empty($b['name'])) err('กรุณากรอก email และ name', 400);

  $email = (string)$b['email'];
  if (findEmailAccount($db, $email)) err('Email นี้มีอยู่แล้ว', 409);

  $cols = array('`email`','`name`');
  $vals = array(':email',':name');
  $p = array(':email' => $email, ':name' => $b['name']);

  foreach ($FIELDS as $f) {
    if ($f === 'email' || $f === 'name') continue;
    if (array_key_exists($f, $b)) {
      $cols[] = "`$f`";
      $vals[] = ":$f";
      $p[":$f"] = $b[$f];
    }
  }

  if (!isset($p[':update_by']) || !$p[':update_by']) {
    $cols[] = '`update_by`';
    $vals[] = ':update_by';
    $p[':update_by'] = isset($auth['username']) ? $auth['username'] : 'system';
  }

  $sql = 'INSERT INTO `email_accounts`(' . implode(',', $cols) . ') VALUES(' . implode(',', $vals) . ')';
  $db->prepare($sql)->execute($p);
  $nid = $db->lastInsertId();
  $st = $db->prepare('SELECT * FROM `email_accounts` WHERE `id`=?');
  $st->execute(array($nid));
  ok($st->fetch(), 201);
}

if ($m === 'PUT') {
  $auth = requireAdmin();
  if (!$id) err('ต้องระบุ id', 400);
  $b = normalizePayloadFields(body(), $FIELDS);

  if (array_key_exists('email', $b)) {
    $email = normalizeNullableText($b['email']);
    if ($email === null || $email === '') err('Email ว่างไม่ได้', 400);
    if (findEmailAccount($db, $email, $id)) err('Email นี้มีอยู่แล้ว', 409);
    $b['email'] = $email;
  }

  $sets = array();
  $p = array(':id' => $id);
  foreach ($FIELDS as $f) {
    if (array_key_exists($f, $b)) {
      $sets[] = "`$f`=:$f";
      $p[":$f"] = $b[$f];
    }
  }
  if (!array_key_exists('update_by', $b)) {
    $sets[] = '`update_by`=:update_by';
    $p[':update_by'] = isset($auth['username']) ? $auth['username'] : 'system';
  }
  if (empty($sets)) err('ไม่มีข้อมูล', 400);

  $db->prepare('UPDATE `email_accounts` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
  $st = $db->prepare('SELECT * FROM `email_accounts` WHERE `id`=?');
  $st->execute(array($id));
  ok($st->fetch(), 200);
}

if ($m === 'DELETE') {
  requireAdmin();
  if (!$id) err('ต้องระบุ id', 400);
  $st = $db->prepare('DELETE FROM `email_accounts` WHERE `id`=?');
  $st->execute(array($id));
  if (!$st->rowCount()) err('ไม่พบ', 404);
  ok(array('success' => true), 200);
}

err('Method not allowed', 405);
