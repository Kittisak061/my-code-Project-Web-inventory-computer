// DeviceHub - Config v7
var API_BASE = ((typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : 'http://localhost') + '/devicehub/api';
var API = {
    auth:      API_BASE + '/auth.php',
    devices:   API_BASE + '/devices.php',
    emails:    API_BASE + '/emails.php',
    users:     API_BASE + '/users.php',
    repair:    API_BASE + '/repair.php',
    dashboard: API_BASE + '/dashboard.php'
};

function getAuthToken() {
    try { return sessionStorage.getItem('dh_token') || ''; } catch (e) { return ''; }
}

function clearAuthState() {
    try {
        sessionStorage.removeItem('dh_user');
        sessionStorage.removeItem('dh_token');
    } catch (e) {}
}

function getAuthHeaders(extra) {
    var headers = {};
    if (extra) {
        for (var k in extra) {
            if (Object.prototype.hasOwnProperty.call(extra, k)) headers[k] = extra[k];
        }
    }
    var token = getAuthToken();
    if (token) headers['X-Auth-Token'] = token;
    return headers;
}

async function parseApiResponse(r) {
    var text = await r.text();
    var data = null;
    if (text) {
        try { data = JSON.parse(text); } catch (e) {}
    }

    if (!r.ok) {
        var msg = data && data.error ? data.error : ('HTTP ' + r.status);
        if (r.status === 401) {
            clearAuthState();
            if (typeof window !== 'undefined' && window.location && window.location.href.indexOf('login.html') === -1) {
                window.location.replace('login.html?v=20260326i');
            }
        }
        throw new Error(msg);
    }

    return data || {};
}

async function apiGet(url, p) {
    if (!p) p = {};
    var q = new URLSearchParams(p).toString();
    var r = await fetch(q ? url + '?' + q : url, { headers: getAuthHeaders() });
    return parseApiResponse(r);
}

async function apiPost(url, b) {
    var r = await fetch(url, {
        method: 'POST',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(b || {})
    });
    return parseApiResponse(r);
}

async function apiPut(url, id, b) {
    var r = await fetch(url + '?id=' + encodeURIComponent(id), {
        method: 'PUT',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(b || {})
    });
    return parseApiResponse(r);
}

async function apiDelete(url, id) {
    var r = await fetch(url + '?id=' + encodeURIComponent(id), {
        method: 'DELETE',
        headers: getAuthHeaders()
    });
    return parseApiResponse(r);
}

function isNoDataValue(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'number' || typeof value === 'boolean') return false;
    var text = String(value).replace(/\s+/g, ' ').trim();
    return text === '' || text === '-' || text === 'โ€”' || text === 'โ€“';
}

function normalizeInputValue(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number' || typeof value === 'boolean') return value;
    var text = String(value).replace(/\s+/g, ' ').trim();
    return isNoDataValue(text) ? null : text;
}

function nullIfNoData(value) {
    return isNoDataValue(value) ? null : value;
}

function showToast(msg, type) {
    if (!type) type = 'success';
    var tc = document.getElementById('_tc');
    if (!tc) {
        tc = document.createElement('div');
        tc.id = '_tc';
        tc.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(tc);
    }
    var clr = type === 'success' ? 'var(--green)' : type === 'error' ? 'var(--red)' : 'var(--cyan)';
    var t = document.createElement('div');
    t.style.cssText = 'background:var(--surface);border:1px solid var(--border2);border-left:3px solid ' + clr + ';border-radius:8px;padding:12px 18px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.4);animation:toastIn .25s ease;max-width:320px;pointer-events:auto;';
    t.textContent = msg;
    tc.appendChild(t);
    setTimeout(function() { t.remove(); }, 3500);
}

var DEPARTMENTS = [
    'AD','Automotive','BCC','EC','MD','Medical','Meeting Room','MRT','Precision','QAC','Slit',
    'AD - AC','AD - GA','AD - HRD','AD - HRM','AD-IT',
    'AUTOMOTIVE - PD',
    'BC CENTER','BC CENTER - PC','BC CENTER - PURCHASE','BC CENTER - SALE',
    'BC CENTER - STANDARD SETTING','BC CENTER - WAREHOUSE',
    'EC - FM','EC - ME','EC - PE','EC DIVISION','MARKETING',
    'MEDICAL - PD','MEDICAL - PE',
    'PD1 - PE','PD1 - PE (ME/AUTO)','PD1 - PE (ME/INJ)','PD1 - PE (MN/ASSY)',
    'PD1 - PE (PE/AUTO)','PD1 - PE (PE/INJ)',
    'PRECISION - PD ASSY','PRECISION - PD ASSY(ASSY)','PRECISION - PD ASSY(PAINT)',
    'PRECISION - PD INJ1','PRECISION - PD INJ2',
    'PRODUCTION DIVISION 1','PRODUCTION DIVISION 2',
    'QA CENTER','QA CENTER - QA','QA CENTER - QC','QA CENTER - QM','QA CENTER - QS',
    'SLIT - PD','SLIT - PE'
];

function deptOptions(sel) {
    var h = '<option value="">- เลือกแผนก -</option>';
    for (var i = 0; i < DEPARTMENTS.length; i++) {
        var d = DEPARTMENTS[i];
        h += '<option value="' + d + '"' + (d === sel ? ' selected' : '') + '>' + d + '</option>';
    }
    return h;
}

