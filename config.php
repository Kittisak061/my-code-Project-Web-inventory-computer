<?php
// ============================================================
// DeviceHub API Core v9
// 1. Load credentials from db.config.php
// 2. Common response helpers
// 3. Input normalization helpers
// 4. Backup / audit log helpers
// 5. Auth token helpers
// 6. Request auth guards
// ============================================================

// ---------- 1) Load credentials ----------
$_credFile = __DIR__ . '/db.config.php';
if (!file_exists($_credFile)) {
    http_response_code(500);
    die(json_encode(array('error' => 'Missing db.config.php — copy db.config.example.php and fill in your credentials'), JSON_UNESCAPED_UNICODE));
}
require_once $_credFile;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type,X-Auth-Token,Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- 2) Common response helpers ----------
function getDB() {
    static $p = null;
    if ($p === null) {
        try {
            $p = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                )
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(array('error' => 'Database connection failed'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
    return $p;
}

function ok($d, $c = 200) {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function err($m, $c = 400) {
    ok(array('error' => $m), $c);
}

function body() {
    $r = file_get_contents('php://input');
    if ($r === false || $r === '') return array();
    $d = json_decode($r, true);
    return is_array($d) ? $d : array();
}

function gv($a, $k, $def = null) {
    return isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $def;
}

// ---------- 3) Input normalization helpers ----------
function isNoDataMarker($value) {
    if ($value === null) return true;
    if (!is_string($value)) return false;
    $text = preg_replace('/\s+/u', ' ', trim($value));
    return $text === '' || $text === '-' || $text === '—' || $text === '–';
}

function normalizeNullableText($value) {
    if ($value === null) return null;
    if (is_bool($value) || is_int($value) || is_float($value)) return $value;
    $text = preg_replace('/\s+/u', ' ', trim((string)$value));
    return isNoDataMarker($text) ? null : $text;
}

function normalizePayloadFields($payload, $fields) {
    if (!is_array($payload)) return array();
    foreach ($fields as $field) {
        if (array_key_exists($field, $payload)) {
            $payload[$field] = normalizeNullableText($payload[$field]);
        }
    }
    return $payload;
}

// ---------- 4) Backup / audit log helpers ----------
function appNow() {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone('Asia/Bangkok');
    return (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
}

function ensureBackupTable($db) {
    static $ready = false;
    if ($ready) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `backup_logs` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `entity_type` VARCHAR(50) NOT NULL,
            `entity_id` VARCHAR(120) DEFAULT NULL,
            `entity_label` VARCHAR(190) DEFAULT NULL,
            `action_type` VARCHAR(20) NOT NULL,
            `changed_by` VARCHAR(120) DEFAULT NULL,
            `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `summary_text` VARCHAR(255) DEFAULT NULL,
            `before_data` LONGTEXT DEFAULT NULL,
            `after_data` LONGTEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_bl_at` (`changed_at`),
            KEY `idx_bl_entity` (`entity_type`,`entity_id`),
            KEY `idx_bl_action` (`action_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ready = true;
}

function maskBackupValue($value) {
    if ($value === null || $value === '') return $value;
    return is_scalar($value) ? '••••' : '[redacted]';
}

function sanitizeBackupSnapshot($entityType, $snapshot) {
    if (!is_array($snapshot)) return $snapshot;
    $copy = $snapshot;
    $maskFields = array();
    if ($entityType === 'devices')  $maskFields = array('win_key', 'office_key');
    if ($entityType === 'emails')   $maskFields = array('password');
    if ($entityType === 'users')    $maskFields = array('password');
    foreach ($maskFields as $f) {
        if (array_key_exists($f, $copy)) $copy[$f] = maskBackupValue($copy[$f]);
    }
    return $copy;
}

function backupJsonEncode($value) {
    if ($value === null) return null;
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function firstNonEmptyValue($values, $fallback = '') {
    foreach ($values as $v) {
        if ($v === null) continue;
        if (is_string($v) && trim($v) === '') continue;
        return (string)$v;
    }
    return $fallback;
}

function backupEntityLabel($entityType, $before, $after, $entityId = null) {
    $s = is_array($after) ? $after : (is_array($before) ? $before : array());
    $fb = $entityId !== null ? (string)$entityId : '';
    if ($entityType === 'devices')             return firstNonEmptyValue(array(gvs($s,'computer_name'), gvs($s,'id'), gvs($s,'fixed_asset_no')), $fb);
    if ($entityType === 'emails')              return firstNonEmptyValue(array(gvs($s,'email'), gvs($s,'name'), gvs($s,'id')), $fb);
    if ($entityType === 'users')               return firstNonEmptyValue(array(gvs($s,'username'), gvs($s,'name'), gvs($s,'id')), $fb);
    if ($entityType === 'device_custom_field') return firstNonEmptyValue(array(gvs($s,'label'), gvs($s,'field_key'), gvs($s,'id')), $fb);
    if ($entityType === 'tickets')             return firstNonEmptyValue(array(gvs($s,'ticket'), gvs($s,'title'), gvs($s,'id')), $fb);
    return $fb;
}

function gvs($a, $k) { return isset($a[$k]) ? $a[$k] : null; }

function backupEntityTypeLabel($entityType) {
    $map = array('devices'=>'Device','emails'=>'Email','users'=>'User','device_custom_field'=>'Device Field','tickets'=>'Ticket');
    return isset($map[$entityType]) ? $map[$entityType] : ucfirst(str_replace('_',' ',(string)$entityType));
}

function backupActionLabel($actionType) {
    $map = array('create'=>'created','update'=>'updated','delete'=>'deleted');
    return isset($map[$actionType]) ? $map[$actionType] : (string)$actionType;
}

function buildBackupSummary($entityType, $actionType, $before, $after, $entityId = null) {
    $label = backupEntityLabel($entityType, $before, $after, $entityId);
    $typeLabel = backupEntityTypeLabel($entityType);
    $actionLabel = backupActionLabel($actionType);
    return $label !== '' ? "$typeLabel $label $actionLabel" : "$typeLabel $actionLabel";
}

function writeBackupEntry($db, $entityType, $entityId, $actionType, $beforeData, $afterData, $changedBy) {
    ensureBackupTable($db);
    $bc = sanitizeBackupSnapshot($entityType, $beforeData);
    $ac = sanitizeBackupSnapshot($entityType, $afterData);
    $st = $db->prepare("
        INSERT INTO `backup_logs`
            (`entity_type`,`entity_id`,`entity_label`,`action_type`,`changed_by`,`changed_at`,`summary_text`,`before_data`,`after_data`)
        VALUES (:et,:eid,:el,:at,:cb,:ca,:st,:bd,:ad)
    ");
    $st->execute(array(
        ':et'  => (string)$entityType,
        ':eid' => $entityId !== null ? (string)$entityId : null,
        ':el'  => backupEntityLabel($entityType, $bc, $ac, $entityId) ?: null,
        ':at'  => (string)$actionType,
        ':cb'  => $changedBy ? (string)$changedBy : 'system',
        ':ca'  => appNow(),
        ':st'  => buildBackupSummary($entityType, $actionType, $bc, $ac, $entityId),
        ':bd'  => backupJsonEncode($bc),
        ':ad'  => backupJsonEncode($ac)
    ));
}

// ---------- 5) Auth token helpers ----------
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

function createAuthToken($user, $ttlSeconds = 28800) {
    $payload = array(
        'uid'      => (int)$user['id'],
        'username' => (string)$user['username'],
        'name'     => (string)$user['name'],
        'role'     => (string)$user['role'],
        'dept'     => isset($user['dept']) ? (string)$user['dept'] : '',
        'iat'      => time(),
        'exp'      => time() + max(300, (int)$ttlSeconds)
    );
    $body = base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $sig  = base64url_encode(hash_hmac('sha256', $body, APP_AUTH_SECRET, true));
    return $body . '.' . $sig;
}

function verifyAuthToken($token) {
    if (!$token || strpos($token, '.') === false) return null;
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    list($body, $sig) = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $body, APP_AUTH_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!is_array($payload)) return null;
    if (empty($payload['uid']) || empty($payload['username']) || empty($payload['role'])) return null;
    if (empty($payload['exp']) || time() > (int)$payload['exp']) return null;
    return $payload;
}

function getRequestHeadersSafe() {
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) return $h;
    }
    $h = array();
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $h[$name] = $v;
        }
    }
    return $h;
}

function getAuthTokenFromRequest() {
    $h = getRequestHeadersSafe();
    if (!empty($h['X-Auth-Token'])) return trim($h['X-Auth-Token']);
    if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    $auth = '';
    if (!empty($h['Authorization'])) $auth = $h['Authorization'];
    elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
    return '';
}

// ---------- 6) Request auth guards ----------
function getAuthUser($required = true) {
    $payload = verifyAuthToken(getAuthTokenFromRequest());
    if (!$payload && $required) err('Unauthorized', 401);
    return $payload;
}

function requireAuth()  { return getAuthUser(true); }

function requireAdmin() {
    $u = requireAuth();
    $role = isset($u['role']) ? $u['role'] : '';
    if ($role !== 'admin') err('Forbidden', 403);
    return $u;
}
