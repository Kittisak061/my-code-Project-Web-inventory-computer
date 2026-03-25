var importedData = [];
var IMPORT_SAVE_FIELDS = FIDS.concat(CHKS);
var IMPORT_HINTS = ['computer name', 'ip address', 'type', 'department', 'windows', 'office', 'antivirus'];
var LEGACY_DEVICE_TEMPLATE_COLUMNS = {
  ip: 1,
  computer_name: 2,
  username: 3,
  workgroup: 4,
  use_dhcp: 5,
  type: 6,
  pic: 8,
  dept: 9,
  location_site: 10,
  location_room: 11,
  year_purchased: 12,
  brand: 14,
  model: 15,
  cpu: 16,
  cpu_speed: 17,
  ram_mb: 18,
  hdd_gb: 19,
  hdd_name: 20,
  ssd_gb: 21,
  ssd_name: 22,
  monitor_brand: 23,
  monitor_model: 24,
  monitor_spec: 25,
  monitor_serial: 26,
  serial_no: 27,
  service_tag: 28,
  fixed_asset_no: 29,
  mac: 31,
  win_version: 33,
  win_bit: 34,
  win_license: 35,
  win_key: 36,
  office_version: 37,
  office_license: 38,
  office_key: 39,
  av_name: 40,
  av_version: 41,
  sw_hrm: 42,
  sw_sap: 43,
  sw_stock: 44,
  sw_garoon: 45,
  sw_team: 46,
  sw_other: 47,
  access_internet: 48,
  access_email: 49,
  email_or_logon: 50,
  remark: 53
};
var IMPORT_FIELD_CONFIG = [
  { field: 'monitor_serial', aliases: ['monitor serial', 'monitor serial no', 'monitor serial number'] },
  { field: 'serial_no', aliases: ['device serial number', 'hardware serial number', 'serial no', 'serial number'] },
  { field: 'service_tag', aliases: ['service tag'] },
  { field: 'fixed_asset_no', aliases: ['fixed asset', 'fixed asset no', 'fixed asset number', 'fixed_asset_no', 'asset no', 'fa'] },
  { field: 'ip', aliases: ['ip', 'ip address'] },
  { field: 'computer_name', aliases: ['computer name', 'computer_name', 'device name', 'hostname'] },
  { field: 'username', aliases: ['user name', 'user_name', 'login user'] },
  { field: 'workgroup', aliases: ['workgroup', 'domain', 'workgroup domain', 'workgroup / domain'] },
  { field: 'use_dhcp', aliases: ['dhcp', 'use dhcp'] },
  { field: 'type', aliases: ['type', 'device type'] },
  { field: 'pic', aliases: ['pic', 'owner', 'responsible person'] },
  { field: 'dept', aliases: ['department', 'dept'] },
  { field: 'location', aliases: ['location', 'room location', 'device location'] },
  { field: 'year_purchased', aliases: ['year', 'year purchased', 'year of purchased', 'purchase year'] },
  { field: 'brand', aliases: ['brand'] },
  { field: 'model', aliases: ['model'] },
  { field: 'cpu', aliases: ['cpu', 'processor'] },
  { field: 'cpu_speed', aliases: ['speed(ghz)', 'speed ghz', 'cpu speed', 'cpu speed ghz'] },
  { field: 'ram_mb', aliases: ['ram(mb)', 'ram mb', 'ram'] },
  { field: 'hdd_gb', aliases: ['hdd(gb)', 'hdd gb', 'hdd size', 'hdd'] },
  { field: 'hdd_name', aliases: ['hdd name', 'hdd hardware name', 'hdd hardware'] },
  { field: 'ssd_gb', aliases: ['ssd(gb)', 'ssd gb', 'ssd size', 'ssd'] },
  { field: 'ssd_name', aliases: ['ssd name', 'ssd hardware name', 'ssd hardware'] },
  { field: 'monitor_brand', aliases: ['monitor brand', 'monitor'] },
  { field: 'monitor_model', aliases: ['monitor model'] },
  { field: 'monitor_spec', aliases: ['monitor spec', 'monitor specification'] },
  { field: 'mac', aliases: ['mac address', 'mac'] },
  { field: 'win_version', aliases: ['windows version', 'win version', 'windows'] },
  { field: 'win_bit', aliases: ['windows bit', 'win bit', 'bit'] },
  { field: 'win_license', aliases: ['windows license type', 'windows license', 'win license type', 'win license'] },
  { field: 'win_key', aliases: ['windows license key', 'windows key', 'win key'] },
  { field: 'office_version', aliases: ['office version', 'office'] },
  { field: 'office_license', aliases: ['office license type', 'office license'] },
  { field: 'office_key', aliases: ['office license key', 'office key'] },
  { field: 'av_name', aliases: ['antivirus name', 'antivirus', 'av name'] },
  { field: 'av_version', aliases: ['antivirus version', 'av version'] },
  { field: 'sw_hrm', aliases: ['hrm'] },
  { field: 'sw_sap', aliases: ['sap'] },
  { field: 'sw_stock', aliases: ['stock'] },
  { field: 'sw_garoon', aliases: ['garoon'] },
  { field: 'sw_team', aliases: ['team', 'teams', 'microsoft teams'] },
  { field: 'software', aliases: ['software', 'installed software', 'programs'] },
  { field: 'sw_other', aliases: ['sw other', 'other software'] },
  { field: 'access_internet', aliases: ['access internet', 'internet access', 'access permission internet', 'internet'] },
  { field: 'access_email', aliases: ['access email', 'email access', 'access permission email', 'email'] },
  { field: 'email', aliases: ['email address logon', 'email address', 'mail address', 'email account'] },
  { field: 'logon', aliases: ['logon account', 'logon', 'login'] },
  { field: 'status', aliases: ['status'] },
  { field: 'remark', aliases: ['remark', 'note', 'comment'] }
];
var IMPORT_TYPE_MAP = {
  'desktop pc': 'Desktop PC',
  'desktop': 'Desktop PC',
  'pc': 'Desktop PC',
  'work station': 'Desktop PC',
  'workstation': 'Desktop PC',
  'laptop': 'Laptop',
  'notebook': 'Laptop',
  'nb': 'Laptop',
  'server': 'Server',
  'tablet': 'Tablet',
  'printer': 'Printer',
  'projector': 'Other',
  'other': 'Other'
};
var IMPORT_STATUS_MAP = {
  'online': 'online',
  'offline': 'offline',
  'maintenance': 'maintenance',
  'maintainance': 'maintenance',
  'repair': 'maintenance',
  'in repair': 'maintenance'
};
var IMPORT_BIT_MAP = {
  '32': '32',
  '32 bit': '32',
  '32-bit': '32',
  'x86': '32',
  '64': '64',
  '64 bit': '64',
  '64-bit': '64',
  'x64': '64'
};
var IMPORT_WIN_LICENSE_MAP = {
  'oem': 'OEM',
  'retail': 'Retail',
  'volume': 'Volume',
  'volume licensing': 'Volume',
  'mak': 'MAK',
  'kms': 'KMS'
};
var IMPORT_OFFICE_LICENSE_MAP = {
  'oem': 'OEM',
  'retail': 'Retail',
  'full package products': 'Retail',
  'volume': 'Volume',
  'volume licensing': 'Volume',
  'mak': 'MAK',
  'kms': 'KMS',
  'o365': 'O365',
  'office 365': 'O365',
  'microsoft 365': 'O365'
};

