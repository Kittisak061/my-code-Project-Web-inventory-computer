<?php
require_once __DIR__.'/config.php';
$db=$db=getDB();$m=$_SERVER['REQUEST_METHOD'];$id=isset($_GET['id'])?$_GET['id']:null;

if($m==='GET'){
    $w=array();$p=array();
    if(!empty($_GET['role'])){$w[]='`role`=:r';$p[':r']=$_GET['role'];}
    if(!empty($_GET['status'])){$w[]='`status`=:s';$p[':s']=$_GET['status'];}
    if(!empty($_GET['q'])){$q='%'.$_GET['q'].'%';$w[]='(`name` LIKE :q OR `username` LIKE :q2 OR `email` LIKE :q3 OR `dept` LIKE :q4)';$p[':q']=$q;$p[':q2']=$q;$p[':q3']=$q;$p[':q4']=$q;}
    $sql='SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`last_login`,`created_at` FROM `users`'.($w?' WHERE '.implode(' AND ',$w):'').' ORDER BY `created_at` DESC';
    $st=$db->prepare($sql);$st->execute($p);ok($st->fetchAll(),200);
}
if($m==='POST'){
    $b=body();
    if(empty($b['name'])||empty($b['email'])||empty($b['username']))err('กรุณากรอก name, username และ email',400);
    if(empty($b['password'])||strlen($b['password'])<4)err('กรุณากรอก password อย่างน้อย 4 ตัว',400);
    $hash=password_hash($b['password'],PASSWORD_DEFAULT);
    $role=(isset($b['role'])&&$b['role']==='admin')?'admin':'user';
    $un=trim($b['username']);
    $chk=$db->prepare('SELECT `id` FROM `users` WHERE `username`=? OR `email`=? LIMIT 1');
    $chk->execute(array($un,$b['email']));
    if($chk->fetch())err('Username หรือ Email นี้มีอยู่แล้ว',409);
    $st=$db->prepare('INSERT INTO `users`(`name`,`username`,`password`,`email`,`dept`,`role`,`phone`,`status`,`devices`) VALUES(:n,:u,:pw,:e,:d,:r,:p,:s,0)');
    $st->execute(array(':n'=>$b['name'],':u'=>$un,':pw'=>$hash,':e'=>$b['email'],':d'=>gv($b,'dept'),':r'=>$role,':p'=>gv($b,'phone'),':s'=>gv($b,'status','active')));
    $nid=$db->lastInsertId();
    $st=$db->prepare('SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`created_at` FROM `users` WHERE `id`=?');
    $st->execute(array($nid));ok($st->fetch(),201);
}
if($m==='PUT'){
    if(!$id)err('ต้องระบุ id',400);$b=body();
    $fields=array('name','username','email','dept','role','phone','status','devices');
    $sets=array();$p=array(':id'=>$id);
    foreach($fields as $f){if(array_key_exists($f,$b)){$sets[]="`$f`=:$f";$p[":$f"]=($f==='role')?($b[$f]==='admin'?'admin':'user'):$b[$f];}}
    if(!empty($b['password'])&&strlen($b['password'])>=4){$sets[]='`password`=:pw';$p[':pw']=password_hash($b['password'],PASSWORD_DEFAULT);}
    if(empty($sets))err('ไม่มีข้อมูล',400);
    $db->prepare('UPDATE `users` SET '.implode(',',$sets).' WHERE `id`=:id')->execute($p);
    $st=$db->prepare('SELECT `id`,`name`,`username`,`email`,`dept`,`role`,`phone`,`devices`,`status`,`last_login`,`created_at` FROM `users` WHERE `id`=?');
    $st->execute(array($id));ok($st->fetch(),200);
}
if($m==='DELETE'){
    if(!$id)err('ต้องระบุ id',400);
    $st=$db->prepare('DELETE FROM `users` WHERE `id`=?');$st->execute(array($id));
    if(!$st->rowCount())err('ไม่พบ',404);
    ok(array('success'=>true),200);
}
err('Method not allowed',405);
