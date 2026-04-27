<?php
require_once __DIR__ . '/config.php';

// ============================================================
// Email Accounts API
// 1. Bootstrap / writable fields
// 2. Lookup helpers
// 3. Main REST handlers
// ============================================================

$db = getDB();
$m = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- 1) Bootstrap / writable fields ----------
$FIELDS = array(
  'dept','email','email_old','computer_name','computer_name_old','name','password',
  'status_user_email','user_computer',
  'mfa_email_user','mfa_ipad_app','mfa_enable_admin','mfa_status','mfa_announcement',
  'login_outlook','login_web_browser','login_ms_team','login_ipad','login_iphone','onedrive_list',
  'quota','used','type','note',
  'update_date','update_by'
);

// ---------- 2) Lookup helpers ----------
function fetchEmailAccountById($db, $id) {
  $st = $db->prepare('SELECT * FROM `email_accounts` WHERE `id`=?');
  $st->execute(array($id));
  return $st->fetch();
}

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

function resolveComputerNameByEmail($db, $email) {
  $email = normalizeNullableText($email);
  if ($email === null || $email === '') return null;

  $byEmail = $db->prepare('SELECT `computer_name` FROM `devices` WHERE LOWER(TRIM(`email`)) = LOWER(TRIM(?)) AND `computer_name` IS NOT NULL AND `computer_name` <> \'\' LIMIT 1');
  $byEmail->execute(array($email));
  $row = $byEmail->fetch();
  if ($row && !empty($row['computer_name'])) {
    return $row['computer_name'];
  }

  $parts = explode('@', (string)$email, 2);
  $username = normalizeNullableText(isset($parts[0]) ? $parts[0] : null);
  if ($username === null || $username === '') return null;

  $byUsername = $db->prepare('SELECT `computer_name` FROM `devices` WHERE LOWER(TRIM(`username`)) = LOWER(TRIM(?)) AND `computer_name` IS NOT NULL AND `computer_name` <> \'\' LIMIT 1');
  $byUsername->execute(array($username));
  $row = $byUsername->fetch();
  return ($row && !empty($row['computer_name'])) ? $row['computer_name'] : null;
}

// ---------- 3) Main REST handlers ----------
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
  if (empty($b['computer_name'])) {
    $b['computer_name'] = resolveComputerNameByEmail($db, $email);
  }
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
  $userName = isset($auth['username']) ? $auth['username'] : 'system';
  try {
    $db->beginTransaction();
    $db->prepare($sql)->execute($p);
    $nid = $db->lastInsertId();
    $created = fetchEmailAccountById($db, $nid);
    writeBackupEntry($db, 'emails', $nid, 'create', null, $created, $userName);
    $db->commit();
    ok($created, 201);
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    err('ไม่สามารถเพิ่มบัญชีอีเมลได้', 500);
  }
}

if ($m === 'PUT') {
  $auth = requireAdmin();
  if (!$id) err('ต้องระบุ id', 400);
  $before = fetchEmailAccountById($db, $id);
  if (!$before) err('ไม่พบ', 404);
  $b = normalizePayloadFields(body(), $FIELDS);

  if (array_key_exists('email', $b)) {
    $email = normalizeNullableText($b['email']);
    if ($email === null || $email === '') err('Email ว่างไม่ได้', 400);
    if (findEmailAccount($db, $email, $id)) err('Email นี้มีอยู่แล้ว', 409);
    $b['email'] = $email;
  }
  if ((!array_key_exists('computer_name', $b) || $b['computer_name'] === null || $b['computer_name'] === '')
      && !empty($b['email'])) {
    $resolvedComputer = resolveComputerNameByEmail($db, $b['email']);
    if ($resolvedComputer) {
      $b['computer_name'] = $resolvedComputer;
    }
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

  try {
    $db->beginTransaction();
    $db->prepare('UPDATE `email_accounts` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
    $after = fetchEmailAccountById($db, $id);
    writeBackupEntry($db, 'emails', $id, 'update', $before, $after, isset($auth['username']) ? $auth['username'] : 'system');
    $db->commit();
    ok($after, 200);
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    err('ไม่สามารถแก้ไขบัญชีอีเมลได้', 500);
  }
}

if ($m === 'DELETE') {
  $auth = requireAdmin();
  if (!$id) err('ต้องระบุ id', 400);
  $before = fetchEmailAccountById($db, $id);
  if (!$before) err('ไม่พบ', 404);
  try {
    $db->beginTransaction();
    $st = $db->prepare('DELETE FROM `email_accounts` WHERE `id`=?');
    $st->execute(array($id));
    if (!$st->rowCount()) {
      throw new RuntimeException('Email delete failed');
    }
    writeBackupEntry($db, 'emails', $id, 'delete', $before, null, isset($auth['username']) ? $auth['username'] : 'system');
    $db->commit();
    ok(array('success' => true), 200);
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    err('ไม่สามารถลบบัญชีอีเมลได้', 500);
  }
}

err('Method not allowed', 405);