function normalizeImportHeader(value) {
  return String(value === null || value === undefined ? '' : value)
    .toLowerCase()
    .replace(/[_\/()-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizeImportChoice(value, map) {
  var key = normalizeImportHeader(value);
  return key ? (map[key] || null) : null;
}

function normalizeImportType(value) {
  var key = normalizeImportHeader(value);
  if (!key) return null;
  if (IMPORT_TYPE_MAP[key]) return IMPORT_TYPE_MAP[key];
  if (/^pc\b/.test(key)) return 'Desktop PC';
  if (/^work\s*station\b/.test(key)) return 'Desktop PC';
  return null;
}

function normalizeImportBit(value) {
  var key = normalizeImportHeader(value);
  if (!key) return null;
  if (IMPORT_BIT_MAP[key]) return IMPORT_BIT_MAP[key];

  var parsed = parseInt(key, 10);
  if (!isNaN(parsed)) {
    if (parsed >= 64) return '64';
    if (parsed > 0 && parsed <= 32) return '32';
  }

  return null;
}

function deriveImportStatus(statusValue, remarkValue) {
  var explicitStatus = normalizeImportChoice(statusValue, IMPORT_STATUS_MAP);
  if (explicitStatus) return explicitStatus;

  var remark = normalizeImportHeader(remarkValue);
  if (!remark) return null;
  if (remark.indexOf('offline') !== -1) return 'offline';
  if (remark.indexOf('os end of life') !== -1) return 'maintenance';
  if (remark.indexOf('รอ') !== -1) return 'maintenance';
  if (remark.indexOf('เก็บคืน') !== -1) return 'maintenance';
  return null;
}

function mergeLegacyLocation(site, room) {
  var siteText = cleanImportValue(site);
  var roomText = cleanImportValue(room);
  if (siteText && roomText) {
    var siteKey = normalizeImportHeader(siteText);
    var roomKey = normalizeImportHeader(roomText);
    if (roomKey.indexOf(siteKey) !== -1) return roomText;
    return siteText + ' ' + roomText;
  }
  return roomText || siteText || '';
}

function normalizeImportType(value) {
  var key = normalizeImportHeader(value);
  if (!key) return null;
  if (IMPORT_TYPE_MAP[key]) return IMPORT_TYPE_MAP[key];
  if (/^pc\b/.test(key)) return 'Desktop PC';
  if (/^work\s*station\b/.test(key)) return 'Desktop PC';
  return null;
}

function normalizeImportBit(value) {
  var key = normalizeImportHeader(value);
  if (!key) return null;
  if (IMPORT_BIT_MAP[key]) return IMPORT_BIT_MAP[key];

  var parsed = parseInt(key, 10);
  if (!isNaN(parsed)) {
    if (parsed >= 64) return '64';
    if (parsed > 0 && parsed <= 32) return '32';
  }

  return null;
}

function cleanImportValue(value) {
  if (value === null || value === undefined) return '';
  var text = String(value).trim();
  var normalized = normalizeImportHeader(text);
  if (!normalized) return '';
  if (normalized === '-' || normalized === 'n a' || normalized === 'na' || normalized === 'none') return '';
  return text;
}

function parseImportBool(value) {
  if (value === null || value === undefined || value === '') return 0;
  if (typeof value === 'boolean') return value ? 1 : 0;
  if (typeof value === 'number') return value ? 1 : 0;
  var raw = String(value).trim();
  if (!raw) return 0;
  if (/[✓✔☑✅]/.test(raw)) return 1;
  if (['1', 'y', 'yes', 'true', 'enabled', 'enable', 'on', 'allow', 'allowed', 'dhcp', 'p'].indexOf(normalizeImportHeader(raw)) !== -1) return 1;
  return 0;
}

function parseImportNumber(value, useFloat) {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'number') return isNaN(value) ? null : value;
  var raw = String(value).replace(/,/g, '').trim();
  if (!raw) return null;
  var parsed = useFloat ? parseFloat(raw) : parseInt(raw, 10);
  return isNaN(parsed) ? null : parsed;
}

function looksLikeEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
}

function parseSoftwareImport(device, value) {
  var raw = cleanImportValue(value);
  if (!raw) return;
  var known = {
    hrm: 'sw_hrm',
    sap: 'sw_sap',
    stock: 'sw_stock',
    garoon: 'sw_garoon',
    team: 'sw_team',
    teams: 'sw_team',
    'ms team': 'sw_team',
    'microsoft teams': 'sw_team'
  };
  var other = [];

  raw.split(/[\n,;/]+/).map(function(part) {
    return part.trim();
  }).filter(Boolean).forEach(function(part) {
    var normalized = normalizeImportHeader(part);
    if (known[normalized]) {
      device[known[normalized]] = 1;
    } else {
      other.push(part);
    }
  });

  if (other.length) {
    device.sw_other = other.join(', ');
  }
}

function applyImportField(device, field, value) {
  if (value === null || value === undefined || value === '') return;

  if (field === 'use_dhcp' || field === 'access_internet' || field === 'access_email' ||
      field === 'sw_hrm' || field === 'sw_sap' || field === 'sw_stock' || field === 'sw_garoon' || field === 'sw_team') {
    device[field] = parseImportBool(value);
    return;
  }

  if (field === 'cpu_speed') {
    device[field] = parseImportNumber(value, true);
    return;
  }

  if (field === 'ram_mb' || field === 'hdd_gb' || field === 'ssd_gb' || field === 'year_purchased') {
    device[field] = parseImportNumber(value, false);
    return;
  }

  if (field === 'win_bit') {
    device[field] = normalizeImportBit(value) || cleanImportValue(value);
    return;
  }

  if (field === 'type') {
    device[field] = normalizeImportType(value) || cleanImportValue(value);
    return;
  }

  if (field === 'status') {
    device[field] = deriveImportStatus(value, device.remark) || normalizeImportHeader(value);
    return;
  }

  if (field === 'win_license') {
    device[field] = normalizeImportChoice(value, IMPORT_WIN_LICENSE_MAP) || cleanImportValue(value);
    return;
  }

  if (field === 'office_license') {
    device[field] = normalizeImportChoice(value, IMPORT_OFFICE_LICENSE_MAP) || cleanImportValue(value);
    return;
  }

  if (field === 'software') {
    parseSoftwareImport(device, value);
    return;
  }

  var text = cleanImportValue(value);
  if (!text) return;
  device[field] = text;
}

function scoreHeaderForAlias(header, alias) {
  if (!header || !alias) return 0;
  if (header === alias) return 300;
  if (header.indexOf(alias) !== -1) return 200 + alias.length;
  return 0;
}

function buildImportColumnMap(headers) {
  var normalizedHeaders = headers.map(normalizeImportHeader);
  var colMap = {};
  var usedColumns = {};

  IMPORT_FIELD_CONFIG.forEach(function(cfg) {
    var bestIndex = -1;
    var bestScore = 0;
    var aliases = cfg.aliases.map(normalizeImportHeader);

    for (var i = 0; i < normalizedHeaders.length; i++) {
      if (usedColumns[i]) continue;
      var header = normalizedHeaders[i];
      if (!header) continue;

      for (var j = 0; j < aliases.length; j++) {
        var score = scoreHeaderForAlias(header, aliases[j]);
        if (score > bestScore) {
          bestScore = score;
          bestIndex = i;
        }
      }
    }

    if (bestIndex !== -1) {
      colMap[cfg.field] = bestIndex;
      usedColumns[bestIndex] = true;
    }
  });

  return colMap;
}

function findExistingDeviceForImport(device, sourceData) {
  var list = sourceData || data || [];
  var id = String(device.id || '').trim().toLowerCase();
  var fixedAsset = String(device.fixed_asset_no || '').trim().toLowerCase();
  var computerName = String(device.computer_name || '').trim().toLowerCase();

  for (var i = 0; i < list.length; i++) {
    var item = list[i];
    if (id && String(item.id || '').trim().toLowerCase() === id) return item;
    if (fixedAsset && String(item.fixed_asset_no || '').trim().toLowerCase() === fixedAsset) return item;
    if (computerName && String(item.computer_name || '').trim().toLowerCase() === computerName) return item;
  }
  return null;
}

function finalizeImportedDevice(device) {
  var existing = findExistingDeviceForImport(device);
  device._existingId = existing ? existing.id : null;
  device._importAction = existing ? 'update' : 'create';
  return device;
}

function buildImportedDevice(row, rowNumber, colMap) {
  var device = { row_num: rowNumber };

  for (var field in colMap) {
    applyImportField(device, field, row[colMap[field]]);
  }

  if (!device.status) {
    device.status = deriveImportStatus('', device.remark) || 'online';
  }

  return finalizeImportedDevice(device);
}

function rowHasContent(row) {
  return row && row.some(function(cell) {
    return cleanImportValue(cell) !== '';
  });
}

function isLegacyDeviceTemplate(rows) {
  if (!rows || rows.length < 5) return false;
  var row2 = (rows[1] || []).map(normalizeImportHeader);
  var row3 = (rows[2] || []).map(normalizeImportHeader);
  return row2.indexOf('network information') !== -1 &&
    row2.indexOf('physical information') !== -1 &&
    row3.indexOf('computer name') !== -1 &&
    row3.indexOf('ip address') !== -1;
}

function scoreGenericSheet(rows) {
  var best = 0;
  for (var i = 0; i < Math.min(rows.length, 10); i++) {
    var text = normalizeImportHeader((rows[i] || []).join(' '));
    var score = 0;
    for (var j = 0; j < IMPORT_HINTS.length; j++) {
      if (text.indexOf(IMPORT_HINTS[j]) !== -1) score++;
    }
    if (score > best) best = score;
  }
  return best;
}

function pickImportSheet(book) {
  var best = null;

  book.SheetNames.forEach(function(name) {
    var sheet = book.Sheets[name];
    var rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
    var score = isLegacyDeviceTemplate(rows) ? 100 : scoreGenericSheet(rows);
    if (!best || score > best.score) {
      best = { name: name, rows: rows, score: score, legacy: score === 100 };
    }
  });

  return best;
}

function buildLegacyTemplateDevices(rows) {
  var devices = [];
  for (var i = 4; i < rows.length; i++) {
    var row = rows[i];
    if (!rowHasContent(row)) continue;

    var device = { row_num: i + 1 };
    applyImportField(device, 'ip', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.ip]);
    applyImportField(device, 'computer_name', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.computer_name]);
    applyImportField(device, 'username', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.username]);
    applyImportField(device, 'workgroup', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.workgroup]);
    applyImportField(device, 'use_dhcp', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.use_dhcp]);
    applyImportField(device, 'type', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.type]);
    applyImportField(device, 'pic', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.pic]);
    applyImportField(device, 'dept', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.dept]);
    applyImportField(device, 'location', mergeLegacyLocation(row[LEGACY_DEVICE_TEMPLATE_COLUMNS.location_site], row[LEGACY_DEVICE_TEMPLATE_COLUMNS.location_room]));
    applyImportField(device, 'year_purchased', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.year_purchased]);
    applyImportField(device, 'brand', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.brand]);
    applyImportField(device, 'model', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.model]);
    applyImportField(device, 'cpu', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.cpu]);
    applyImportField(device, 'cpu_speed', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.cpu_speed]);
    applyImportField(device, 'ram_mb', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.ram_mb]);
    applyImportField(device, 'hdd_gb', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.hdd_gb]);
    applyImportField(device, 'hdd_name', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.hdd_name]);
    applyImportField(device, 'ssd_gb', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.ssd_gb]);
    applyImportField(device, 'ssd_name', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.ssd_name]);
    applyImportField(device, 'monitor_brand', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.monitor_brand]);
    applyImportField(device, 'monitor_model', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.monitor_model]);
    applyImportField(device, 'monitor_spec', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.monitor_spec]);
    applyImportField(device, 'monitor_serial', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.monitor_serial]);
    applyImportField(device, 'serial_no', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.serial_no]);
    applyImportField(device, 'service_tag', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.service_tag]);
    applyImportField(device, 'fixed_asset_no', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.fixed_asset_no]);
    applyImportField(device, 'mac', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.mac]);
    applyImportField(device, 'win_version', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.win_version]);
    applyImportField(device, 'win_bit', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.win_bit]);
    applyImportField(device, 'win_license', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.win_license]);
    applyImportField(device, 'win_key', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.win_key]);
    applyImportField(device, 'office_version', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.office_version]);
    applyImportField(device, 'office_license', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.office_license]);
    applyImportField(device, 'office_key', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.office_key]);
    applyImportField(device, 'av_name', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.av_name]);
    applyImportField(device, 'av_version', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.av_version]);
    applyImportField(device, 'sw_hrm', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_hrm]);
    applyImportField(device, 'sw_sap', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_sap]);
    applyImportField(device, 'sw_stock', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_stock]);
    applyImportField(device, 'sw_garoon', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_garoon]);
    applyImportField(device, 'sw_team', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_team]);
    applyImportField(device, 'sw_other', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.sw_other]);
    applyImportField(device, 'access_internet', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.access_internet]);
    applyImportField(device, 'access_email', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.access_email]);
    applyImportField(device, 'remark', row[LEGACY_DEVICE_TEMPLATE_COLUMNS.remark]);
    if (!device.status) {
      device.status = deriveImportStatus('', device.remark) || 'online';
    }

    var emailOrLogon = cleanImportValue(row[LEGACY_DEVICE_TEMPLATE_COLUMNS.email_or_logon]);
    if (looksLikeEmail(emailOrLogon)) {
      device.email = emailOrLogon;
      device.logon = emailOrLogon;
    } else if (emailOrLogon) {
      device.logon = emailOrLogon;
    }

    devices.push(finalizeImportedDevice(device));
  }
  return devices;
}

