<?php
require_once __DIR__ . '/config.php';

$db = getDB();
requireAuth();

function fetchAllSafe($db, $sql, $fallback = array()) {
    try {
        return $db->query($sql)->fetchAll();
    } catch (Exception $e) {
        return $fallback;
    }
}

function fetchOneSafe($db, $sql, $fallback = array()) {
    try {
        $row = $db->query($sql)->fetch();
        return $row ? $row : $fallback;
    } catch (Exception $e) {
        return $fallback;
    }
}

function normalizeDeviceStatus($value) {
    return $value === 'maintenance' ? 'maintenance' : '';
}

$devices = fetchAllSafe($db, 'SELECT `status`, `type`, `dept`, `computer_name`, `remark` FROM `devices`');

try {
    $emails = $db->query('SELECT `status_user_email` AS `status`, `used`, `quota` FROM `email_accounts`')->fetchAll();
} catch (Exception $e) {
    $emails = fetchAllSafe($db, 'SELECT `status`, `used`, `quota` FROM `email_accounts`');
}

$users = fetchAllSafe($db, 'SELECT `status`, `role` FROM `users`');
$tickets = fetchAllSafe($db, 'SELECT `status`, `priority` FROM `repair_tickets`');
$byDept = fetchAllSafe(
    $db,
    'SELECT `dept`, COUNT(*) AS cnt FROM `devices` GROUP BY `dept` ORDER BY cnt DESC, `dept` ASC LIMIT 10'
);
$byType = fetchAllSafe(
    $db,
    'SELECT `type`, COUNT(*) AS cnt FROM `devices` GROUP BY `type` ORDER BY cnt DESC, `type` ASC'
);
$emailTotals = fetchOneSafe($db, 'SELECT SUM(`used`) AS u, SUM(`quota`) AS q FROM `email_accounts`', array('u' => 0, 'q' => 0));

$deviceMaintenance = 0;
$deviceRemarks = 0;
foreach ($devices as $row) {
    if (normalizeDeviceStatus(isset($row['status']) ? $row['status'] : '') === 'maintenance') {
        $deviceMaintenance++;
    }
    if (!empty($row['remark'])) {
        $deviceRemarks++;
    }
}

$emailActive = 0;
$emailInactive = 0;
$emailSuspended = 0;
foreach ($emails as $row) {
    $rowStatus = isset($row['status']) ? $row['status'] : '';
    if ($rowStatus === 'active') {
        $emailActive++;
    } elseif ($rowStatus === 'inactive') {
        $emailInactive++;
    } else {
        $emailSuspended++;
    }
}

$userActive = 0;
$userAdmins = 0;
$userNormal = 0;
foreach ($users as $row) {
    $rowStatus = isset($row['status']) ? $row['status'] : '';
    $rowRole = isset($row['role']) ? $row['role'] : '';
    if ($rowStatus === 'active') {
        $userActive++;
    }
    if ($rowRole === 'admin') {
        $userAdmins++;
    } elseif ($rowRole === 'user') {
        $userNormal++;
    }
}

$ticketOpen = 0;
$ticketInProgress = 0;
$ticketResolved = 0;
$ticketHigh = 0;
$ticketMedium = 0;
foreach ($tickets as $row) {
    $rowStatus = isset($row['status']) ? $row['status'] : '';
    $rowPriority = isset($row['priority']) ? $row['priority'] : '';
    if ($rowStatus === 'open') {
        $ticketOpen++;
    } elseif ($rowStatus === 'in_progress') {
        $ticketInProgress++;
    } else {
        $ticketResolved++;
    }

    if ($rowPriority === 'high') {
        $ticketHigh++;
    } elseif ($rowPriority === 'medium') {
        $ticketMedium++;
    }
}

ok(array(
    'generated_at' => gmdate('c'),
    'devices' => array(
        'total' => count($devices),
        'maintenance' => $deviceMaintenance,
        'with_remark' => $deviceRemarks
    ),
    'emails' => array(
        'total' => count($emails),
        'active' => $emailActive,
        'inactive' => $emailInactive,
        'suspended' => $emailSuspended,
        'total_used' => (int)(isset($emailTotals['u']) ? $emailTotals['u'] : 0),
        'total_quota' => (int)(isset($emailTotals['q']) ? $emailTotals['q'] : 0)
    ),
    'users' => array(
        'total' => count($users),
        'active' => $userActive,
        'admins' => $userAdmins,
        'users' => $userNormal
    ),
    'tickets' => array(
        'total' => count($tickets),
        'open' => $ticketOpen,
        'in_progress' => $ticketInProgress,
        'resolved' => $ticketResolved,
        'high' => $ticketHigh,
        'medium' => $ticketMedium
    ),
    'by_dept' => $byDept,
    'by_type' => $byType
), 200);
