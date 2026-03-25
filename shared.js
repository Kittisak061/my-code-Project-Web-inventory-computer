// DeviceHub shared.js v5

(function() {
    var href = window.location.href;
    if (href.indexOf('login.html') !== -1) return;
    if (!sessionStorage.getItem('dh_user')) {
        window.location.replace('login.html');
    }
})();

var DEVICEHUB_LOCAL_XLSX_SRC = 'vendor/xlsx.full.min.js?v=20260324';

function ensureLocalXlsx(callback) {
    if (typeof document === 'undefined') {
        if (callback) callback(false);
        return;
    }

    if (window.XLSX) {
        if (callback) callback(true);
        return;
    }

    var existing = document.getElementById('dh-local-xlsx');
    if (existing) {
        if (callback) {
            if (existing.getAttribute('data-loaded') === '1' || window.XLSX) {
                callback(true);
            } else {
                existing.addEventListener('load', function() { callback(!!window.XLSX); }, { once: true });
                existing.addEventListener('error', function() { callback(false); }, { once: true });
            }
        }
        return;
    }

    var script = document.createElement('script');
    script.id = 'dh-local-xlsx';
    script.src = DEVICEHUB_LOCAL_XLSX_SRC;
    script.async = false;
    script.onload = function() {
        script.setAttribute('data-loaded', '1');
        if (callback) callback(!!window.XLSX);
    };
    script.onerror = function() {
        if (callback) callback(false);
    };
    console.warn('DeviceHub: XLSX CDN unavailable, loading local fallback.');
    (document.head || document.documentElement).appendChild(script);
}

(function primeLocalXlsxFallback() {
    if (window.location.href.indexOf('login.html') !== -1) return;

    function loadIfNeeded() {
        if (!window.XLSX) ensureLocalXlsx();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadIfNeeded, { once: true });
    } else {
        loadIfNeeded();
    }
})();

function getCurrentUser() {
    var raw = sessionStorage.getItem('dh_user');
    if (!raw) return null;
    try { var o = JSON.parse(raw); if (o && o.username) return o; } catch(e) {}
    return { username: raw, name: raw, role: raw === 'admin' ? 'admin' : 'user', dept: '' };
}

function isAdmin() {
    var u = getCurrentUser();
    return !!(u && u.role === 'admin');
}

function logout() {
    sessionStorage.removeItem('dh_user');
    window.location.href = 'login.html';
}

function buildSidebar(activeId) {
    var u = getCurrentUser() || { username: 'admin', name: 'Admin IT', role: 'admin', dept: '' };
    var av = (u.name || u.username || 'A').charAt(0).toUpperCase();

    var pages = [
        { id: 'dashboard',   ico: '📊', label: 'Dashboard',        href: 'dashboard.html',   sec: 'หลัก' },
        { id: 'device-list', ico: '🖥',  label: 'อุปกรณ์ IT',      href: 'device-list.html', sec: '' },
        { id: 'email',       ico: '✉️',  label: 'Email & Computer', href: 'email.html',       sec: '' },
        { id: 'email-list',  ico: '📋',  label: 'KF Email List',    href: 'email-list.html',  sec: '' },
        { id: 'users',       ico: '👥',  label: 'ผู้ใช้งาน',       href: 'users.html',       sec: 'จัดการ' },
        { id: 'repair',      ico: '🔧',  label: 'แจ้งซ่อม',        href: 'repair.html',      sec: '' },
        { id: 'reports',     ico: '📊',  label: 'รายงาน',          href: 'reports.html',     sec: '' },
        { id: 'export',      ico: '📥',  label: 'Export Excel',     href: 'export.html',      sec: '' },
        { id: 'settings',    ico: '⚙️',  label: 'ตั้งค่า',         href: 'settings.html',    sec: '' }
    ];

    var nav = '';
    var lastSec = '';
    for (var i = 0; i < pages.length; i++) {
        var p = pages[i];
        if (p.sec && p.sec !== lastSec) {
            lastSec = p.sec;
            nav += '<div class="sb-section">' + p.sec + '</div>';
        }
        var act = (p.id === activeId) ? ' active' : '';
        nav += '<a class="sb-item' + act + '" href="' + p.href + '">'
             + '<span class="ico">' + p.ico + '</span>' + p.label + '</a>';
    }

    var badge = u.role === 'admin'
        ? '<span style="font-size:10px;background:rgba(0,212,255,.15);color:var(--cyan);padding:1px 6px;border-radius:4px;font-family:var(--mono)">ADMIN</span>'
        : '<span style="font-size:10px;background:rgba(122,136,153,.12);color:var(--text3);padding:1px 6px;border-radius:4px;font-family:var(--mono)">USER</span>';

    var html = ''
        + '<aside class="sidebar">'
        + '<div class="sb-logo">'
        + '<div class="sb-mark">🖥</div>'
        + '<div><div class="sb-name">DeviceHub</div><div class="sb-ver">IT MGMT v3.0</div></div>'
        + '</div>'
        + '<nav class="sb-nav">' + nav + '</nav>'
        + '<div class="sb-footer">'
        + '<div class="sb-avatar">' + av + '</div>'
        + '<div style="flex:1;min-width:0">'
        + '<div class="sb-uname">' + xss(u.name || u.username) + '</div>'
        + '<div style="margin-top:3px">' + badge + '</div>'
        + '</div>'
        + '<button onclick="logout()" title="ออกจากระบบ" '
        + 'style="background:transparent;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:4px;flex-shrink:0" '
        + 'onmouseenter="this.style.color=\'var(--red)\'" '
        + 'onmouseleave="this.style.color=\'var(--text3)\'">⏻</button>'
        + '</div>'
        + '</aside>';

    var slot = document.getElementById('sidebar-slot');
    if (slot) slot.innerHTML = html;
}

function xss(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