function findHeaderRowIndex(rows) {
  var bestIndex = -1;
  var bestScore = 0;

  for (var i = 0; i < Math.min(rows.length, 10); i++) {
    var text = normalizeImportHeader((rows[i] || []).join(' '));
    var score = 0;
    for (var j = 0; j < IMPORT_HINTS.length; j++) {
      if (text.indexOf(IMPORT_HINTS[j]) !== -1) score++;
    }
    if (score > bestScore) {
      bestScore = score;
      bestIndex = i;
    }
  }

  if (bestIndex !== -1) return bestIndex;

  for (var k = 0; k < rows.length; k++) {
    if (rowHasContent(rows[k])) return k;
  }

  return -1;
}

function buildGenericImportTable(rows) {
  var headerIndex = findHeaderRowIndex(rows);
  if (headerIndex === -1) return null;

  var headers = rows[headerIndex] || [];
  var content = rows.slice(headerIndex + 1).filter(rowHasContent);
  if (!content.length) return null;

  return {
    headers: headers,
    content: content,
    rowOffset: headerIndex + 2
  };
}

function validateImportedDevice(device, seenTargets) {
  var errors = [];
  var targetKey = device._existingId
    ? 'update:' + device._existingId
    : 'create:' + String(device.computer_name || device.fixed_asset_no || device.id || ('row-' + device.row_num)).trim().toLowerCase();

  if (!device._existingId && !String(device.computer_name || '').trim()) {
    errors.push('Missing Computer Name for a new record');
  }
  if (device.type && normalizeImportType(device.type) !== device.type) {
    errors.push('Type is invalid');
  }
  if (device.status && !normalizeImportChoice(device.status, IMPORT_STATUS_MAP) && IMPORT_STATUS_MAP[normalizeImportHeader(device.status)] !== device.status) {
    errors.push('Status is invalid');
  }
  if (device.win_bit && normalizeImportBit(device.win_bit) !== device.win_bit) {
    errors.push('Win Bit must be 32 or 64');
  }
  if (device.win_license && !normalizeImportChoice(device.win_license, IMPORT_WIN_LICENSE_MAP) && IMPORT_WIN_LICENSE_MAP[normalizeImportHeader(device.win_license)] !== device.win_license) {
    errors.push('Windows license is invalid');
  }
  if (device.office_license && !normalizeImportChoice(device.office_license, IMPORT_OFFICE_LICENSE_MAP) && IMPORT_OFFICE_LICENSE_MAP[normalizeImportHeader(device.office_license)] !== device.office_license) {
    errors.push('Office license is invalid');
  }
  if (seenTargets[targetKey]) {
    errors.push('Duplicate target with row ' + seenTargets[targetKey]);
  } else {
    seenTargets[targetKey] = device.row_num;
  }

  return errors;
}

