<?php
// ============================================================
// Backup logs API
// 1. Bootstrap / auth guard
// 2. Filter preparation
// 3. Backup list response
// ============================================================
require_once __DIR__ . '/config.php';

$db = getDB();
ensureBackupTable($db);
$m = $_SERVER['REQUEST_METHOD'];

if ($m !== 'GET') {
    err('Method not allowed', 405);
}

requireAdmin();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 250;
if ($limit < 1) $limit = 250;
if ($limit > 1000) $limit = 1000;

$where = array();
$params = array();

if (!empty($_GET['entity_type'])) {
    $where[] = '`entity_type`=:entity_type';
    $params[':entity_type'] = (string)$_GET['entity_type'];
}

if (!empty($_GET['action_type'])) {
    $where[] = '`action_type`=:action_type';
    $params[':action_type'] = (string)$_GET['action_type'];
}

if (!empty($_GET['changed_by'])) {
    $where[] = '`changed_by`=:changed_by';
    $params[':changed_by'] = (string)$_GET['changed_by'];
}

if (!empty($_GET['date_from'])) {
    $where[] = '`changed_at` >= :date_from';
    $params[':date_from'] = (string)$_GET['date_from'] . ' 00:00:00';
}

if (!empty($_GET['date_to'])) {
    $where[] = '`changed_at` <= :date_to';
    $params[':date_to'] = (string)$_GET['date_to'] . ' 23:59:59';
}

if (!empty($_GET['q'])) {
    $q = '%' . trim((string)$_GET['q']) . '%';
    $where[] = '(`entity_label` LIKE :q OR `entity_id` LIKE :q2 OR `summary_text` LIKE :q3 OR `changed_by` LIKE :q4)';
    $params[':q'] = $q;
    $params[':q2'] = $q;
    $params[':q3'] = $q;
    $params[':q4'] = $q;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countSt = $db->prepare('SELECT COUNT(*) FROM `backup_logs`' . $whereSql);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$todayParams = $params;
$todaySql = 'SELECT COUNT(*) FROM `backup_logs`' . ($where ? $whereSql . ' AND DATE(`changed_at`) = :today' : ' WHERE DATE(`changed_at`) = :today');
$todayParams[':today'] = substr(appNow(), 0, 10);
$todaySt = $db->prepare($todaySql);
$todaySt->execute($todayParams);
$today = (int)$todaySt->fetchColumn();

$rowsSt = $db->prepare('SELECT * FROM `backup_logs`' . $whereSql . ' ORDER BY `changed_at` DESC, `id` DESC LIMIT ' . $limit);
$rowsSt->execute($params);
$rows = $rowsSt->fetchAll();

foreach ($rows as &$row) {
    $row['before_data'] = !empty($row['before_data']) ? json_decode($row['before_data'], true) : null;
    $row['after_data'] = !empty($row['after_data']) ? json_decode($row['after_data'], true) : null;
}
unset($row);

$actionSt = $db->prepare('SELECT `action_type`, COUNT(*) AS `cnt` FROM `backup_logs`' . $whereSql . ' GROUP BY `action_type`');
$actionSt->execute($params);
$byAction = $actionSt->fetchAll();

$entitySt = $db->prepare('SELECT `entity_type`, COUNT(*) AS `cnt` FROM `backup_logs`' . $whereSql . ' GROUP BY `entity_type`');
$entitySt->execute($params);
$byEntity = $entitySt->fetchAll();

ok(array(
    'items' => $rows,
    'summary' => array(
        'total' => $total,
        'today' => $today,
        'by_action' => $byAction,
        'by_entity' => $byEntity,
        'limit' => $limit
    )
), 200);
