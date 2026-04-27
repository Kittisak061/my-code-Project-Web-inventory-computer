<?php
// ============================================================
// Dashboard API
// 1. Bootstrap / auth guard
// 2. Safe query helpers
// 3. Summary assembly
// 4. Response payload
// ============================================================
require_once __DIR__ . '/config.php';

$db = getDB();
requireAuth();

function fetchAllSafe($db, $sql, $fallback = array()) {
    try { return $db->query($sql)->fetchAll(); }
    catch (Exception $e) { return $fallback; }
}

function fetchOneSafe($db, $sql, $fallback = array()) {
    try {
        $row = $db->query($sql)->fetch();
        return $row ?: $fallback;
    } catch (Exception $e) { return $fallback; }
}

// ── Fetch data ─────────────────────────────────────────────────
$devices = fetchAllSafe($db, 'SELECT `status`,`type`,`dept`,`computer_name`,`remark` FROM `devices`');

try {
    $emails = $db->query('SELECT `status_user_email` AS `status`,`used`,`quota` FROM `email_accounts`')->fetchAll();
} catch (Exception $e) {
    $emails = array();
}

$users  = fetchAllSafe($db, 'SELECT `status`,`role` FROM `users`');

// repair_tickets อาจไม่มีอยู่ (ถ้า schema เก่า) — ใช้ fetchAllSafe จะ silent fail เป็น []
$tickets = fetchAllSafe($db, 'SELECT `status`,`priority` FROM `repair_tickets`');

$byDept = fetchAllSafe($db,
    'SELECT `dept`, COUNT(*) AS cnt FROM `devices` GROUP BY `dept` ORDER BY cnt DESC, `dept` ASC LIMIT 10');
$byType = fetchAllSafe($db,
    'SELECT `type`, COUNT(*) AS cnt FROM `devices` GROUP BY `type` ORDER BY cnt DESC, `type` ASC');
$emailTotals = fetchOneSafe($db,
    'SELECT SUM(`used`) AS u, SUM(`quota`) AS q FROM `email_accounts`',
    array('u'=>0,'q'=>0));

// ── Device counts ──────────────────────────────────────────────
$deviceOnline      = 0;
$deviceOffline     = 0;
$deviceMaintenance = 0;
$deviceRemarks     = 0;
foreach ($devices as $row) {
    $st = isset($row['status']) ? $row['status'] : '';
    if ($st === 'online')      $deviceOnline++;
    elseif ($st === 'offline') $deviceOffline++;
    elseif ($st === 'maintenance') $deviceMaintenance++;
    if (!empty($row['remark'])) $deviceRemarks++;
}

// ── Email counts ───────────────────────────────────────────────
$emailActive = $emailInactive = $emailSuspended = 0;
foreach ($emails as $row) {
    $st = isset($row['status']) ? $row['status'] : '';
    if ($st === 'active')        $emailActive++;
    elseif ($st === 'inactive')  $emailInactive++;
    else                         $emailSuspended++;
}

// ── User counts ────────────────────────────────────────────────
$userActive = $userAdmins = $userNormal = 0;
foreach ($users as $row) {
    if ((isset($row['status']) ? $row['status'] : '') === 'active') $userActive++;
    if ((isset($row['role']) ? $row['role'] : '') === 'admin')  $userAdmins++;
    elseif ((isset($row['role']) ? $row['role'] : '') === 'user') $userNormal++;
}

// ── Ticket counts ──────────────────────────────────────────────
$ticketOpen = $ticketInProgress = $ticketResolved = $ticketHigh = $ticketMedium = 0;
foreach ($tickets as $row) {
    $st = isset($row['status']) ? $row['status'] : '';
    $pr = isset($row['priority']) ? $row['priority'] : '';
    if ($st === 'open')            $ticketOpen++;
    elseif ($st === 'in_progress') $ticketInProgress++;
    else                           $ticketResolved++;
    if ($pr === 'high')   $ticketHigh++;
    elseif ($pr === 'medium') $ticketMedium++;
}

ok(array(
    'generated_at' => gmdate('c'),
    'devices' => array(
        'total'       => count($devices),
        'online'      => $deviceOnline,
        'offline'     => $deviceOffline,
        'maintenance' => $deviceMaintenance,
        'with_remark' => $deviceRemarks
    ),
    'emails' => array(
        'total'       => count($emails),
        'active'      => $emailActive,
        'inactive'    => $emailInactive,
        'suspended'   => $emailSuspended,
        'total_used'  => (int)(isset($emailTotals['u']) ? $emailTotals['u'] : 0),
        'total_quota' => (int)(isset($emailTotals['q']) ? $emailTotals['q'] : 0)
    ),
    'users' => array(
        'total'  => count($users),
        'active' => $userActive,
        'admins' => $userAdmins,
        'users'  => $userNormal
    ),
    'tickets' => array(
        'total'       => count($tickets),
        'open'        => $ticketOpen,
        'in_progress' => $ticketInProgress,
        'resolved'    => $ticketResolved,
        'high'        => $ticketHigh,
        'medium'      => $ticketMedium
    ),
    'by_dept' => $byDept,
    'by_type' => $byType
));
