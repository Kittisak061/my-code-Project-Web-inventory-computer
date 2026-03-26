<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$m  = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

// All writable fields matching the new schema
$FIELDS = array(
  'fixed_asset_no','status',
  'ip','computer_name','username','workgroup','use_dhcp','mac',
  'type','pic','dept','location','year_purchased','age_years',
  'brand','model','cpu','cpu_speed','ram_mb','serial_no','service_tag',
  'hdd_gb','hdd_name','ssd_gb','ssd_name',
  'monitor_brand','monitor_model','monitor_spec','monitor_serial',
  'hw_id_cpu','hw_id_monitor','hw_id_lan','hw_id_wireless',
  'win_version','win_bit','win_license','win_key',
  'office_version','office_license','office_key',
  'av_name','av_version',
  'sw_hrm','sw_sap','sw_stock','sw_garoon','sw_team','sw_other',
  'access_internet','access_email',
  'email','logon','remark','updated_by'
);

function nextDeviceId($db) {
  $max = $db->query("SELECT MAX(CAST(SUBSTRING(`id`, 5) AS UNSIGNED)) FROM `devices` WHERE `id` LIKE 'DEV-%'")->fetchColumn();
  $next = ((int)$max) + 1;
  return 'DEV-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function buildDeviceFilters($src) {
  $w = array();
  $p = array();

  if (!empty($src['status'])) { $w[] = '`status`=:s';  $p[':s'] = $src['status']; }
  if (!empty($src['type']))   { $w[] = '`type`=:t';    $p[':t'] = $src['type'];   }
  if (!empty($src['dept']))   { $w[] = '`dept`=:d';    $p[':d'] = $src['dept'];   }
  if (!empty($src['q'])) {
    $q = '%' . $src['q'] . '%';
    $w[] = '(`computer_name` LIKE :q OR `username` LIKE :q2 OR `email` LIKE :q3 OR `ip` LIKE :q4 OR `dept` LIKE :q5 OR `id` LIKE :q6 OR `pic` LIKE :q7 OR `brand` LIKE :q8 OR `model` LIKE :q9 OR `serial_no` LIKE :q10 OR `fixed_asset_no` LIKE :q11)';
    for ($i=1;$i<=11;$i++) { $k = ($i===1)?':q':':q'.$i; $p[$k]=$q; }
  }

  return array($w, $p);
}

function fetchDevices($db, $src) {
  list($w, $p) = buildDeviceFilters($src);
  $sql = 'SELECT * FROM `devices`' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY `created_at` DESC';
  $st = $db->prepare($sql);
  $st->execute($p);
  return $st->fetchAll();
}

