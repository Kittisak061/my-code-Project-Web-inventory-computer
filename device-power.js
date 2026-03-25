(function() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  if (typeof API === 'undefined' || typeof apiPost !== 'function') return;

  var POWER_STATE_BY_ID = {};
  var POWER_SUMMARY = { on: 0, off: 0, unknown: 0 };
  var POWER_REQUEST_TOKEN = 0;
  var POWER_TIMER = null;
  var POWER_BOOT_TRIES = 0;

  function injectPowerStyles() {
    if (document.getElementById('device-power-style')) return;
    var style = document.createElement('style');
    style.id = 'device-power-style';
    style.textContent = ''
      + '.power-badge{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;font-size:10.5px;font-family:var(--mono);margin-top:6px;border:1px solid var(--border);}'
      + '.power-badge::before{content:"";width:7px;height:7px;border-radius:50%;display:inline-block;}'
      + '.power-on{color:var(--green);background:rgba(0,232,150,.1);border-color:rgba(0,232,150,.22);}'
      + '.power-on::before{background:var(--green);box-shadow:0 0 8px rgba(0,232,150,.45);}'
      + '.power-off{color:var(--red);background:rgba(255,100,100,.08);border-color:rgba(255,100,100,.18);}'
      + '.power-off::before{background:var(--red);box-shadow:0 0 8px rgba(255,100,100,.35);}'
      + '.power-checking{color:var(--cyan);background:rgba(0,212,255,.08);border-color:rgba(0,212,255,.18);}'
      + '.power-checking::before{background:var(--cyan);animation:powerPulse 1s ease infinite;}'
      + '.power-unknown{color:var(--text3);background:rgba(122,136,153,.08);border-color:var(--border);}'
      + '.power-unknown::before{background:var(--text3);}'
      + '.power-meta{display:block;font-size:10px;color:var(--text3);margin-top:3px;font-family:var(--mono);}'
      + '.power-summary{font-size:11px;color:var(--text3);font-family:var(--mono);margin-left:10px;}'
      + '@keyframes powerPulse{0%{opacity:.45;transform:scale(.9)}50%{opacity:1;transform:scale(1.08)}100%{opacity:.45;transform:scale(.9)}}';
    document.head.appendChild(style);
  }

  function ensurePowerButton() {
    if (document.getElementById('btnPowerCheck')) return;
    var topbar = document.querySelector('.topbar');
    if (!topbar) return;

    var refreshBtn = topbar.querySelector('.btn.btn-ghost');
    var btn = document.createElement('button');
    btn.id = 'btnPowerCheck';
    btn.className = 'btn btn-ghost';
    btn.textContent = '⚡ เช็กเครื่อง';
    btn.onclick = function() { refreshLivePower(true); };

    if (refreshBtn && refreshBtn.nextSibling) topbar.insertBefore(btn, refreshBtn.nextSibling);
    else topbar.appendChild(btn);
  }

  function getPowerButton() {
    ensurePowerButton();
    return document.getElementById('btnPowerCheck');
  }

  function pickCurrentTarget(device) {
    if (!device) return '—';
    if (device.computer_name && device.workgroup) return device.computer_name + ' [' + device.workgroup + ']';
    if (device.computer_name) return device.computer_name;
    return '—';
  }

  function getPowerState(device) {
    if (!device || !device.id) return { power_state: 'unknown', message: 'No device id' };
    return POWER_STATE_BY_ID[device.id] || { power_state: 'unknown', message: 'ยังไม่เช็ก' };
  }

  function isMaintenanceDevice(device) {
    return !!(device && device.status === 'maintenance');
  }

  function getMaintenanceBadgeHtml(device) {
    if (!isMaintenanceDevice(device)) return '';
    return '<div style="margin-top:6px"><span class="badge badge-maintenance">กำลังซ่อม</span></div>';
  }

  function countMaintenanceDevices() {
    if (!Array.isArray(window.data)) return 0;
    var count = 0;
    for (var i = 0; i < window.data.length; i++) {
      if (isMaintenanceDevice(window.data[i])) count++;
    }
    return count;
  }

  function getPowerBadgeHtml(device) {
    var state = getPowerState(device);
    if (state.power_state === 'on') {
      return '<div class="power-badge power-on">เปิดอยู่</div><span class="power-meta">' + xss(state.target || pickCurrentTarget(device)) + '</span>';
    }
    if (state.power_state === 'off') {
      return '<div class="power-badge power-off">ปิด / ไม่ตอบ</div><span class="power-meta">' + xss(state.target || pickCurrentTarget(device)) + '</span>';
    }
    if (state.power_state === 'checking') {
      return '<div class="power-badge power-checking">กำลังเช็ก...</div><span class="power-meta">' + xss(state.target || pickCurrentTarget(device)) + '</span>';
    }
    return '<div class="power-badge power-unknown">ยังไม่ทราบ</div><span class="power-meta">' + xss(state.target || pickCurrentTarget(device)) + '</span>';
  }

  function applyLivePowerToTable() {
    var tbody = document.getElementById('tb');
    if (!tbody || !Array.isArray(window.data) || !window.data.length) return;

    var rows = tbody.querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) {
      var device = window.data[i];
      if (!device) continue;

      var statusCell = rows[i].children[20];
      if (statusCell) {
        statusCell.innerHTML = ''
          + '<div><span class="badge badge-' + device.status + '">' + (STH[device.status] || device.status || '—') + '</span></div>'
          + getPowerBadgeHtml(device);
        statusCell.innerHTML = ''
          + getPowerBadgeHtml(device)
          + getMaintenanceBadgeHtml(device);
      }
    }
  }

  function applyLivePowerToDetail(device) {
    if (!device) return;
    var detailBody = document.getElementById('dB');
    if (!detailBody) return;

    var existing = detailBody.querySelector('.dv-live-power');
    if (existing) existing.remove();

    var wrapper = document.createElement('div');
    wrapper.className = 'dv-section dv-live-power';
    wrapper.innerHTML = ''
      + '<div class="dv-sec-title">⚡ สถานะเครื่องตอนนี้</div>'
      + '<div class="dv-grid">'
      + '<div class="dv-row"><div class="dv-lbl">Power State</div><div class="dv-val">' + getPowerBadgeHtml(device) + '</div></div>'
      + '<div class="dv-row"><div class="dv-lbl">Target</div><div class="dv-val mono">' + xss(pickCurrentTarget(device)) + '</div></div>'
      + '<div class="dv-row"><div class="dv-lbl">Maintenance</div><div class="dv-val">' + (isMaintenanceDevice(device) ? '<span class="badge badge-maintenance">กำลังซ่อม</span>' : '—') + '</div></div>'
      + '</div>';

    detailBody.insertBefore(wrapper, detailBody.firstChild);
  }

  function updateRealtimeStatCards(checking, errorMessage) {
    var total = Array.isArray(window.data) ? window.data.length : 0;
    var s0 = document.getElementById('s0');
    var s1 = document.getElementById('s1');
    var s2 = document.getElementById('s2');
    var s3 = document.getElementById('s3');

    if (s0) s0.textContent = total;
    if (s2) s2.textContent = countMaintenanceDevices();

    if (s1) {
      if (checking) s1.textContent = '...';
      else if (errorMessage) s1.textContent = '—';
      else s1.textContent = POWER_SUMMARY.on;
    }

    if (s3) {
      if (checking) s3.textContent = '...';
      else if (errorMessage) s3.textContent = '—';
      else s3.textContent = POWER_SUMMARY.off;
    }
  }

  function updatePowerSummaryUi(checking, errorMessage) {
    updateRealtimeStatCards(checking, errorMessage);

    var btn = getPowerButton();
    if (btn) {
      btn.disabled = !!checking;
      btn.textContent = checking ? '⚡ กำลังเช็ก...' : '⚡ เช็กเครื่อง';
    }

    var dbTxt = document.getElementById('dbTxt');
    if (dbTxt && Array.isArray(window.data)) {
      if (checking) {
        dbTxt.textContent = 'MySQL · ' + window.data.length + ' เครื่อง · กำลังเช็กสถานะเครื่อง';
      } else if (errorMessage) {
        dbTxt.textContent = 'MySQL · ' + window.data.length + ' เครื่อง · เช็กสถานะเครื่องไม่ได้';
      } else {
        dbTxt.textContent = 'MySQL · ' + window.data.length + ' เครื่อง · เปิด ' + POWER_SUMMARY.on + ' · ปิด ' + POWER_SUMMARY.off + ' · ไม่ทราบ ' + POWER_SUMMARY.unknown;
      }
    }

    var pager = document.getElementById('pi');
    if (pager && Array.isArray(window.data) && window.data.length) {
      var base = 'แสดง ' + window.data.length + ' รายการ';
      if (checking) {
        pager.textContent = base + ' · กำลังเช็กสถานะเครื่อง';
      } else if (!errorMessage) {
        pager.textContent = base + ' · เปิด ' + POWER_SUMMARY.on + ' · ปิด ' + POWER_SUMMARY.off + ' · ไม่ทราบ ' + POWER_SUMMARY.unknown;
      } else {
        pager.textContent = base + ' · เช็กสถานะเครื่องไม่ได้';
      }
    }
  }

  function markCurrentRowsChecking() {
    if (!Array.isArray(window.data)) return;
    for (var i = 0; i < window.data.length; i++) {
      var device = window.data[i];
      POWER_STATE_BY_ID[device.id] = {
        id: device.id,
        power_state: 'checking',
        target: pickCurrentTarget(device),
        target_source: device.workgroup ? 'computer_name (workgroup)' : 'computer_name'
      };
    }
  }

  function buildProbePayload() {
    if (!Array.isArray(window.data)) return [];
    return window.data.map(function(device) {
      return {
        id: device.id,
        computer_name: device.computer_name || '',
        workgroup: device.workgroup || '',
        status: device.status || ''
      };
    });
  }

  function normalizePowerResponse(items) {
    var map = {};
    POWER_SUMMARY = { on: 0, off: 0, unknown: 0 };

    (items || []).forEach(function(item) {
      map[item.id] = item;
      if (!POWER_SUMMARY[item.power_state]) POWER_SUMMARY[item.power_state] = 0;
      POWER_SUMMARY[item.power_state]++;
    });

    POWER_STATE_BY_ID = map;
  }

  function refreshOpenDetailIfNeeded() {
    var overlay = document.getElementById('ov2');
    if (!overlay || !overlay.classList.contains('open')) return;
    var title = document.getElementById('dT');
    if (!title || !Array.isArray(window.data)) return;

    for (var i = 0; i < window.data.length; i++) {
      var device = window.data[i];
      if (title.textContent && title.textContent.indexOf(device.computer_name || '') !== -1) {
        applyLivePowerToDetail(device);
        break;
      }
    }
  }

  async function refreshLivePower(showToastMessage) {
    if (!Array.isArray(window.data) || !window.data.length) return;

    var token = ++POWER_REQUEST_TOKEN;
    markCurrentRowsChecking();
    applyLivePowerToTable();
    updatePowerSummaryUi(true);

    try {
      var response = await apiPost(API.devices + '?action=power', {
        action: 'power',
        devices: buildProbePayload()
      });

      if (token !== POWER_REQUEST_TOKEN) return;
      normalizePowerResponse(response.items);
      applyLivePowerToTable();
      refreshOpenDetailIfNeeded();
      updatePowerSummaryUi(false);

      if (showToastMessage) {
        showToast('เช็กสถานะเครื่องแล้ว: เปิด ' + POWER_SUMMARY.on + ' · ปิด ' + POWER_SUMMARY.off + ' · ไม่ทราบ ' + POWER_SUMMARY.unknown, 'success');
      }
    } catch (e) {
      if (token !== POWER_REQUEST_TOKEN) return;

      POWER_SUMMARY = { on: 0, off: 0, unknown: Array.isArray(window.data) ? window.data.length : 0 };
      for (var i = 0; i < window.data.length; i++) {
        var device = window.data[i];
        POWER_STATE_BY_ID[device.id] = {
          id: device.id,
          power_state: 'unknown',
          target: pickCurrentTarget(device),
          target_source: device.workgroup ? 'computer_name (workgroup)' : 'computer_name',
          message: e.message
        };
      }

      applyLivePowerToTable();
      refreshOpenDetailIfNeeded();
      updatePowerSummaryUi(false, e.message);
      if (showToastMessage) showToast('เช็กสถานะเครื่องไม่ได้: ' + e.message, 'error');
    }
  }

  function scheduleLivePowerCheck(delay) {
    clearTimeout(POWER_TIMER);
    POWER_TIMER = setTimeout(function() {
      refreshLivePower(false);
    }, delay || 150);
  }

  function patchRender() {
    if (typeof window.render !== 'function' || window.render._powerPatched) return;
    var originalRender = window.render;
    var wrapped = function() {
      originalRender.apply(this, arguments);
      applyLivePowerToTable();
      refreshOpenDetailIfNeeded();
    };
    wrapped._powerPatched = true;
    window.render = wrapped;
    try { render = wrapped; } catch (e) {}
  }

  function patchLoadData() {
    if (typeof window.loadData !== 'function' || window.loadData._powerPatched) return;
    var originalLoadData = window.loadData;
    var wrapped = async function() {
      var result = await originalLoadData.apply(this, arguments);
      scheduleLivePowerCheck(200);
      return result;
    };
    wrapped._powerPatched = true;
    window.loadData = wrapped;
    try { loadData = wrapped; } catch (e) {}
  }

  function patchShowDetail() {
    if (typeof window.showDetail !== 'function' || window.showDetail._powerPatched) return;
    var originalShowDetail = window.showDetail;
    var wrapped = function(device) {
      originalShowDetail.apply(this, arguments);
      applyLivePowerToDetail(device);
    };
    wrapped._powerPatched = true;
    window.showDetail = wrapped;
    try { showDetail = wrapped; } catch (e) {}
  }

  function scheduleInitialPowerBoot() {
    clearTimeout(POWER_TIMER);
    POWER_BOOT_TRIES = 0;

    function waitForData() {
      if (Array.isArray(window.data) && window.data.length) {
        scheduleLivePowerCheck(120);
        return;
      }

      POWER_BOOT_TRIES++;
      if (POWER_BOOT_TRIES >= 40) return;

      POWER_TIMER = setTimeout(waitForData, 300);
    }

    POWER_TIMER = setTimeout(waitForData, 180);
  }

  function initDevicePowerCheck() {
    injectPowerStyles();
    ensurePowerButton();
    patchRender();
    patchLoadData();
    patchShowDetail();

    scheduleInitialPowerBoot();
  }

  window.refreshLivePower = refreshLivePower;
  window.getPowerState = getPowerState;
  initDevicePowerCheck();
})();