var XL_THEMES = [
    { headerBg:'1E3A5F', headerFg:'FFFFFF', headerBg2:'2E5299', altBg:'EBF3FC', borderC:'2E5299', totalBg:'1E3A5F', totalFg:'FFFFFF' },
    { headerBg:'1A3C2A', headerFg:'FFFFFF', headerBg2:'2D6A4F', altBg:'EDFFF2', borderC:'2D6A4F', totalBg:'1A3C2A', totalFg:'FFFFFF' },
    { headerBg:'7C2D12', headerFg:'FFFFFF', headerBg2:'C2410C', altBg:'FFF7ED', borderC:'C2410C', totalBg:'7C2D12', totalFg:'FFFFFF' },
    { headerBg:'3B0764', headerFg:'FFFFFF', headerBg2:'6D28D9', altBg:'F5F3FF', borderC:'6D28D9', totalBg:'3B0764', totalFg:'FFFFFF' },
    { headerBg:'1A1A2E', headerFg:'00D4FF', headerBg2:'16213E', altBg:'1A2744', borderC:'00D4FF', totalBg:'0D0D1A', totalFg:'00E896' }
];
var _xlTheme = 0;

function colLetter(n) {
    var s = '';
    while (n > 0) {
        var r = (n - 1) % 26;
        s = String.fromCharCode(65 + r) + s;
        n = Math.floor((n - 1) / 26);
    }
    return s;
}

function buildStyledXL(rows, title, subtitle) {
    if (!rows || !rows.length) return XLSX.utils.json_to_sheet([]);
    var th = XL_THEMES[_xlTheme] || XL_THEMES[0];
    var cols = Object.keys(rows[0]);
    var ws = {};
    var border = {
        top:{style:'thin',color:{rgb:th.borderC}},
        bottom:{style:'thin',color:{rgb:th.borderC}},
        left:{style:'thin',color:{rgb:th.borderC}},
        right:{style:'thin',color:{rgb:th.borderC}}
    };
    var today = new Date().toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});

    ws['A1'] = { v: title || 'Report', t:'s', s:{
        font:{bold:true,sz:14,color:{rgb:th.headerFg},name:'Angsana New'},
        fill:{fgColor:{rgb:th.headerBg},patternType:'solid'},
        alignment:{horizontal:'center',vertical:'center'}
    }};
    ws['A2'] = { v: (subtitle || '') + ' | ' + today + ' | จำนวน ' + rows.length + ' รายการ', t:'s', s:{
        font:{sz:11,italic:true,color:{rgb:th.headerFg},name:'Angsana New'},
        fill:{fgColor:{rgb:th.headerBg2},patternType:'solid'},
        alignment:{horizontal:'center',vertical:'center'}
    }};
    ws['A3'] = { v:'', t:'s', s:{fill:{fgColor:{rgb:'F8F9FA'},patternType:'solid'}} };

    var HR = 4;
    cols.forEach(function(col, ci) {
        ws[colLetter(ci + 1) + HR] = { v: col, t:'s', s:{
            font:{bold:true,sz:11,color:{rgb:th.headerFg},name:'Angsana New'},
            fill:{fgColor:{rgb:th.headerBg},patternType:'solid'},
            alignment:{horizontal:'center',vertical:'center',wrapText:true},
            border:border
        }};
    });

    rows.forEach(function(row, ri) {
        var isAlt = ri % 2 === 1;
        var fill = isAlt ? th.altBg : 'FFFFFF';
        cols.forEach(function(col, ci) {
            var val = row[col];
            var isN = typeof val === 'number' || (typeof val === 'string' && val !== '' && !isNaN(val));
            ws[colLetter(ci + 1) + (HR + 1 + ri)] = {
                v: (val !== null && val !== undefined) ? val : '',
                t: isN ? 'n' : 's',
                s:{
                    font:{sz:11,name:'Angsana New'},
                    fill:{fgColor:{rgb:fill},patternType:'solid'},
                    alignment:{vertical:'center'},
                    border:border
                }
            };
        });
    });

    var TR = HR + 1 + rows.length;
    cols.forEach(function(col, ci) {
        ws[colLetter(ci + 1) + TR] = { v: ci === 0 ? 'รวม: ' + rows.length + ' รายการ' : '', t:'s', s:{
            font:{bold:true,sz:11,color:{rgb:th.totalFg},name:'Angsana New'},
            fill:{fgColor:{rgb:th.totalBg},patternType:'solid'},
            alignment:{horizontal:ci === 0 ? 'left' : 'center',vertical:'center'},
            border:border
        }};
    });

    ws['!ref'] = 'A1:' + colLetter(cols.length) + TR;
    ws['!merges'] = [
        {s:{r:0,c:0},e:{r:0,c:cols.length-1}},
        {s:{r:1,c:0},e:{r:1,c:cols.length-1}},
        {s:{r:2,c:0},e:{r:2,c:cols.length-1}}
    ];
    ws['!rows'] = [{hpt:24},{hpt:18},{hpt:6},{hpt:22}];
    ws['!cols'] = cols.map(function(col) {
        var max = col.length;
        rows.forEach(function(r) {
            var v = String(r[col] || '');
            if (v.length > max) max = v.length;
        });
        return {wch:Math.min(Math.max(max + 2, 8), 40)};
    });
    return ws;
}

function xlExport(rows, filename, title, subtitle) {
    var ws = buildStyledXL(rows, title, subtitle);
    var wb = XLSX.utils.book_new();
    var sheetName = filename.replace('.xlsx', '').substring(0, 31);
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, filename);
}
