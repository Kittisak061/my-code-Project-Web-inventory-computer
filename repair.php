<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$m  = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($m === 'GET') {
    $w = array(); $p = array();
    if (!empty($_GET['status']))   { $w[] = '`status`=:s';    $p[':s']  = $_GET['status'];   }
    if (!empty($_GET['priority'])) { $w[] = '`priority`=:pr'; $p[':pr'] = $_GET['priority']; }
    if (!empty($_GET['q'])) {
        $q = '%' . $_GET['q'] . '%';
        $w[] = '(`title` LIKE :q OR `device` LIKE :q2 OR `reporter` LIKE :q3 OR `ticket` LIKE :q4)';
        $p[':q']=$q; $p[':q2']=$q; $p[':q3']=$q; $p[':q4']=$q;
    }
    $sql = 'SELECT * FROM `repair_tickets`' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY `created_at` DESC';
    $st = $db->prepare($sql); $st->execute($p);
    ok($st->fetchAll(), 200);
}

if ($m === 'POST') {
    $b = body();
    if (empty($b['title'])) err('กรุณากรอกหัวข้อ', 400);
    $st = $db->prepare('INSERT INTO `repair_tickets`(`title`,`device`,`reporter`,`priority`,`status`,`tech`,`description`) VALUES(:t,:d,:r,:p,:s,:te,:de)');
    $st->execute(array(
        ':t'  => $b['title'],
        ':d'  => gv($b,'device'),
        ':r'  => gv($b,'reporter'),
        ':p'  => gv($b,'priority','medium'),
        ':s'  => gv($b,'status','open'),
        ':te' => gv($b,'tech'),
        ':de' => gv($b,'description')
    ));
    $nid = $db->lastInsertId();
    $tk = 'TKT-' . str_pad($nid, 4, '0', STR_PAD_LEFT);
    $db->prepare('UPDATE `repair_tickets` SET `ticket`=? WHERE `id`=?')->execute(array($tk, $nid));
    $st = $db->prepare('SELECT * FROM `repair_tickets` WHERE `id`=?');
    $st->execute(array($nid));
    ok($st->fetch(), 201);
}

if ($m === 'PUT') {
    if (!$id) err('ต้องระบุ id', 400);
    $b = body();
    $fields = array('title','device','reporter','priority','status','tech','description');
    $sets = array(); $p = array(':id' => $id);
    foreach ($fields as $k) {
        if (array_key_exists($k, $b)) { $sets[] = "`$k`=:$k"; $p[":$k"] = $b[$k]; }
    }
    if (empty($sets)) err('ไม่มีข้อมูล', 400);
    $db->prepare('UPDATE `repair_tickets` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
    $st = $db->prepare('SELECT * FROM `repair_tickets` WHERE `id`=?');
    $st->execute(array($id));
    ok($st->fetch(), 200);
}

if ($m === 'DELETE') {
    if (!$id) err('ต้องระบุ id', 400);
    $st = $db->prepare('DELETE FROM `repair_tickets` WHERE `id`=?');
    $st->execute(array($id));
    if (!$st->rowCount()) err('ไม่พบ', 404);
    ok(array('success' => true), 200);
}

err('Method not allowed', 405);
