<?php
// ============================================================
// Repair Tickets API
// 1. Bootstrap / request context
// 2. Ticket lookup helpers
// 3. Main REST handlers (GET / POST / PUT / DELETE)
// ============================================================
require_once __DIR__ . '/config.php';

$db     = getDB();
$m      = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$FIELDS = array('title','device','reporter','priority','status','tech','description');

// ---------- 2) Lookup helpers ----------
function fetchRepairTicketById($db, $id) {
    $st = $db->prepare('SELECT * FROM `repair_tickets` WHERE `id`=?');
    $st->execute(array((int)$id));
    return $st->fetch();
}

function normalizeRepairPayload($payload) {
    global $FIELDS;
    return normalizePayloadFields($payload, $FIELDS);
}

function sanitizeTicketPriority($value) {
    $allowed = array('low','medium','high');
    return in_array($value, $allowed, true) ? $value : 'medium';
}

function sanitizeTicketStatus($value) {
    $allowed = array('open','in_progress','closed');
    return in_array($value, $allowed, true) ? $value : 'open';
}

// ---------- 3) GET ----------
if ($m === 'GET') {
    requireAuth();
    $w = array(); $p = array();
    if (!empty($_GET['status']))   { $w[] = '`status`=:s';    $p[':s']  = $_GET['status']; }
    if (!empty($_GET['priority'])) { $w[] = '`priority`=:pr'; $p[':pr'] = $_GET['priority']; }
    if (!empty($_GET['q'])) {
        $q = '%' . $_GET['q'] . '%';
        $w[] = '(`title` LIKE :q OR `device` LIKE :q2 OR `reporter` LIKE :q3 OR `ticket` LIKE :q4)';
        $p[':q'] = $q; $p[':q2'] = $q; $p[':q3'] = $q; $p[':q4'] = $q;
    }
    $sql = 'SELECT * FROM `repair_tickets`' . ($w ? ' WHERE '.implode(' AND ',$w) : '') . ' ORDER BY `created_at` DESC';
    $st  = $db->prepare($sql);
    $st->execute($p);
    ok($st->fetchAll());
}

// ---------- POST ----------
if ($m === 'POST') {
    $auth = requireAdmin();
    $b    = normalizeRepairPayload(body());
    if (empty($b['title'])) err('กรุณากรอกหัวข้อ', 400);

    try {
        $db->beginTransaction();

        $st = $db->prepare('INSERT INTO `repair_tickets`(`title`,`device`,`reporter`,`priority`,`status`,`tech`,`description`) VALUES(:t,:d,:r,:p,:s,:te,:de)');
        $st->execute(array(
            ':t'  => $b['title'],
            ':d'  => gv($b,'device'),
            ':r'  => gv($b,'reporter'),
            ':p'  => sanitizeTicketPriority(gv($b,'priority','medium')),
            ':s'  => sanitizeTicketStatus(gv($b,'status','open')),
            ':te' => gv($b,'tech'),
            ':de' => gv($b,'description')
        ));

        $nid = $db->lastInsertId();
        $tk  = 'TKT-' . str_pad($nid, 4, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE `repair_tickets` SET `ticket`=? WHERE `id`=?')->execute(array($tk, $nid));

        $created = fetchRepairTicketById($db, $nid);
        writeBackupEntry($db, 'tickets', $nid, 'create', null, $created, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok($created, 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถสร้าง ticket ได้', 500);
    }
}

// ---------- PUT ----------
if ($m === 'PUT') {
    $auth   = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    $before = fetchRepairTicketById($db, $id);
    if (!$before) err('ไม่พบ ticket', 404);

    $b    = normalizeRepairPayload(body());
    $sets = array(); $p = array(':id' => $id);
    foreach ($FIELDS as $k) {
        if (!array_key_exists($k, $b)) continue;
        $sets[] = "`$k`=:$k";
        $p[":$k"] = ($k === 'priority') ? sanitizeTicketPriority($b[$k])
                  : (($k === 'status')  ? sanitizeTicketStatus($b[$k])
                  : $b[$k]);
    }
    if (empty($sets)) err('ไม่มีข้อมูล', 400);

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE `repair_tickets` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
        $after = fetchRepairTicketById($db, $id);
        writeBackupEntry($db, 'tickets', $id, 'update', $before, $after, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok($after);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถแก้ไข ticket ได้', 500);
    }
}

// ---------- DELETE ----------
if ($m === 'DELETE') {
    $auth   = requireAdmin();
    if (!$id) err('ต้องระบุ id', 400);
    $before = fetchRepairTicketById($db, $id);
    if (!$before) err('ไม่พบ', 404);

    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM `repair_tickets` WHERE `id`=?')->execute(array($id));
        writeBackupEntry($db, 'tickets', $id, 'delete', $before, null, isset($auth['username']) ? $auth['username'] : 'system');
        $db->commit();
        ok(array('success' => true));
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        err('ไม่สามารถลบ ticket ได้', 500);
    }
}

err('Method not allowed', 405);
