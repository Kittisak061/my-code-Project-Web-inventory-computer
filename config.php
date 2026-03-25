<?php
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','12345678'); // แก้ตรงนี้
define('DB_NAME','devicehub');
define('DB_CHARSET','utf8mb4');

function getDB(){
    static $p=null;
    if($p===null){
        try{
            $p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
                DB_USER,DB_PASS,array(
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES=>false
                ));
        }catch(PDOException $e){
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            die(json_encode(array('error'=>'DB: '.$e->getMessage())));
        }
    }
    return $p;
}
// CORS — ใช้ * เพราะไม่ใช้ session cookie แล้ว
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type,X-Auth-Token');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

function ok($d,$c=200){http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function err($m,$c=400){ok(array('error'=>$m),$c);}
function body(){$r=file_get_contents('php://input');$d=json_decode($r,true);return $d?$d:array();}
function gv($a,$k,$def=null){return isset($a[$k])&&$a[$k]!==''?$a[$k]:$def;}
