// DeviceHub shared.js v6

(function enforceSession() {
    var href = window.location.href;
    if (href.indexOf('login.html') !== -1) return;
    if (!sessionStorage.getItem('dh_user') || !sessionStorage.getItem('dh_token')) {
        window.location.replace('login.html?v=20260326i');
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
    var token = sessionStorage.getItem('dh_token');
    if (!raw || !token) return null;
    try {
        var parsed = JSON.parse(raw);
        if (parsed && parsed.username) return parsed;
    } catch (e) {}
    return { username: raw, name: raw, role: raw === 'admin' ? 'admin' : 'user', dept: '' };
}

function isAdmin() {
    var user = getCurrentUser();
    return !!(user && user.role === 'admin');
}

function logout() {
    sessionStorage.removeItem('dh_user');
    sessionStorage.removeItem('dh_token');
    window.location.href = 'login.html?v=20260326i';
}

function xss(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function safeText(value, fallback) {
    var hasValue = value !== null && value !== undefined && value !== '';
    return xss(hasValue ? value : (arguments.length > 1 ? fallback : '—'));
}

function encodeInlineData(value) {
    try {
        return encodeURIComponent(JSON.stringify(value == null ? null : value)).replace(/'/g, '%27');
    } catch (e) {
        console.warn('DeviceHub: failed to encode inline data.', e);
        return '';
    }
}

function decodeInlineData(value) {
    try {
        return JSON.parse(decodeURIComponent(String(value || '')));
    } catch (e) {
        console.warn('DeviceHub: failed to decode inline data.', e);
        return null;
    }
}

function buildSidebar(activeId) {
    var user = getCurrentUser() || { username: 'admin', name: 'Admin IT', role: 'admin', dept: '' };
    var avatar = (user.name || user.username || 'A').charAt(0).toUpperCase();

    var pages = [
        { id: 'dashboard',   ico: '📊', label: 'Dashboard',           href: 'dashboard.html',   sec: 'หลัก' },
        { id: 'device-list', ico: '🖥', label: 'อุปกรณ์ IT',          href: 'device-list.html', sec: '' },
        { id: 'email',       ico: '✉️', label: 'Email & Computer',    href: 'email.html',       sec: '' },
        { id: 'email-list',  ico: '📋', label: 'KF Email List',       href: 'email-list.html',  sec: '' },
        { id: 'users',       ico: '👥', label: 'ผู้ใช้งาน',            href: 'users.html',       sec: 'จัดการ' },
        { id: 'repair',      ico: '🛠', label: 'อุปกรณ์ที่ต้องซ่อม',   href: 'repair.html',      sec: '' },
        { id: 'reports',     ico: '📈', label: 'รายงาน',              href: 'reports.html',     sec: '' },
        { id: 'export',      ico: '📦', label: 'Export Excel',        href: 'export.html',      sec: '' },
        { id: 'settings',    ico: '⚙️', label: 'ตั้งค่า',             href: 'settings.html',    sec: '' }
    ];

    var nav = '';
    var lastSection = '';
    for (var i = 0; i < pages.length; i++) {
        var page = pages[i];
        if (page.sec && page.sec !== lastSection) {
            lastSection = page.sec;
            nav += '<div class="sb-section">' + page.sec + '</div>';
        }

        var isActive = page.id === activeId ? ' active' : '';
        nav += '<a class="sb-item' + isActive + '" href="' + page.href + '">'
            + '<span class="ico">' + page.ico + '</span>'
            + page.label
            + '</a>';
    }

    var badge = user.role === 'admin'
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
        + '<div class="sb-avatar">' + avatar + '</div>'
        + '<div style="flex:1;min-width:0">'
        + '<div class="sb-uname">' + xss(user.name || user.username) + '</div>'
        + '<div style="margin-top:3px">' + badge + '</div>'
        + '</div>'
        + '<button onclick="logout()" title="Logout" '
        + 'style="background:transparent;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:4px;flex-shrink:0" '
        + 'onmouseenter="this.style.color=\'var(--red)\'" '
        + 'onmouseleave="this.style.color=\'var(--text3)\'">⏻</button>'
        + '</div>'
        + '</aside>';

    var slot = document.getElementById('sidebar-slot');
    if (slot) {
        slot.innerHTML = html;
        refreshDhUiEnhancements(slot);
    }
}

function ensureAppShellClasses() {
    if (typeof document === 'undefined' || !document.body) return;
    document.body.classList.add('dh-app');
    window.requestAnimationFrame(function() {
        document.body.classList.add('dh-ready');
    });
}

function enhanceTopbars(root) {
    var scope = (root && root.querySelectorAll) ? root : document;
    var bars = scope.querySelectorAll ? scope.querySelectorAll('.topbar') : [];

    for (var i = 0; i < bars.length; i++) {
        var bar = bars[i];
        if (bar.getAttribute('data-dh-topbar') === '1') continue;
        bar.setAttribute('data-dh-topbar', '1');
        if (bar.firstElementChild && bar.firstElementChild.tagName === 'DIV') {
            bar.firstElementChild.classList.add('topbar-lead');
        }
    }
}

var dhRevealObserver = null;

function getDhRevealObserver() {
    if (dhRevealObserver || !('IntersectionObserver' in window)) return dhRevealObserver;

    dhRevealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            dhRevealObserver.unobserve(entry.target);
        });
    }, {
        rootMargin: '0px 0px -12% 0px',
        threshold: 0.12
    });

    return dhRevealObserver;
}