function isWindowsPlatform() {
  return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function isFunctionDisabled($name) {
  $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
  return in_array($name, $disabled, true);
}

function canUseFunctionSafe($name) {
  return function_exists($name) && !isFunctionDisabled($name);
}

function pickProbeIp($value) {
  $parts = preg_split('/[\s,;]+/', trim((string)$value));
  foreach ($parts as $part) {
    if (filter_var($part, FILTER_VALIDATE_IP)) {
      return $part;
    }
  }
  return '';
}

function pickProbeHost($value) {
  $host = trim((string)$value);
  if ($host === '') return '';
  return preg_replace('/[^A-Za-z0-9._-]/', '', $host);
}

function pickProbeCandidates($device) {
  $candidates = array();

  $host = pickProbeHost(isset($device['computer_name']) ? $device['computer_name'] : '');
  if ($host !== '') {
    $candidates[] = array(
      'target' => $host,
      'source' => !empty($device['workgroup']) ? 'computer_name (workgroup)' : 'computer_name',
      'priority' => 0
    );
  }

  return $candidates;
}

function buildPingCommand($target, $timeoutMs) {
  $timeoutMs = max(250, min(2500, (int)$timeoutMs));
  if (isWindowsPlatform()) {
    return 'ping -n 1 -w ' . $timeoutMs . ' ' . escapeshellarg($target);
  }
  $timeoutSec = max(1, (int)ceil($timeoutMs / 1000));
  return 'ping -c 1 -W ' . $timeoutSec . ' ' . escapeshellarg($target);
}

function pingOutputShowsSuccess($output) {
  $text = strtolower((string)$output);
  if (strpos($text, 'ttl=') !== false) return true;
  if (strpos($text, 'bytes from') !== false) return true;
  if (preg_match('/received = [1-9]/i', $text)) return true;
  if (preg_match('/[1-9]\s+received/i', $text)) return true;
  if (strpos($text, '0% packet loss') !== false) return true;
  return false;
}

function analyzePingOutput($output) {
  $text = strtolower((string)$output);

  if (pingOutputShowsSuccess($output)) {
    return array('state' => 'on', 'message' => 'Ping replied');
  }

  $nameErrors = array(
    'could not find host',
    'name or service not known',
    'temporary failure in name resolution',
    'unknown host',
    'could not resolve target system name'
  );
  foreach ($nameErrors as $needle) {
    if (strpos($text, $needle) !== false) {
      return array('state' => 'unknown', 'message' => 'Computer name not resolved');
    }
  }

  return array('state' => 'off', 'message' => 'No reply');
}

function buildPowerResult($device, $meta, $state, $elapsedMs, $message) {
  return array(
    'id' => isset($device['id']) ? $device['id'] : null,
    'computer_name' => isset($device['computer_name']) ? $device['computer_name'] : null,
    'status' => isset($device['status']) ? $device['status'] : null,
    'type' => isset($device['type']) ? $device['type'] : null,
    'dept' => isset($device['dept']) ? $device['dept'] : null,
    'power_state' => $state,
    'target' => $meta ? $meta['target'] : null,
    'target_source' => $meta ? $meta['source'] : null,
    'candidate_priority' => $meta && isset($meta['priority']) ? (int)$meta['priority'] : 99,
    'elapsed_ms' => (int)$elapsedMs,
    'message' => $message
  );
}

function pickBestPowerResult($device, $results) {
  if (empty($results)) {
    return buildPowerResult($device, null, 'unknown', 0, 'No probe candidates');
  }

  usort($results, function($a, $b) {
    $pa = isset($a['candidate_priority']) ? (int)$a['candidate_priority'] : 99;
    $pb = isset($b['candidate_priority']) ? (int)$b['candidate_priority'] : 99;
    if ($pa !== $pb) return $pa - $pb;
    return ((int)$a['elapsed_ms']) - ((int)$b['elapsed_ms']);
  });

  foreach ($results as $result) {
    if (isset($result['power_state']) && $result['power_state'] === 'on') {
      return $result;
    }
  }

  return $results[0];
}

function probeDevicesSequential($jobs, $timeoutMs) {
  $results = array();

  foreach ($jobs as $job) {
    $meta = $job['meta'];
    $device = $job['device'];
    $command = buildPingCommand($meta['target'], $timeoutMs);
    $started = microtime(true);
    $output = '';

    if (canUseFunctionSafe('exec')) {
      $lines = array();
      @exec($command . ' 2>&1', $lines, $exitCode);
      $output = implode("\n", $lines);
    } else {
      $results[] = buildPowerResult($device, $meta, 'unknown', 0, 'Shell execution is disabled');
      continue;
    }

    $elapsedMs = round((microtime(true) - $started) * 1000);
    $analysis = analyzePingOutput($output);
    $results[] = buildPowerResult($device, $meta, $analysis['state'], $elapsedMs, $analysis['message']);
  }

  return $results;
}

function startProbeProcess($job, $timeoutMs) {
  if (!canUseFunctionSafe('proc_open')) return null;

  $descriptors = array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
    2 => array('pipe', 'w')
  );

  $process = @proc_open(buildPingCommand($job['meta']['target'], $timeoutMs), $descriptors, $pipes);
  if (!is_resource($process)) return null;

  if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
  if (isset($pipes[1]) && is_resource($pipes[1])) stream_set_blocking($pipes[1], false);
  if (isset($pipes[2]) && is_resource($pipes[2])) stream_set_blocking($pipes[2], false);

  return array(
    'process' => $process,
    'pipes' => $pipes,
    'job' => $job,
    'started_at' => microtime(true),
    'timeout_ms' => $timeoutMs
  );
}