function buildImportPayload(device) {
  var payload = {};
  IMPORT_SAVE_FIELDS.forEach(function(field) {
    if (device[field] !== undefined && device[field] !== null && device[field] !== '') {
      payload[field] = device[field];
    }
  });
  var currentUser = getCurrentUser();
  payload.updated_by = currentUser ? (currentUser.name || currentUser.username) : 'admin';
  return payload;
}

function handleImportFile(input) {
  if (!input.files.length) return;
  if (typeof XLSX === 'undefined') {
    if (typeof ensureLocalXlsx === 'function') {
      ensureLocalXlsx(function(loaded) {
        if (loaded && typeof XLSX !== 'undefined') {
          handleImportFile(input);
        } else {
          showToast('Excel library is not loaded. Please reload the page.', 'error');
          input.value = '';
        }
      });
      return;
    }
    showToast('Excel library is not loaded. Please reload the page.', 'error');
    input.value = '';
    return;
  }

  var file = input.files[0];
  var reader = new FileReader();

  reader.onload = function(e) {
    try {
      var fileData = new Uint8Array(e.target.result);
      var book = XLSX.read(fileData, { type: 'array' });
      var selectedSheet = pickImportSheet(book);

      if (!selectedSheet || !selectedSheet.rows.length) {
        showToast('No usable worksheet was found in this file.', 'error');
        return;
      }

      if (selectedSheet.legacy) {
        parseLegacyTemplate(selectedSheet.rows);
      } else {
        parseGenericImport(selectedSheet.rows);
      }
    } catch (err) {
      console.error(err);
      showToast('Cannot read file: ' + err.message, 'error');
    }
  };

  reader.readAsArrayBuffer(file);
  input.value = '';
}