function refreshDhUiEnhancements(root) {
    if (typeof document === 'undefined') return;

    ensureAppShellClasses();
    enhanceTopbars(root);

    var scope = (root && root.querySelectorAll) ? root : document;
    var selectors = [
        '.stats-row > *',
        '.toolbar',
        '.table-wrap',
        '.card',
        '.perm-bar',
        '.repair-hero',
        '.hero',
        '.panel',
        '.metric',
        '.pstat',
        '.rc',
        '.tabs'
    ].join(',');
    var nodes = scope.querySelectorAll ? scope.querySelectorAll(selectors) : [];
    var observer = getDhRevealObserver();

    for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        if (el.getAttribute('data-dh-reveal') === '1') continue;

        el.setAttribute('data-dh-reveal', '1');
        el.classList.add('dh-reveal');
        el.style.setProperty('--reveal-delay', ((i % 8) * 45) + 'ms');

        if (observer) {
            observer.observe(el);
        } else {
            el.classList.add('is-visible');
        }
    }
}

(function primeDhUiEnhancements() {
    if (window.location.href.indexOf('login.html') !== -1) return;

    function initDhUi() {
        refreshDhUiEnhancements(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDhUi, { once: true });
    } else {
        initDhUi();
    }

    window.addEventListener('load', function() {
        refreshDhUiEnhancements(document);
    });
})();

function refreshSyncedScrollAreas(root) {
    if (typeof document === 'undefined') return;

    var scope = (root && root.querySelectorAll) ? root : document;
    var areas = [];

    if (root && root.nodeType === 1 && root.classList && root.classList.contains('sync-scroll-x')) {
        areas.push(root);
    }

    if (scope && scope.querySelectorAll) {
        var found = scope.querySelectorAll('.sync-scroll-x');
        for (var i = 0; i < found.length; i++) {
            if (areas.indexOf(found[i]) === -1) areas.push(found[i]);
        }
    }

    for (var j = 0; j < areas.length; j++) {
        ensureSyncedScrollArea(areas[j]);
    }
}

function ensureSyncedScrollArea(area) {
    if (!area || !area.parentNode) return null;

    var meta = area.__syncScrollMeta;
    if (!meta) {
        var top = document.createElement('div');
        top.className = 'sync-scroll-top';
        top.innerHTML = '<div class="sync-scroll-top-inner"></div>';
        area.parentNode.insertBefore(top, area);

        var inner = top.firstChild;
        var syncing = false;

        function syncScroll(from, to) {
            if (syncing) return;
            syncing = true;
            to.scrollLeft = from.scrollLeft;
            window.requestAnimationFrame(function() {
                syncing = false;
            });
        }

        top.addEventListener('scroll', function() {
            syncScroll(top, area);
        }, { passive: true });

        area.addEventListener('scroll', function() {
            syncScroll(area, top);
        }, { passive: true });

        meta = {
            top: top,
            inner: inner,
            refresh: function() {
                var scrollWidth = area.scrollWidth;
                var clientWidth = area.clientWidth;
                var show = scrollWidth > clientWidth + 2;

                inner.style.width = Math.max(scrollWidth, clientWidth) + 'px';
                top.classList.toggle('is-active', show);

                if (show && Math.abs(top.scrollLeft - area.scrollLeft) > 1) {
                    top.scrollLeft = area.scrollLeft;
                }

                if (!show) {
                    top.scrollLeft = 0;
                }
            }
        };

        if ('ResizeObserver' in window) {
            meta.observer = new ResizeObserver(function() {
                meta.refresh();
            });
            meta.observer.observe(area);
        }

        area.__syncScrollMeta = meta;
    }

    meta.refresh();
    return meta;
}

(function primeSyncedScrollAreas() {
    if (window.location.href.indexOf('login.html') !== -1) return;

    function initSyncScrolls() {
        refreshSyncedScrollAreas(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSyncScrolls, { once: true });
    } else {
        initSyncScrolls();
    }

    window.addEventListener('resize', function() {
        refreshSyncedScrollAreas(document);
    });
})();