function finishProbeProcess($active, $timedOut) {
  $output = '';
  if (isset($active['pipes'][1]) && is_resource($active['pipes'][1])) {
    $output .= stream_get_contents($active['pipes'][1]);
    fclose($active['pipes'][1]);
  }
  if (isset($active['pipes'][2]) && is_resource($active['pipes'][2])) {
    $output .= "\n" . stream_get_contents($active['pipes'][2]);
    fclose($active['pipes'][2]);
  }

  if ($timedOut && canUseFunctionSafe('proc_terminate')) {
    @proc_terminate($active['process']);
  }

  $elapsedMs = round((microtime(true) - $active['started_at']) * 1000);
  @proc_close($active['process']);

  $analysis = analyzePingOutput($output);
  $state = $analysis['state'];
  $message = $analysis['message'];
  if ($timedOut && $state !== 'on') {
    $state = 'unknown';
    $message = 'Probe timed out';
  }

  return buildPowerResult($active['job']['device'], $active['job']['meta'], $state, $elapsedMs, $message);
}

function probeDevicesConcurrent($jobs, $timeoutMs, $maxConcurrent) {
  $results = array();
  $queue = array_values($jobs);
  $active = array();
  $maxConcurrent = max(1, (int)$maxConcurrent);

  while (!empty($queue) || !empty($active)) {
    while (!empty($queue) && count($active) < $maxConcurrent) {
      $next = array_shift($queue);
      $started = startProbeProcess($next, $timeoutMs);
      if ($started === null) {
        return array_merge($results, probeDevicesSequential(array_merge(array($next), $queue), $timeoutMs));
      }
      $active[] = $started;
    }

    usleep(50000);

    for ($i = count($active) - 1; $i >= 0; $i--) {
      $status = @proc_get_status($active[$i]['process']);
      $elapsedMs = (microtime(true) - $active[$i]['started_at']) * 1000;
      $timedOut = $elapsedMs > max(($active[$i]['timeout_ms'] + 900), 1500);
      if (!$status['running'] || $timedOut) {
        $results[] = finishProbeProcess($active[$i], $timedOut);
        array_splice($active, $i, 1);
      }
    }
  }

  return $results;
}

function summarizePowerResults($items) {
  $summary = array('on' => 0, 'off' => 0, 'unknown' => 0);
  foreach ($items as $item) {
    $state = isset($item['power_state']) ? $item['power_state'] : 'unknown';
    if (!isset($summary[$state])) $summary[$state] = 0;
    $summary[$state]++;
  }
  return $summary;
}

function buildPowerDiagnosticHint($summary, $total) {
  $on = isset($summary['on']) ? (int)$summary['on'] : 0;
  $unknown = isset($summary['unknown']) ? (int)$summary['unknown'] : 0;
  if ($total >= 20 && $unknown >= (int)floor($total * 0.7) && $on <= 1) {
    return 'เครื่องที่รันระบบนี้ยัง resolve ชื่อเครื่องส่วนใหญ่ไม่ได้ ควรตรวจ LAN, internal DNS หรือ Network Discovery ของ Windows';
  }
  return '';
}

function buildPowerCacheKey($devices) {
  $parts = array();
  foreach ((array)$devices as $device) {
    $parts[] = (string)(isset($device['id']) ? $device['id'] : '')
      . '|'
      . (string)(isset($device['computer_name']) ? $device['computer_name'] : '')
      . '|'
      . (string)(isset($device['status']) ? $device['status'] : '');
  }
  return md5(implode("\n", $parts));
}

function getPowerCachePath($cacheKey) {
  $dir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  return $dir . DIRECTORY_SEPARATOR . 'devicehub_power_' . $cacheKey . '.json';
}