function parseLegacyTemplate(rows) {
  var seenTargets = {};
  importedData = buildLegacyTemplateDevices(rows).map(function(device) {
    device.errors = validateImportedDevice(device, seenTargets);
    return device;
  });
  showImportPreview();
}

function parseGenericImport(rows) {
  var table = buildGenericImportTable(rows);
  var seenTargets = {};

  if (!table) {
    showToast('No importable rows were found in this file.', 'error');
    return;
  }

  var colMap = buildImportColumnMap(table.headers);
  if (!Object.keys(colMap).length) {
    showToast('No supported columns were detected for import.', 'error');
    return;
  }

  importedData = table.content.map(function(row, idx) {
    var device = buildImportedDevice(row, table.rowOffset + idx, colMap);
    device.errors = validateImportedDevice(device, seenTargets);
    return device;
  });

  showImportPreview();
}

function showImportPreview() {
  var valid = importedData.filter(function(item) { return !item.errors.length; });
  var invalid = importedData.filter(function(item) { return item.errors.length; });
  var createCount = valid.filter(function(item) { return item._importAction === 'create'; }).length;
  var updateCount = valid.length - createCount;
  var html = '<div style="margin-bottom:20px;padding:12px;background:var(--s2);border-radius:6px;border-left:3px solid var(--cyan)">';

  html += '<div style="font-weight:500;font-size:14px">Summary: <strong>' + importedData.length + '</strong> rows';
  if (createCount) html += ' | New: <strong style="color:var(--cyan)">' + createCount + '</strong>';
  if (updateCount) html += ' | Update: <strong style="color:var(--green)">' + updateCount + '</strong>';
  if (invalid.length) html += ' | Error: <strong style="color:var(--red)">' + invalid.length + '</strong>';
  html += '</div></div>';

  if (invalid.length) {
    html += '<div style="margin-bottom:16px;padding:12px;background:rgba(255,100,100,.08);border:1px solid rgba(255,100,100,.15);border-radius:6px">';
    html += '<div style="font-weight:500;color:var(--red);margin-bottom:10px">Rows with errors (skipped)</div>';
    html += '<div style="max-height:200px;overflow-y:auto">';
    invalid.forEach(function(item) {
      html += '<div style="font-size:12px;padding:6px 0;border-bottom:1px solid rgba(255,100,100,.1);color:var(--text2)">';
      html += '<strong>Row ' + item.row_num + ':</strong> ' + xss(item.computer_name || item.id || '(no key)') + ' - ' + xss(item.errors.join(', '));
      html += '</div>';
    });
    html += '</div></div>';
  }

  if (valid.length) {
    html += '<div style="margin-bottom:16px"><div style="font-weight:500;margin-bottom:10px">Ready to import</div>';
    html += '<div style="max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:6px">';
    html += '<table style="width:100%;font-size:12px;border-collapse:collapse">';
    html += '<thead><tr style="background:var(--s3);border-bottom:1px solid var(--border)">';
    html += '<th style="padding:8px;text-align:left">Action</th>';
    html += '<th style="padding:8px;text-align:left">Computer Name</th>';
    html += '<th style="padding:8px;text-align:left">Type</th>';
    html += '<th style="padding:8px;text-align:left">IP</th>';
    html += '<th style="padding:8px;text-align:left">User</th>';
    html += '<th style="padding:8px;text-align:left">Department</th>';
    html += '</tr></thead><tbody>';
    valid.forEach(function(item) {
      var badge = item._importAction === 'update'
        ? '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:rgba(0,232,150,.1);border:1px solid rgba(0,232,150,.2);color:var(--green);font-family:var(--mono)">UPDATE ' + xss(item._existingId) + '</span>'
        : '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.15);color:var(--cyan);font-family:var(--mono)">NEW</span>';
      html += '<tr style="border-bottom:1px solid var(--border)">';
      html += '<td style="padding:8px">' + badge + '</td>';
      html += '<td style="padding:8px"><strong>' + xss(item.computer_name || '-') + '</strong></td>';
      html += '<td style="padding:8px">' + xss(item.type || 'Desktop PC') + '</td>';
      html += '<td style="padding:8px;font-family:var(--mono);font-size:11px">' + xss(item.ip || '-') + '</td>';
      html += '<td style="padding:8px">' + xss(item.username || '-') + '</td>';
      html += '<td style="padding:8px">' + xss(item.dept || '-') + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table></div></div>';
  }

  document.getElementById('importPreview').innerHTML = html;
  document.getElementById('importCount').textContent = valid.length;
  document.getElementById('btnSaveImport').disabled = !valid.length;
  document.getElementById('ovImport').style.display = 'flex';
}

function closeImport() {
  document.getElementById('ovImport').style.display = 'none';
  importedData = [];
}

async function saveImportedData() {
  var valid = importedData.filter(function(item) { return !item.errors.length; });
  if (!valid.length) {
    showToast('There is no valid data to import.', 'error');
    return;
  }

  var btn = document.getElementById('btnSaveImport');
  var btnHtml = btn.innerHTML;
  var workingData = data.slice();
  var saved = 0;
  var failed = 0;
  var failList = [];

  btn.disabled = true;
  btn.innerHTML = 'Saving...';

  for (var i = 0; i < valid.length; i++) {
    var importedRow = valid[i];
    try {
      var existing = findExistingDeviceForImport(importedRow, workingData);
      var payload = buildImportPayload(importedRow);
      if (existing) {
        await apiPut(API.devices, existing.id, payload);
      } else {
        var created = await apiPost(API.devices, payload);
        workingData.unshift(created);
      }
      saved++;
    } catch (e) {
      failed++;
      failList.push((importedRow.computer_name || importedRow.id || ('Row ' + importedRow.row_num)) + ': ' + e.message);
    }
  }

  btn.disabled = false;
  btn.innerHTML = btnHtml;

  if (failed) {
    console.error(failList);
    showToast('Import completed: ' + saved + ' success, ' + failed + ' failed.', 'error');
  } else {
    showToast('Excel import completed: ' + saved + ' rows.');
  }

  closeImport();
  await loadData();
}
