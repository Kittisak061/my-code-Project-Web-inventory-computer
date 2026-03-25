(function() {
  buildSidebar('dashboard');

  var REFRESH_MS = 90000;
  var state = {
    summary: null,
    power: null,
    busy: false,
    timer: null
  };

  function text(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function setDB(kind, textValue) {
    document.getElementById('dbDot').className = 'db-dot ' + kind;
    document.getElementById('dbTxt').textContent = textValue;
  }

  function updateClock() {
    text('clock', new Date().toLocaleString('th-TH', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }));
  }

  function animateNumber(id, nextValue) {
    var el = document.getElementById(id);
    if (!el) return;
    var target = Number(nextValue || 0);
    var current = Number(el.getAttribute('data-value') || 0);
    var delta = target - current;
    var steps = 18;
    var tick = 0;
    el.setAttribute('data-value', String(target));
    clearInterval(el._animTimer);
    el._animTimer = setInterval(function() {
      tick++;
      var value = Math.round(current + (delta * tick / steps));
      el.textContent = String(value);
      if (tick >= steps) {
        clearInterval(el._animTimer);
        el.textContent = String(target);
      }
    }, 18);
  }

  function pct(part, total) {
    if (!total) return 0;
    return Math.round((part / total) * 100);
  }

  function fmtDateTime(value) {
    if (!value) return '-';
    var d = new Date(value);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleString('th-TH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function fmtTime(value) {
    if (!value) return '-';
    var d = new Date(value);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleTimeString('th-TH', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function renderTrack(id, on, maint, off, unknown, total) {
    var el = document.getElementById(id);
    if (!el) return;
    var pOn = pct(on, total);
    var pMaint = pct(maint, total);
    var pOff = pct(off, total);
    var pUnknown = Math.max(0, 100 - pOn - pMaint - pOff);
    el.innerHTML = ''
      + '<span class="seg-on" style="width:' + pOn + '%"></span>'
      + '<span class="seg-maint" style="width:' + pMaint + '%"></span>'
      + '<span class="seg-off" style="width:' + pOff + '%"></span>'
      + '<span class="seg-unknown" style="width:' + pUnknown + '%"></span>';
  }

  function rankRows(items, maxCount, warn) {
    if (!items || !items.length) {
      return '<div class="empty">ไม่มีข้อมูลสำหรับแสดง</div>';
    }
    return items.map(function(item, idx) {
      var name = xss(item.label || '-');
      var count = Number(item.count || 0);
      var width = pct(count, maxCount || 1);
      return '<div class="rank-row" style="animation:rowIn .22s ease ' + (idx * 0.03) + 's both">'
        + '<div class="rank-name" title="' + name + '">' + name + '</div>'
        + '<div class="rank-bar"><div class="rank-fill' + (warn ? ' warn' : '') + '" style="width:' + width + '%"></div></div>'
        + '<div class="rank-count">' + count + '</div>'
        + '</div>';
    }).join('');
  }

  function sortFeed(items) {
    return items.slice().sort(function(a, b) {
      function score(item) {
        if (item.status === 'maintenance' && item.power_state !== 'on') return 0;
        if (item.power_state === 'off') return 1;
        if (item.power_state === 'unknown') return 2;
        if (item.status === 'maintenance') return 3;
        return 4;
      }
      var diff = score(a) - score(b);
      if (diff) return diff;
      return String(a.computer_name || '').localeCompare(String(b.computer_name || ''));
    });
  }

  function powerBadge(item) {
    if (item.power_state === 'on') return '<span class="rt on">เปิดอยู่</span>';
    if (item.power_state === 'off') return '<span class="rt off">ปิด / ไม่ตอบ</span>';
    return '<span class="rt unknown">ไม่ทราบ</span>';
  }

  function renderSummary() {
    var summary = state.summary || {};
    var power = state.power || {};
    var devices = summary.devices || { total: 0, maintenance: 0, with_remark: 0 };
    var emails = summary.emails || { total: 0, active: 0 };
    var users = summary.users || { total: 0, active: 0, admins: 0 };
    var tickets = summary.tickets || { total: 0, open: 0, in_progress: 0, resolved: 0, high: 0, medium: 0 };
    var p = power.summary || { on: 0, off: 0, unknown: 0 };
    var total = devices.total || 0;

    animateNumber('k0', devices.total || 0);
    animateNumber('k1', p.on || 0);
    animateNumber('k2', devices.maintenance || 0);
    animateNumber('k3', p.off || 0);
    animateNumber('k4', emails.total || 0);
    animateNumber('k5', users.active || 0);
    animateNumber('k6', tickets.open || 0);
    animateNumber('k7', tickets.high || 0);

    text('ks0', 'Inventory ทั้งหมดในระบบ');
    text('ks1', 'ตอบกลับได้ ' + pct(p.on || 0, total) + '%');
    text('ks2', 'เครื่องที่ถูกติ๊กกำลังซ่อม');
    text('ks3', 'ไม่ตอบกลับ ' + pct(p.off || 0, total) + '%');
    text('ks4', 'active ' + (emails.active || 0) + ' บัญชี');
    text('ks5', 'admin ' + (users.admins || 0) + ' คน');
    text('ks6', 'in progress ' + (tickets.in_progress || 0) + ' งาน');
    text('ks7', 'medium ' + (tickets.medium || 0) + ' งาน');

    text('heroCheckedAt', power.checked_at ? 'เช็กล่าสุด ' + fmtDateTime(power.checked_at) : 'ยังไม่เคยสแกน');
    text('heroScope', total ? 'สแกน ' + total + ' เครื่องจากฐานข้อมูล' : 'ยังไม่มีข้อมูลอุปกรณ์');
    text('liveHeadline', total ? ((p.on || 0) + ' / ' + total + ' เครื่องตอบกลับได้ตอนนี้') : 'ยังไม่มีข้อมูลอุปกรณ์');
    text('liveSub', 'Maintenance ' + (devices.maintenance || 0) + ' · Unknown ' + (p.unknown || 0));

    animateNumber('heroOn', p.on || 0);
    animateNumber('heroMaint', devices.maintenance || 0);
    animateNumber('heroOff', p.off || 0);
    text('heroOnPct', pct(p.on || 0, total) + '%');
    text('heroMaintPct', pct(devices.maintenance || 0, total) + '%');
    text('heroOffPct', pct(p.off || 0, total) + '%');
    text('heroUnknownPct', pct(p.unknown || 0, total) + '%');
    renderTrack('heroTrack', p.on || 0, devices.maintenance || 0, p.off || 0, p.unknown || 0, total);

    text('rtHeadline', 'เปิด ' + (p.on || 0) + ' · ปิด/ไม่ตอบ ' + (p.off || 0) + ' · ไม่ทราบ ' + (p.unknown || 0));
    text('rtSub', 'Remark ในระบบ ' + (devices.with_remark || 0) + ' เครื่อง');
    text('rtState', state.busy ? 'scanning' : 'ready');
    animateNumber('rtOn', p.on || 0);
    animateNumber('rtOff', (p.off || 0) + (p.unknown || 0));
    animateNumber('rtRemark', devices.with_remark || 0);
    text('rtOnSub', 'คิดเป็น ' + pct(p.on || 0, total) + '% ของทั้งหมด');
    text('rtOffSub', 'รวม off + unknown');
    text('rtTime', power.checked_at ? fmtTime(power.checked_at) : '-');
    text('rtTimeSub', power.checked_at ? fmtDateTime(power.checked_at) : 'ยังไม่มีผลสแกน');
    renderTrack('mainTrack', p.on || 0, devices.maintenance || 0, p.off || 0, p.unknown || 0, total);

    animateNumber('bizOpen', tickets.open || 0);
    animateNumber('bizProg', tickets.in_progress || 0);
    animateNumber('bizMail', emails.active || 0);
    animateNumber('bizAdmin', users.admins || 0);

    var bizItems = [
      { label: 'Open Tickets', count: tickets.open || 0 },
      { label: 'In Progress', count: tickets.in_progress || 0 },
      { label: 'Resolved', count: tickets.resolved || 0 },
      { label: 'High Priority', count: tickets.high || 0 },
      { label: 'Email Active', count: emails.active || 0 },
      { label: 'User Admins', count: users.admins || 0 }
    ];
    document.getElementById('bizBars').innerHTML = rankRows(bizItems, Math.max(1, tickets.total || emails.total || users.total || 1), true);

    var byType = (summary.by_type || []).map(function(item) {
      return { label: item.type || 'Unknown', count: item.cnt || 0 };
    });
    var byDept = (summary.by_dept || []).map(function(item) {
      return { label: item.dept || '-', count: item.cnt || 0 };
    });
    document.getElementById('typeList').innerHTML = rankRows(byType, byType.length ? byType[0].count || 1 : 1, false);
    document.getElementById('deptList').innerHTML = rankRows(byDept, byDept.length ? byDept[0].count || 1 : 1, true);
  }

  function renderFeed() {
    var wrap = document.getElementById('feedWrap');
    var items = sortFeed((state.power && state.power.items) ? state.power.items : []).slice(0, 12);
    text('feedCount', items.length);
    if (!items.length) {
      wrap.innerHTML = '<div class="empty">ยังไม่มีผลการเช็กสถานะเครื่อง</div>';
      return;
    }
    wrap.innerHTML = '<table class="feed"><thead><tr><th>Machine</th><th>สถานะสด</th><th>Maintenance</th><th>Target</th><th>Latency</th></tr></thead><tbody>'
      + items.map(function(item) {
        var maint = item.status === 'maintenance' ? '<span class="badge badge-maintenance">กำลังซ่อม</span>' : '—';
        return '<tr>'
          + '<td><div class="machine"><b>' + xss(item.computer_name || item.id || '-') + '</b><span>' + xss(item.type || '-') + ' · ' + xss(item.dept || '-') + '</span></div></td>'
          + '<td>' + powerBadge(item) + '</td>'
          + '<td>' + maint + '</td>'
          + '<td><span class="machine"><span>' + xss(item.target || '-') + '</span></span></td>'
          + '<td><span class="machine"><span>' + (item.elapsed_ms || item.elapsed_ms === 0 ? item.elapsed_ms + ' ms' : '-') + '</span></span></td>'
          + '</tr>';
      }).join('')
      + '</tbody></table>';
  }

  function renderAll() {
    renderSummary();
    renderFeed();
  }

  function scheduleRefresh() {
    clearTimeout(state.timer);
    state.timer = setTimeout(function() {
      refreshDashboard(false);
    }, REFRESH_MS);
  }

  async function refreshDashboard(showToastMessage) {
    if (state.busy) return;
    state.busy = true;
    setDB('wait', 'กำลังอัปเดต real-time...');
    document.getElementById('errBanner').style.display = 'none';

    try {
      var results = await Promise.allSettled([
        apiGet(API.dashboard),
        apiGet(API.devices, { action: 'power' })
      ]);

      if (results[0].status === 'fulfilled') state.summary = results[0].value;
      if (results[1].status === 'fulfilled') state.power = results[1].value;

      if (results[0].status !== 'fulfilled' && results[1].status !== 'fulfilled') {
        throw new Error('โหลดทั้งข้อมูลสรุปและสถานะสดไม่สำเร็จ');
      }

      renderAll();
      setDB('ok', state.power && state.power.checked_at ? 'Realtime · ' + fmtTime(state.power.checked_at) : 'Summary พร้อมใช้งาน');

      if (results[0].status !== 'fulfilled' || results[1].status !== 'fulfilled') {
        var parts = [];
        if (results[0].status !== 'fulfilled') parts.push('summary');
        if (results[1].status !== 'fulfilled') parts.push('real-time');
        var banner = document.getElementById('errBanner');
        banner.textContent = 'โหลดข้อมูลบางส่วนไม่สำเร็จ: ' + parts.join(', ');
        banner.style.display = 'block';
      } else if (showToastMessage) {
        showToast('อัปเดต Dashboard real-time แล้ว', 'success');
      }
    } catch (e) {
      setDB('err', 'เชื่อมต่อไม่ได้');
      var errorBanner = document.getElementById('errBanner');
      errorBanner.textContent = 'โหลด Dashboard ไม่สำเร็จ: ' + e.message;
      errorBanner.style.display = 'block';
      if (showToastMessage) showToast('โหลด Dashboard ไม่สำเร็จ', 'error');
    } finally {
      state.busy = false;
      scheduleRefresh();
    }
  }

  window.refreshDashboard = refreshDashboard;
  updateClock();
  setInterval(updateClock, 1000);
  refreshDashboard(false);
})();