function loadPowerCache($cacheKey, $maxAgeSeconds) {
  $path = getPowerCachePath($cacheKey);
  if (!is_file($path)) return null;
  if ((time() - (int)@filemtime($path)) > max(5, (int)$maxAgeSeconds)) return null;
  $raw = @file_get_contents($path);
  if ($raw === false || $raw === '') return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function savePowerCache($cacheKey, $payload) {
  $path = getPowerCachePath($cacheKey);
  @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function handlePowerProbe($db, $srcDevices) {
  $devices = is_array($srcDevices) ? $srcDevices : array();
  $jobs = array();
  $results = array();
  $byDevice = array();
  $cacheKey = buildPowerCacheKey($devices);
  $forceFresh = !empty($_GET['force']) || !empty($_POST['force']);

  if (!$forceFresh) {
    $cached = loadPowerCache($cacheKey, 75);
    if (is_array($cached) && isset($cached['items']) && isset($cached['summary'])) {
      ok($cached, 200);
    }
  }

  foreach ($devices as $device) {
    $candidates = pickProbeCandidates($device);
    if (empty($candidates)) {
      $results[] = buildPowerResult($device, null, 'unknown', 0, 'No computer name');
      continue;
    }

    foreach ($candidates as $meta) {
      $jobs[] = array('device' => $device, 'meta' => $meta);
    }
  }

  if (!empty($jobs)) {
    $candidateResults = probeDevicesConcurrent($jobs, 650, 96);
    foreach ($candidateResults as $item) {
      $deviceId = isset($item['id']) ? (string)$item['id'] : '';
      if ($deviceId === '') continue;
      if (!isset($byDevice[$deviceId])) $byDevice[$deviceId] = array();
      $byDevice[$deviceId][] = $item;
    }

    foreach ($devices as $device) {
      $deviceId = isset($device['id']) ? (string)$device['id'] : '';
      if ($deviceId === '' || empty($byDevice[$deviceId])) continue;
      $results[] = pickBestPowerResult($device, $byDevice[$deviceId]);
    }
  }

  $summary = summarizePowerResults($results);
  $payload = array(
    'items' => $results,
    'summary' => $summary,
    'hint' => buildPowerDiagnosticHint($summary, count($results)),
    'checked_at' => gmdate('c')
  );
  savePowerCache($cacheKey, $payload);
  ok($payload, 200);
}

if ($m === 'GET') {
  requireAuth();
  if (!empty($_GET['action']) && $_GET['action'] === 'power') {
    handlePowerProbe($db, fetchDevices($db, $_GET));
  }
  ok(fetchDevices($db, $_GET), 200);
}

if ($m === 'POST') {
  $b = body();
  if ((!empty($_GET['action']) && $_GET['action'] === 'power') || (!empty($b['action']) && $b['action'] === 'power')) {
    requireAuth();
    $devices = isset($b['devices']) && is_array($b['devices']) ? $b['devices'] : fetchDevices($db, $b);
    handlePowerProbe($db, $devices);
  }
  $auth = requireAdmin();
  $b = normalizePayloadFields($b, $FIELDS);
  if (empty($b['computer_name']) && empty($b['name'])) err('Please provide computer_name', 400);
  $nid = nextDeviceId($db);
  // Build insert
  $cols = array('`id`'); $vals = array(':id'); $p = array(':id' => $nid);
  foreach ($FIELDS as $f) {
    if (array_key_exists($f, $b)) {
      $cols[] = "`$f`"; $vals[] = ":$f";
      $p[":$f"] = $b[$f];
    }
  }
  if (!isset($p[':updated_by']) || !$p[':updated_by']) {
    $cols[] = '`updated_by`';
    $vals[] = ':updated_by';
    $p[':updated_by'] = isset($auth['username']) ? $auth['username'] : 'system';
  }
  $sql = 'INSERT INTO `devices`(' . implode(',', $cols) . ') VALUES(' . implode(',', $vals) . ')';
  $db->prepare($sql)->execute($p);
  $st = $db->prepare('SELECT * FROM `devices` WHERE `id`=?'); $st->execute(array($nid));
  ok($st->fetch(), 201);
}

if ($m === 'PUT') {
  $auth = requireAdmin();
  if (!$id) err('Missing id', 400);
  $b = normalizePayloadFields(body(), $FIELDS); $sets = array(); $p = array(':id' => $id);
  foreach ($FIELDS as $f) {
    if (array_key_exists($f, $b)) {
      $sets[] = "`$f`=:$f";
      $p[":$f"] = $b[$f];
    }
  }
  if (!array_key_exists('updated_by', $b)) {
    $sets[] = '`updated_by`=:updated_by';
    $p[':updated_by'] = isset($auth['username']) ? $auth['username'] : 'system';
  }
  if (empty($sets)) err('No data to update', 400);
  $db->prepare('UPDATE `devices` SET ' . implode(',', $sets) . ' WHERE `id`=:id')->execute($p);
  $st = $db->prepare('SELECT * FROM `devices` WHERE `id`=?'); $st->execute(array($id));
  ok($st->fetch(), 200);
}

if ($m === 'DELETE') {
  requireAdmin();
  if (!$id) err('Missing id', 400);
  $st = $db->prepare('DELETE FROM `devices` WHERE `id`=?'); $st->execute(array($id));
  if (!$st->rowCount()) err('Not found', 404);
  ok(array('success' => true, 'id' => $id), 200);
}

err('Method not allowed', 405);
