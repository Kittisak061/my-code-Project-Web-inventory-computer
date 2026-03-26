<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '12345678');
define('DB_NAME', 'devicehub');
define('DB_CHARSET', 'utf8mb4');
define('APP_AUTH_SECRET', 'devicehub_local_2026_auth_secret_change_me');

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

function getDB() {
    static $p = null;
    if ($p === null) {
        try {
            $p = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
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

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function createAuthToken($user, $ttlSeconds = 28800) {
    $payload = array(
        'uid' => (int)$user['id'],
        'username' => (string)$user['username'],
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
        'dept' => isset($user['dept']) ? (string)$user['dept'] : '',
        'iat' => time(),
        'exp' => time() + max(300, (int)$ttlSeconds)
    );
    $body = base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $sig = base64url_encode(hash_hmac('sha256', $body, APP_AUTH_SECRET, true));
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
        $headers = getallheaders();
        if (is_array($headers)) return $headers;
    }

    $headers = array();
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
    return $headers;
}

function getAuthTokenFromRequest() {
    $headers = getRequestHeadersSafe();

    if (!empty($headers['X-Auth-Token'])) return trim($headers['X-Auth-Token']);
    if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) return trim($_SERVER['HTTP_X_AUTH_TOKEN']);

    $auth = '';
    if (!empty($headers['Authorization'])) $auth = $headers['Authorization'];
    elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];

    if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }

    return '';
}

function getAuthUser($required = true) {
    $payload = verifyAuthToken(getAuthTokenFromRequest());
    if (!$payload && $required) err('Unauthorized', 401);
    return $payload;
}

function requireAuth() {
    return getAuthUser(true);
}

function requireAdmin() {
    $u = requireAuth();
    $role = isset($u['role']) ? $u['role'] : '';
    if ($role !== 'admin') err('Forbidden', 403);
    return $u;
}
