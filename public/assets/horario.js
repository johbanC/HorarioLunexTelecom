(function () {
  "use strict";

  var CFG = window.HORARIO || {};
  var EDITABLE = !!CFG.editable;
  var API = (CFG.apiBase || "/api").replace(/\/$/, "") + "/";
  var TOKEN = CFG.token || null;

  var MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
  var DIAS = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
  var DIAS_CORTOS = ["dom", "lun", "mar", "mié", "jue", "vie", "sáb"];

  var state = {
    teams: [],
    team: CFG.team || null,          // equipo actual (objeto de config)
    employees: [],                   // [{id,name,order}] del equipo actual
    monthKey: currentMonthKey(),
    monthData: { days: {} },         // { "YYYY-MM-DD": { empId: [ {dbId,start,end,breakMin,breakMode,lunchStart,cobro} ] } }
    ready: false
  };

  // ---------------- utilidades de fecha/hora ----------------
  function currentMonthKey(d) {
    d = d || new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
  }
  function pad(n) { return String(n).padStart(2, "0"); }
  function daysInMonth(key) {
    var y = +key.slice(0, 4), m = +key.slice(5, 7);
    return new Date(y, m, 0).getDate();
  }
  function dateStr(key, day) { return key + "-" + pad(day); }
  function weekdayOf(key, day) {
    var y = +key.slice(0, 4), m = +key.slice(5, 7);
    return new Date(y, m - 1, day).getDay();
  }
  function timeToMin(t) {
    if (!t) return null;
    var p = t.split(":"); return (+p[0]) * 60 + (+p[1]);
  }
  function minToTime(mins) {
    mins = ((mins % 1440) + 1440) % 1440;
    return pad(Math.floor(mins / 60)) + ":" + pad(mins % 60);
  }
  function grossMinutes(start, end) {
    var a = timeToMin(start), b = timeToMin(end);
    if (a == null || b == null) return 0;
    var mins = b - a;
    if (mins <= 0) mins += 24 * 60; // el turno cruza medianoche
    return mins;
  }
  function fmtH(h) {
    var r = Math.round(h * 100) / 100;
    return (r % 1 === 0) ? String(r) : r.toFixed(2).replace(/0$/, "").replace(/\.$/, "");
  }
  function esc(s) { return s == null ? "" : String(s); }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // ---------------- reglas del equipo ----------------
  function team() { return state.team || {}; }
  function isLunch() { return team().rule === "lunch"; }
  function breakPaid() { return !!team().break_paid; }
  function breakLen() { return +team().break_len_min || 15; }
  function breakEvery() { return +team().break_interval_min || 180; }
  function lunchLen() { return +team().lunch_min || 60; }

  // regla 'interval' (CSR): un descanso corto solo si, al completar el bloque,
  // todavía queda turno después (nunca justo al salir).
  function breakCountFor(total) {
    var every = breakEvery(), len = breakLen(), c = 0, k = 1;
    while (k * every + len <= total) { c++; k++; }
    return c;
  }
  function autoBreakMinutes(start, end) {
    return breakCountFor(grossMinutes(start, end)) * breakLen();
  }

  // Posición de los descansos dentro del turno (offset en minutos desde el inicio).
  function breakSlots(s) {
    var startAbs = timeToMin(s.start);
    var total = grossMinutes(s.start, s.end);
    if (total <= 0) return [];

    if (isLunch()) {
      if (!s.lunchStart) return [];
      var lu = timeToMin(s.lunchStart);
      var off = lu - startAbs;
      if (off < 0) off += 1440;
      var len = lunchLen();
      if (off <= 0 || off + len > total) return [];
      return [{ startOffset: off, endOffset: off + len, start: s.lunchStart, end: minToTime(lu + len) }];
    }

    var every = breakEvery(), blen = breakLen();
    var count = Math.round((s.breakMin || 0) / blen);
    var slots = [];
    for (var k = 1; k <= count; k++) {
      var bStart = k * every;
      if (bStart + blen > total) break;
      slots.push({ startOffset: bStart, endOffset: bStart + blen, start: minToTime(startAbs + bStart), end: minToTime(startAbs + bStart + blen) });
    }
    return slots;
  }

  // Horas pagadas: el descanso de CSR NO se descuenta; el almuerzo de
  // Contabilidad SÍ (según team.break_paid).
  function paidHours(s) {
    var gross = grossMinutes(s.start, s.end);
    var mins = breakPaid() ? gross : gross - (s.breakMin || 0);
    if (mins < 0) mins = 0;
    return mins / 60;
  }

  // Segmentos trabajo/descanso para la línea de tiempo del turno.
  function shiftTimeline(s) {
    var total = grossMinutes(s.start, s.end);
    if (total <= 0) return [];
    var brks = breakSlots(s).slice().sort(function (a, b) { return a.startOffset - b.startOffset; });
    var segs = [], cursor = 0;
    brks.forEach(function (b) {
      if (b.startOffset > cursor) segs.push({ type: "work", pct: (b.startOffset - cursor) / total * 100 });
      segs.push({ type: "brk", pct: (b.endOffset - b.startOffset) / total * 100 });
      cursor = b.endOffset;
    });
    if (cursor < total) segs.push({ type: "work", pct: (total - cursor) / total * 100 });
    return segs;
  }
  function timelineHtml(s, big) {
    var cls = s.cobro === "posterior" ? "posterior" : "anticipado";
    var segs = shiftTimeline(s);
    var html = '<div class="timeline' + (big ? ' lg' : '') + '">';
    segs.forEach(function (seg) {
      html += '<div class="seg ' + (seg.type === "brk" ? "brk" : "work " + cls) + '" style="width:' + seg.pct + '%"></div>';
    });
    html += '</div>';
    return html;
  }

  // Divide una celda de una hora en tramos proporcionales (trabajo/descanso/nada).
  function hourCellSegments(cellStart, cellEnd, sMin, sEnd, brks) {
    var points = [cellStart, cellEnd, sMin, sEnd];
    brks.forEach(function (b) { points.push(sMin + b.startOffset, sMin + b.endOffset); });
    points = points.filter(function (p) { return p > cellStart && p < cellEnd; });
    points.push(cellStart, cellEnd);
    points = Array.from(new Set(points)).sort(function (a, b) { return a - b; });
    var segs = [];
    for (var i = 0; i < points.length - 1; i++) {
      var a = points[i], b = points[i + 1];
      if (b <= a) continue;
      var mid = (a + b) / 2;
      var isBrk = brks.some(function (br) { return mid >= sMin + br.startOffset && mid < sMin + br.endOffset; });
      var isWork = !isBrk && mid >= sMin && mid < sEnd;
      segs.push({ type: isBrk ? "brk" : (isWork ? "work" : "off"), pct: (b - a) / (cellEnd - cellStart) * 100 });
    }
    return segs;
  }

  var EMP_HUES = [142, 208, 28, 352, 285, 190, 48, 322, 96, 4];
  function employeeColor(index) {
    var hue = EMP_HUES[((index % EMP_HUES.length) + EMP_HUES.length) % EMP_HUES.length];
    return { bg: 'hsl(' + hue + ',56%,40%)', fg: '#ffffff' };
  }

  function hourRange() {
    var minH = 8, maxH = 23;
    var days = state.monthData.days || {};
    Object.keys(days).forEach(function (dk) {
      var dayObj = days[dk] || {};
      Object.keys(dayObj).forEach(function (eid) {
        (dayObj[eid] || []).forEach(function (s) {
          var a = timeToMin(s.start);
          if (a == null) return;
          var total = grossMinutes(s.start, s.end);
          var startH = Math.floor(a / 60);
          var endH = Math.ceil((a + total) / 60);
          if (startH < minH) minH = Math.max(0, startH);
          if (endH > maxH) maxH = Math.min(24, endH);
        });
      });
    });
    return { start: minH, end: maxH };
  }

  // ---------------- API ----------------
  function setStatus(text, cls) {
    var st = document.getElementById("statusText");
    if (st) st.textContent = text;
    var dot = document.getElementById("statusDot");
    if (!dot) return;
    dot.classList.remove("live", "error");
    if (cls) dot.classList.add(cls);
  }
  function handle(res) {
    if (!res.ok) {
      return res.json().then(function (e) { throw new Error(e.error || ("HTTP " + res.status)); })
        .catch(function (err) { throw (err instanceof Error ? err : new Error("HTTP " + res.status)); });
    }
    return res.json();
  }
  function apiGet(path) { return fetch(API + path).then(handle); }
  function apiSend(path, method, body) {
    return fetch(API + path, {
      method: method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {})
    }).then(handle);
  }
  function apiDelete(path) { return fetch(API + path, { method: "DELETE" }).then(handle); }

  function buildDaysFromRows(rows) {
    var days = {};
    rows.forEach(function (r) {
      var d = r.work_date, eid = String(r.employee_id);
      if (!days[d]) days[d] = {};
      if (!days[d][eid]) days[d][eid] = [];
      days[d][eid].push({
        dbId: r.id,
        start: r.start_time,
        end: r.end_time,
        breakMin: +r.break_min,
        breakMode: r.break_mode,
        lunchStart: r.lunch_start || null,
        cobro: r.cobro
      });
    });
    state.monthData = { days: days };
  }

  function mapEmployees(data) {
    return data
      .map(function (e) { return { id: String(e.id), name: e.name, order: e.sort_order }; })
      .sort(function (a, b) { return (a.order - b.order) || a.name.localeCompare(b.name); });
  }

  async function loadTeams() {
    state.teams = await apiGet("teams");
    if (state.team) {
      var cur = state.teams.find(function (t) { return t.id === state.team.id; });
      state.team = cur || state.teams[0] || null;
    } else {
      state.team = state.teams[0] || null;
    }
  }
  async function loadEmployees() {
    if (!state.team) { state.employees = []; return; }
    state.employees = mapEmployees(await apiGet("employees?team=" + state.team.id));
  }
  async function loadMonth(key) {
    if (!state.team) { state.monthData = { days: {} }; return; }
    var rows = await apiGet("shifts?month=" + encodeURIComponent(key) + "&team=" + state.team.id);
    buildDaysFromRows(rows);
  }
  async function loadReadOnly() {
    var res = await apiGet("ver/" + encodeURIComponent(TOKEN) + "/data?month=" + encodeURIComponent(state.monthKey));
    state.team = res.team;
    state.employees = mapEmployees(res.employees);
    buildDaysFromRows(res.shifts);
  }

  var pollTimer = null;
  async function init() {
    try {
      if (EDITABLE) {
        await loadTeams();
        await loadEmployees();
        await loadMonth(state.monthKey);
      } else {
        await loadReadOnly();
      }
      state.ready = true;
      setStatus("Conectado", "live");
      renderAll();
    } catch (e) {
      console.error("init error", e);
      setStatus("Sin conexión al servidor", "error");
      renderAll();
    }
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshQuiet, 20000);
    window.addEventListener("focus", refreshQuiet);
  }
  async function refreshQuiet() {
    try {
      if (EDITABLE) {
        await loadTeams();
        await loadEmployees();
        await loadMonth(state.monthKey);
      } else {
        await loadReadOnly();
      }
      setStatus("Conectado", "live");
      renderAll();
    } catch (e) {
      console.error("refresh error", e);
      setStatus("Sin conexión al servidor", "error");
    }
  }

  // ---------------- render ----------------
  function renderAll() {
    renderMonthLabel();
    renderTeamStrip();
    renderStats();
    renderGrid();
  }

  function renderMonthLabel() {
    var y = +state.monthKey.slice(0, 4), m = +state.monthKey.slice(5, 7);
    var el = document.getElementById("monthLabel");
    if (el) el.textContent = MESES[m - 1] + " " + y;
  }

  function ruleLabel(t) {
    return t.rule === "lunch"
      ? (t.lunch_min + " min almuerzo · se descuenta")
      : (t.break_len_min + " min cada " + fmtH(t.break_interval_min / 60) + "h" + (t.break_paid ? "" : " · se descuenta"));
  }

  function renderTeamStrip() {
    var tabs = document.getElementById("teamTabs");
    if (!tabs) return;

    if (!EDITABLE) {
      tabs.className = "";
      tabs.innerHTML = '<div class="ro-banner">👁️ Solo lectura · Horario del equipo ' +
        escapeHtml(team().name || "") + ' — se actualiza solo cada 20 s</div>';
      return;
    }

    tabs.className = "team-tabs";
    var html = "";
    state.teams.forEach(function (t) {
      var active = state.team && t.id === state.team.id;
      html += '<button class="team-tab' + (active ? ' active' : '') + '" data-team="' + t.id + '">' +
        escapeHtml(t.name) + ' <span class="rule">' + escapeHtml(ruleLabel(t)) + '</span></button>';
    });
    html += '<button class="team-tab manage" id="manageTeamsBtn">⚙ Equipos</button>';
    tabs.innerHTML = html;

    tabs.querySelectorAll("[data-team]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = +btn.getAttribute("data-team");
        if (state.team && id === state.team.id) return;
        state.team = state.teams.find(function (t) { return t.id === id; }) || state.team;
        state.employees = [];
        state.monthData = { days: {} };
        renderAll();
        Promise.all([loadEmployees(), loadMonth(state.monthKey)]).then(renderAll).catch(function (e) {
          console.error(e); setStatus("Sin conexión al servidor", "error");
        });
      });
    });
    var mt = document.getElementById("manageTeamsBtn");
    if (mt) mt.addEventListener("click", openTeamEditor);
  }

  function computeTotals() {
    var perEmp = {};
    state.employees.forEach(function (e) { perEmp[e.id] = 0; });
    var perDay = {};
    var anticipado = 0, posterior = 0, total = 0;
    var days = state.monthData.days || {};
    Object.keys(days).forEach(function (dateK) {
      var dayObj = days[dateK] || {};
      var dayTotal = 0;
      Object.keys(dayObj).forEach(function (empId) {
        (dayObj[empId] || []).forEach(function (s) {
          var h = paidHours(s);
          dayTotal += h; total += h;
          if (perEmp[empId] != null) perEmp[empId] += h; else perEmp[empId] = h;
          if (s.cobro === "posterior") posterior += h; else anticipado += h;
        });
      });
      perDay[dateK] = dayTotal;
    });
    return { perEmp: perEmp, perDay: perDay, anticipado: anticipado, posterior: posterior, total: total };
  }

  function statTile(label, value, accent, tagColor) {
    return '<div class="stat' + (accent ? ' accent' : '') + '"><div class="label">' +
      (tagColor ? '<span class="tag" style="background:' + tagColor + '"></span>' : '') +
      escapeHtml(label) + '</div><div class="value">' + value + '</div></div>';
  }
  function renderStats() {
    var t = computeTotals();
    var el = document.getElementById("stats");
    if (!el) return;
    var html = "";
    html += statTile("Total del mes", fmtH(t.total) + "h", true);
    state.employees.forEach(function (e) {
      html += statTile(e.name, fmtH(t.perEmp[e.id] || 0) + "h", false);
    });
    html += statTile("Cobro anticipado", fmtH(t.anticipado) + "h", false, "var(--accent)");
    html += statTile("Cobro posterior", fmtH(t.posterior) + "h", false, "var(--warn)");
    el.innerHTML = html;
  }

  function empIndex(empId) {
    for (var i = 0; i < state.employees.length; i++) if (state.employees[i].id === empId) return i;
    return 0;
  }

  function renderGrid() {
    var table = document.getElementById("schedTable");
    if (!table) return;
    var nDays = daysInMonth(state.monthKey);
    var t = computeTotals();
    var hr = hourRange();

    var thead = '<thead><tr><th class="date-col">Fecha</th><th class="emp-col">Empleado</th>';
    for (var h = hr.start; h < hr.end; h++) { thead += '<th class="hour-col">' + pad(h) + ':00</th>'; }
    thead += '<th class="total-col">Turno</th><th class="total-col day-total">Total día</th></tr></thead>';

    var body = "<tbody>";
    for (var day = 1; day <= nDays; day++) {
      var dk = dateStr(state.monthKey, day);
      var wd = weekdayOf(state.monthKey, day);
      var isWeekend = (wd === 0 || wd === 6);
      var rowClass = isWeekend ? "weekend" : "";

      var rows = [];
      state.employees.forEach(function (e) {
        var shifts = (state.monthData.days[dk] && state.monthData.days[dk][e.id]) || [];
        shifts.forEach(function (s, idx) { rows.push({ emp: e, shift: s, idx: idx }); });
      });
      var rowSpan = rows.length + (EDITABLE ? 1 : (rows.length === 0 ? 1 : 0));
      if (rowSpan < 1) rowSpan = 1;

      var dayTotal = t.perDay[dk] || 0;
      var dateCell = '<td class="date-col" rowspan="' + rowSpan + '"><div class="d">' + day + '</div><div class="w">' + DIAS_CORTOS[wd] + '</div>' +
        '<div class="daytot">' + fmtH(dayTotal) + 'h</div></td>';
      var dayTotalCell = '<td class="total-col day-total' + (dayTotal === 0 ? ' zero' : '') + '" rowspan="' + rowSpan + '">' + fmtH(dayTotal) + 'h</td>';

      var editAttr = EDITABLE ? ' data-edit-shift="1"' : '';

      rows.forEach(function (r, i) {
        var col = employeeColor(empIndex(r.emp.id));
        var cobroColor = r.shift.cobro === "posterior" ? "var(--warn)" : "var(--accent)";
        body += '<tr class="' + rowClass + '">';
        if (i === 0) body += dateCell;
        body += '<td class="emp-col"' + editAttr + ' data-date="' + dk + '" data-emp="' + r.emp.id + '" data-idx="' + r.idx + '" style="border-left:4px solid ' + cobroColor + ';">' +
          '<div class="emp-name">' + escapeHtml(r.emp.name) + '</div>' +
          '<div class="emp-time mono">' + esc(r.shift.start) + '–' + esc(r.shift.end) +
          (EDITABLE ? ' <button class="add-more" data-add-emp="' + r.emp.id + '" data-add-date="' + dk + '" title="Agregar otro turno a ' + escapeHtml(r.emp.name) + ' ese día">＋ turno</button>' : '') +
          '</div>' +
          '</td>';
        var sMin = timeToMin(r.shift.start);
        var totalMin = grossMinutes(r.shift.start, r.shift.end);
        var sEnd = sMin + totalMin;
        var brks = breakSlots(r.shift);
        for (var hh = hr.start; hh < hr.end; hh++) {
          var cs = hh * 60, ce = hh * 60 + 60;
          var segs = hourCellSegments(cs, ce, sMin, sEnd, brks);
          var brkInCell = segs.some(function (sg) { return sg.type === "brk"; });
          body += '<td class="hcell" title="' + (brkInCell ? (isLunch() ? "Almuerzo" : "Descanso") : "") + '"' + editAttr + ' data-date="' + dk + '" data-emp="' + r.emp.id + '" data-idx="' + r.idx + '">';
          body += '<div class="hbar">';
          segs.forEach(function (sg) {
            var style = 'width:' + sg.pct + '%;';
            if (sg.type === "work") style += 'background:' + col.bg + ';';
            body += '<div class="hseg ' + sg.type + '" style="' + style + '"></div>';
          });
          body += '</div></td>';
        }
        body += '<td class="total-col">' + fmtH(paidHours(r.shift)) + 'h</td>';
        if (i === 0) body += dayTotalCell;
        body += '</tr>';
      });

      if (EDITABLE) {
        body += '<tr class="' + rowClass + ' add-row" data-new-date="' + dk + '">';
        if (rows.length === 0) body += dateCell;
        body += '<td class="add-cell" colspan="' + (2 + (hr.end - hr.start)) + '">+ agregar turno</td>';
        if (rows.length === 0) body += dayTotalCell;
        body += '</tr>';
      } else if (rows.length === 0) {
        body += '<tr class="' + rowClass + '">' + dateCell +
          '<td class="add-cell" colspan="' + (2 + (hr.end - hr.start)) + '" style="color:var(--muted);">Sin turnos</td>' +
          dayTotalCell + '</tr>';
      }
    }
    body += "</tbody>";

    var tfoot = '<tfoot><tr><td class="foot-label" colspan="2">Total mes</td>';
    for (var hh2 = hr.start; hh2 < hr.end; hh2++) { tfoot += '<td></td>'; }
    tfoot += '<td colspan="2">' + fmtH(t.total) + 'h</td></tr></tfoot>';

    table.innerHTML = thead + body + tfoot;

    if (!EDITABLE) return;
    table.querySelectorAll("[data-edit-shift]").forEach(function (td) {
      td.addEventListener("click", function () {
        openShiftEditor(td.getAttribute("data-date"), td.getAttribute("data-emp"), +td.getAttribute("data-idx"));
      });
    });
    table.querySelectorAll("tr.add-row[data-new-date]").forEach(function (tr) {
      tr.addEventListener("click", function () {
        openShiftEditor(tr.getAttribute("data-new-date"), null, -1);
      });
    });
    table.querySelectorAll("[data-add-emp]").forEach(function (btn) {
      btn.addEventListener("click", function (ev) {
        ev.stopPropagation();
        openShiftEditor(btn.getAttribute("data-add-date"), btn.getAttribute("data-add-emp"), -1);
      });
    });
  }

  // ---------------- editor de turno ----------------
  function defaultLunchStart(s) {
    var startAbs = timeToMin(s.start);
    var total = grossMinutes(s.start, s.end);
    var off = Math.max(0, Math.round((total - lunchLen()) / 2));
    return minToTime(startAbs + off);
  }

  function openShiftEditor(dateK, empId, shiftIdx) {
    if (!EDITABLE) return;
    if (state.employees.length === 0) { alert("Primero agrega al menos un empleado a este equipo (botón 👤 Empleados)."); return; }
    var isNew = !empId || shiftIdx == null || shiftIdx < 0;
    var s;
    if (!isNew) {
      var src = (state.monthData.days[dateK] && state.monthData.days[dateK][empId] && state.monthData.days[dateK][empId][shiftIdx]);
      s = src ? Object.assign({}, src) : { start: "08:00", end: "17:00", cobro: "anticipado" };
    } else {
      s = { start: "08:00", end: "17:00", cobro: "anticipado" };
      // Turno adicional ("está cubriendo horas"): arranca donde terminó el último
      // turno de esa persona ese día.
      var mine = (empId && state.monthData.days[dateK] && state.monthData.days[dateK][empId]) || [];
      if (mine.length) {
        var lastEnd = mine.reduce(function (a, x) { return timeToMin(x.end) > timeToMin(a) ? x.end : a; }, mine[0].end);
        s.start = lastEnd;
        s.end = minToTime(timeToMin(lastEnd) + 180);
        s.cobro = mine[mine.length - 1].cobro || "anticipado";
      }
    }
    if (!s.breakMode) s.breakMode = "auto";

    if (isLunch()) {
      s.breakMode = "manual";
      if (!s.lunchStart) s.lunchStart = defaultLunchStart(s);
      s.breakMin = lunchLen();
    } else if (s.breakMode === "auto") {
      s.breakMin = autoBreakMinutes(s.start, s.end);
    }

    var takenIds = Object.keys((state.monthData.days[dateK] || {})).filter(function (id) { return (state.monthData.days[dateK][id] || []).length; });
    var defaultEmp = empId || (state.employees.find(function (e) { return takenIds.indexOf(e.id) === -1; }) || state.employees[0]).id;
    var selectedEmp = defaultEmp;

    var y = +dateK.slice(0, 4), m = +dateK.slice(5, 7), d = +dateK.slice(8, 10);
    var wd = new Date(y, m - 1, d).getDay();
    var dateLabel = DIAS[wd] + " " + d + " de " + MESES[m - 1];

    var root = document.getElementById("modalRoot");

    function breakBlock() {
      if (isLunch()) {
        return '<div class="field-row">' +
          '<div class="field"><label>Inicio almuerzo</label><input type="time" id="fLunch" value="' + esc(s.lunchStart) + '"></div>' +
          '<div class="field"><label>Horas pagadas</label><input type="text" class="mono" value="' + fmtH(paidHours(s)) + 'h" disabled></div>' +
          '</div>' +
          '<div class="hint">Almuerzo de ' + lunchLen() + ' min · se descuenta del pago</div>';
      }
      return '<div class="field-row">' +
        '<div class="field"><label>Descanso (min)' + (s.breakMode === "auto" ? ' · auto' : '') + '</label><input type="number" min="0" step="5" id="fBreak" value="' + (s.breakMin || 0) + '"' + (s.breakMode === "auto" ? ' disabled' : '') + '></div>' +
        '<div class="field"><label>Horas pagadas</label><input type="text" class="mono" value="' + fmtH(paidHours(s)) + 'h" disabled></div>' +
        '</div>' +
        '<button type="button" class="btn ghost small" style="padding:4px 0 0;" id="toggleBreak">' +
        (s.breakMode === "auto" ? "Ajustar descanso manualmente" : "Usar regla automática (" + breakLen() + " min cada " + fmtH(breakEvery() / 60) + "h)") + '</button>';
    }
    function brkLabelHtml() {
      var slots = breakSlots(s);
      if (isLunch()) {
        return slots.length
          ? '<div class="brk-label" style="margin-top:6px;"><span class="dash"></span>Almuerzo: ' + slots[0].start + '–' + slots[0].end + ' · se descuenta del pago</div>'
          : '<div class="brk-label" style="margin-top:6px;">El almuerzo no cabe dentro del turno.</div>';
      }
      return slots.length
        ? '<div class="brk-label" style="margin-top:6px;"><span class="dash"></span>Descanso: ' + slots.map(function (b) { return b.start + '–' + b.end; }).join(', ') + ' · no se descuenta del pago</div>'
        : '<div class="brk-label" style="margin-top:6px;">Sin descanso (turno corto)</div>';
    }

    function overlapNote() {
      var mine = (state.monthData.days[dateK] && state.monthData.days[dateK][selectedEmp]) || [];
      var aS = timeToMin(s.start), aE = timeToMin(s.end);
      if (aE <= aS) aE += 1440;
      var hit = mine.filter(function (x, i) {
        if (!isNew && selectedEmp === empId && i === shiftIdx) return false; // el mismo turno que se edita
        var bS = timeToMin(x.start), bE = timeToMin(x.end);
        if (bE <= bS) bE += 1440;
        return aS < bE && bS < aE;
      });
      if (!hit.length) return '';
      return '<div class="brk-label" style="margin-top:6px;color:var(--warn);">⚠ Se cruza con otro turno de esta persona: ' +
        hit.map(function (x) { return x.start + '–' + x.end; }).join(', ') + '. Se contará doble en las horas.</div>';
    }

    function repeatBlock() {
      var boxes = WEEK_PICK.map(function (w) {
        var pre = (w.d >= 1 && w.d <= 5);
        return '<label style="font-size:11px;display:inline-flex;align-items:center;gap:3px;margin-right:6px;">' +
          '<input type="checkbox" data-rep-day="' + w.d + '"' + (pre ? " checked" : "") + '> ' + w.l + '</label>';
      }).join("");
      return '<details class="team-rule-fields" style="margin-top:10px;"><summary style="cursor:pointer;font-size:12.5px;font-weight:600;">Repetir este turno / guardarlo como plantilla</summary>' +
        '<div class="hint" style="margin-top:8px;">Repetir en ' + escapeHtml(monthLabelText()) + ', los días:</div>' +
        '<div style="margin:6px 0;">' + boxes + '</div>' +
        '<button type="button" class="btn small" id="repeatBtn">Repetir en el mes</button>' +
        '<div id="repeatOut" class="hint"></div>' +
        '<div class="hint" style="margin-top:10px;">Guardar estas horas como plantilla fija de este empleado:</div>' +
        '<div class="share-box" style="margin-top:4px;">' +
        '<button type="button" class="btn small ghost" data-savetpl="weekday">↳ entre semana</button>' +
        '<button type="button" class="btn small ghost" data-savetpl="weekend">↳ fin de semana</button>' +
        '</div><div id="tplOut" class="hint"></div>' +
        '</details>';
    }

    function draw() {
      var html = '<div class="overlay" id="ovl"><div class="modal">' +
        '<h3>' + (isNew ? "Nuevo turno" : "Editar turno") + '</h3>' +
        '<div class="modal-sub">' + escapeHtml(dateLabel) + ' · ' + escapeHtml(team().name || "") + '</div>' +
        '<div class="field" style="margin-bottom:10px;"><label>Empleado</label>' +
        '<select id="empSelect">' + state.employees.map(function (e) {
          return '<option value="' + e.id + '"' + (e.id === selectedEmp ? ' selected' : '') + '>' + escapeHtml(e.name) + '</option>';
        }).join('') + '</select>' +
        '</div>' +
        '<div class="field-row">' +
        '<div class="field"><label>Entrada</label><input type="time" id="fStart" value="' + esc(s.start) + '"></div>' +
        '<div class="field"><label>Salida</label><input type="time" id="fEnd" value="' + esc(s.end) + '"></div>' +
        '</div>' +
        breakBlock() +
        timelineHtml(s, true) +
        brkLabelHtml() +
        overlapNote() +
        '<div class="cobro-toggle" style="margin-top:12px;">' +
        '<button type="button" class="' + (s.cobro !== "posterior" ? "active anticipado" : "") + '" data-cobro="anticipado">Cobro anticipado</button>' +
        '<button type="button" class="' + (s.cobro === "posterior" ? "active posterior" : "") + '" data-cobro="posterior">Cobro posterior</button>' +
        '</div>' +
        repeatBlock() +
        '<div class="modal-actions">' +
        (isNew ? '<div></div>' : '<button class="btn danger" id="removeShift">Eliminar turno</button>') +
        '<div class="right"><button class="btn ghost" id="cancelBtn">Cancelar</button><button class="btn primary" id="saveBtn">Guardar</button></div>' +
        '</div>' +
        '</div></div>';
      root.innerHTML = html;

      document.getElementById("empSelect").addEventListener("change", function () { selectedEmp = this.value; });
      document.getElementById("fStart").addEventListener("input", function () {
        s.start = this.value;
        if (isLunch()) { s.lunchStart = defaultLunchStart(s); s.breakMin = lunchLen(); }
        else if (s.breakMode !== "manual") s.breakMin = autoBreakMinutes(s.start, s.end);
        draw();
      });
      document.getElementById("fEnd").addEventListener("input", function () {
        s.end = this.value;
        if (isLunch()) { s.breakMin = lunchLen(); }
        else if (s.breakMode !== "manual") s.breakMin = autoBreakMinutes(s.start, s.end);
        draw();
      });
      var fLunch = document.getElementById("fLunch");
      if (fLunch) fLunch.addEventListener("input", function () { s.lunchStart = this.value; draw(); });
      var fBreak = document.getElementById("fBreak");
      if (fBreak) fBreak.addEventListener("input", function () { s.breakMin = (+this.value || 0); draw(); });
      var tgl = document.getElementById("toggleBreak");
      if (tgl) tgl.addEventListener("click", function () {
        if (s.breakMode === "auto") { s.breakMode = "manual"; }
        else { s.breakMode = "auto"; s.breakMin = autoBreakMinutes(s.start, s.end); }
        draw();
      });
      root.querySelectorAll("[data-cobro]").forEach(function (btn) {
        btn.addEventListener("click", function () { s.cobro = btn.getAttribute("data-cobro"); draw(); });
      });

      var repeatBtn = document.getElementById("repeatBtn");
      if (repeatBtn) repeatBtn.addEventListener("click", function () {
        var days = Array.prototype.map.call(root.querySelectorAll("[data-rep-day]:checked"), function (c) { return +c.getAttribute("data-rep-day"); });
        var out = document.getElementById("repeatOut");
        if (!days.length) { out.textContent = "Marca al menos un día."; return; }
        if (!s.start || !s.end) { out.textContent = "Falta entrada o salida."; return; }
        out.textContent = "Repitiendo…";
        apiSend("shifts/repeat", "POST", {
          employee_id: +selectedEmp, month: state.monthKey, weekdays: days,
          start_time: s.start, end_time: s.end,
          lunch_start: isLunch() ? (s.lunchStart || null) : null,
          cobro: s.cobro === "posterior" ? "posterior" : "anticipado"
        }).then(function (r) {
          out.textContent = "Listo: " + r.created + " creado(s), " + r.skipped + " omitido(s).";
          return loadMonth(state.monthKey);
        }).then(function () { renderAll(); }).catch(function (e) { out.textContent = ""; alert("No se pudo repetir: " + e.message); });
      });

      root.querySelectorAll("[data-savetpl]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var out = document.getElementById("tplOut");
          out.textContent = "Guardando…";
          apiSend("templates", "POST", {
            employee_id: +selectedEmp, kind: btn.getAttribute("data-savetpl"),
            start_time: s.start, end_time: s.end,
            lunch_start: isLunch() ? (s.lunchStart || null) : null,
            cobro: s.cobro === "posterior" ? "posterior" : "anticipado", active: true
          }).then(function () { out.textContent = "Plantilla guardada."; })
            .catch(function (e) { out.textContent = ""; alert("No se pudo guardar la plantilla: " + e.message); });
        });
      });

      document.getElementById("cancelBtn").addEventListener("click", closeModal);
      document.getElementById("ovl").addEventListener("click", function (ev) { if (ev.target.id === "ovl") closeModal(); });

      var rm = document.getElementById("removeShift");
      if (rm) rm.addEventListener("click", function () {
        apiDelete("shifts?id=" + encodeURIComponent(s.dbId))
          .then(function () { return loadMonth(state.monthKey); })
          .then(function () { renderAll(); closeModal(); })
          .catch(function (err) { console.error(err); alert("No se pudo eliminar el turno: " + err.message); });
      });

      document.getElementById("saveBtn").addEventListener("click", function () {
        if (!s.start || !s.end) { alert("Falta la hora de entrada o salida."); return; }
        var payload = {
          employee_id: +selectedEmp,
          work_date: dateK,
          start_time: s.start,
          end_time: s.end,
          break_min: s.breakMin || 0,
          break_mode: isLunch() ? "manual" : (s.breakMode || "auto"),
          lunch_start: isLunch() ? (s.lunchStart || null) : null,
          cobro: s.cobro === "posterior" ? "posterior" : "anticipado"
        };
        var req;
        if (isNew) {
          req = apiSend("shifts", "POST", payload);
        } else {
          payload.id = s.dbId;
          req = apiSend("shifts", "PUT", payload);
        }
        req.then(function () { return loadMonth(state.monthKey); })
          .then(function () { renderAll(); closeModal(); })
          .catch(function (err) { console.error(err); alert("No se pudo guardar el turno: " + err.message); });
      });
    }
    draw();
  }

  function closeModal() { document.getElementById("modalRoot").innerHTML = ""; }

  // ---------------- empleados (del equipo actual) ----------------
  function openEmployeeEditor() {
    if (!EDITABLE || !state.team) return;
    var root = document.getElementById("modalRoot");
    function draw() {
      var html = '<div class="overlay" id="ovl2"><div class="modal">' +
        '<h3>Empleados · ' + escapeHtml(team().name) + '</h3>' +
        '<div class="modal-sub">Guardado en la base de datos</div>' +
        '<div id="empRows"></div>' +
        '<input type="text" class="plain" id="newEmpName" placeholder="Nombre del nuevo empleado" style="margin-top:10px;">' +
        '<button class="btn primary small" id="addEmpBtn">+ Agregar empleado</button>' +
        '<div class="modal-actions"><div></div><div class="right"><button class="btn ghost" id="closeEmpBtn">Cerrar</button></div></div>' +
        '</div></div>';
      root.innerHTML = html;

      var rows = document.getElementById("empRows");
      rows.innerHTML = state.employees.map(function (e) {
        return '<div class="emp-list-row"><input class="rename" data-id="' + e.id + '" type="text" value="' + escapeHtml(e.name) + '">' +
          '<button class="btn small danger" data-del="' + e.id + '">Eliminar</button></div>';
      }).join("") || '<div class="modal-sub">Sin empleados todavía.</div>';

      rows.querySelectorAll("input.rename").forEach(function (inp) {
        inp.addEventListener("change", function () { renameEmployee(inp.getAttribute("data-id"), inp.value.trim()); });
      });
      rows.querySelectorAll("[data-del]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var id = btn.getAttribute("data-del");
          var emp = state.employees.find(function (e) { return e.id === id; });
          if (confirm('¿Eliminar a "' + (emp ? emp.name : "") + '"? También se eliminarán todos sus turnos guardados.')) {
            removeEmployee(id).then(draw);
          }
        });
      });
      document.getElementById("addEmpBtn").addEventListener("click", function () {
        var v = document.getElementById("newEmpName").value.trim();
        if (!v) return;
        addEmployee(v).then(draw);
      });
      document.getElementById("closeEmpBtn").addEventListener("click", closeModal2);
      document.getElementById("ovl2").addEventListener("click", function (ev) { if (ev.target.id === "ovl2") closeModal2(); });
    }
    draw();
  }
  function closeModal2() { document.getElementById("modalRoot").innerHTML = ""; renderAll(); }

  async function addEmployee(name) {
    try {
      await apiSend("employees", "POST", { name: name, team_id: state.team.id });
      await loadEmployees();
    } catch (e) { console.error(e); alert("No se pudo agregar el empleado: " + e.message); }
  }
  async function renameEmployee(id, name) {
    if (!name) return;
    try {
      await apiSend("employees", "PUT", { id: +id, name: name });
      await loadEmployees();
    } catch (e) { console.error(e); alert("No se pudo renombrar: " + e.message); }
  }
  async function removeEmployee(id) {
    try {
      await apiDelete("employees?id=" + encodeURIComponent(id));
      await loadEmployees();
      await loadMonth(state.monthKey);
    } catch (e) { console.error(e); alert("No se pudo eliminar: " + e.message); }
  }

  // ---------------- equipos ----------------
  function openTeamEditor() {
    if (!EDITABLE) return;
    var root = document.getElementById("modalRoot");

    function paramFields(t) {
      if (t.rule === "lunch") {
        return '<div class="team-rule-fields">' +
          '<div class="field-row">' +
          '<div class="field"><label>Almuerzo (min)</label><input type="number" min="0" step="15" data-f="lunch_min" value="' + (t.lunch_min || 60) + '"></div>' +
          '<div class="field"><label>¿Se paga?</label><select data-f="break_paid"><option value="0"' + (t.break_paid ? "" : " selected") + '>No, se descuenta</option><option value="1"' + (t.break_paid ? " selected" : "") + '>Sí, se paga</option></select></div>' +
          '</div></div>';
      }
      return '<div class="team-rule-fields">' +
        '<div class="field-row">' +
        '<div class="field"><label>Descanso (min)</label><input type="number" min="0" step="5" data-f="break_len_min" value="' + (t.break_len_min || 15) + '"></div>' +
        '<div class="field"><label>Cada (min)</label><input type="number" min="30" step="30" data-f="break_interval_min" value="' + (t.break_interval_min || 180) + '"></div>' +
        '</div>' +
        '<div class="field"><label>¿Se paga?</label><select data-f="break_paid"><option value="1"' + (t.break_paid ? " selected" : "") + '>Sí, se paga</option><option value="0"' + (t.break_paid ? "" : " selected") + '>No, se descuenta</option></select></div>' +
        '</div>';
    }

    function teamCard(t) {
      var link = location.origin + "/ver/" + t.share_token;
      return '<div class="team-rule-fields" data-team-card="' + t.id + '" style="background:var(--surface);">' +
        '<div class="field-row">' +
        '<div class="field"><label>Nombre</label><input type="text" data-f="name" value="' + escapeHtml(t.name) + '"></div>' +
        '<div class="field"><label>Regla</label><select data-f="rule"><option value="interval"' + (t.rule === "interval" ? " selected" : "") + '>Descansos cortos (CSR)</option><option value="lunch"' + (t.rule === "lunch" ? " selected" : "") + '>Hora de almuerzo</option></select></div>' +
        '</div>' +
        '<div data-params="1">' + paramFields(t) + '</div>' +
        '<label style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Enlace para el equipo (solo lectura)</label>' +
        '<div class="share-box"><input type="text" readonly value="' + escapeHtml(link) + '" data-link="1">' +
        '<button class="btn small" data-copy="1">Copiar</button>' +
        '<button class="btn small ghost" data-regen="' + t.id + '">Regenerar</button></div>' +
        '<div class="modal-actions"><button class="btn danger small" data-delteam="' + t.id + '">Eliminar equipo</button>' +
        '<div class="right"><button class="btn primary small" data-saveteam="' + t.id + '">Guardar cambios</button></div></div>' +
        '</div>';
    }

    function draw() {
      var html = '<div class="overlay" id="ovl3"><div class="modal" style="max-width:520px;">' +
        '<h3>Equipos / horarios</h3>' +
        '<div class="modal-sub">Cada equipo tiene su propia regla de descanso y su enlace</div>' +
        '<div id="teamCards">' + state.teams.map(teamCard).join("") + '</div>' +
        '<div class="team-rule-fields" style="margin-top:14px;">' +
        '<label style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Nuevo equipo</label>' +
        '<div class="field-row" style="margin-top:6px;">' +
        '<div class="field"><input type="text" id="newTeamName" placeholder="Nombre (ej. Ventas)"></div>' +
        '<div class="field"><select id="newTeamRule"><option value="interval">Descansos cortos (CSR)</option><option value="lunch">Hora de almuerzo</option></select></div>' +
        '</div>' +
        '<button class="btn primary small" id="addTeamBtn">+ Crear equipo</button>' +
        '</div>' +
        '<div class="modal-actions"><div></div><div class="right"><button class="btn ghost" id="closeTeamBtn">Cerrar</button></div></div>' +
        '</div></div>';
      root.innerHTML = html;

      var wrap = document.getElementById("teamCards");

      wrap.querySelectorAll('[data-f="rule"]').forEach(function (sel) {
        sel.addEventListener("change", function () {
          var card = sel.closest("[data-team-card]");
          var t = state.teams.find(function (x) { return x.id === +card.getAttribute("data-team-card"); });
          var draftT = Object.assign({}, t, { rule: sel.value });
          card.querySelector('[data-params="1"]').innerHTML = paramFields(draftT);
        });
      });
      wrap.querySelectorAll("[data-copy]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var inp = btn.closest(".share-box").querySelector("[data-link]");
          inp.select();
          navigator.clipboard && navigator.clipboard.writeText(inp.value);
          btn.textContent = "¡Copiado!";
          setTimeout(function () { btn.textContent = "Copiar"; }, 1500);
        });
      });
      wrap.querySelectorAll("[data-regen]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          if (!confirm("Regenerar el enlace invalida el anterior. ¿Continuar?")) return;
          apiSend("teams/regenerate-token", "POST", { id: +btn.getAttribute("data-regen") })
            .then(function () { return loadTeams(); }).then(draw)
            .catch(function (e) { alert("No se pudo regenerar: " + e.message); });
        });
      });
      wrap.querySelectorAll("[data-saveteam]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var card = btn.closest("[data-team-card]");
          var payload = { id: +btn.getAttribute("data-saveteam") };
          card.querySelectorAll("[data-f]").forEach(function (el) {
            var k = el.getAttribute("data-f");
            payload[k] = (k === "break_paid") ? (el.value === "1") : el.value;
          });
          apiSend("teams", "PUT", payload)
            .then(function () { return loadTeams(); })
            .then(function () { draw(); renderTeamStrip(); })
            .catch(function (e) { alert("No se pudo guardar: " + e.message); });
        });
      });
      wrap.querySelectorAll("[data-delteam]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          if (!confirm("¿Eliminar este equipo? Solo se puede si no tiene empleados.")) return;
          apiDelete("teams?id=" + btn.getAttribute("data-delteam"))
            .then(function () { return loadTeams(); })
            .then(function () {
              if (state.team && !state.teams.find(function (t) { return t.id === state.team.id; })) {
                state.team = state.teams[0] || null;
              }
              draw(); refreshQuiet();
            })
            .catch(function (e) { alert(e.message); });
        });
      });

      document.getElementById("addTeamBtn").addEventListener("click", function () {
        var name = document.getElementById("newTeamName").value.trim();
        if (!name) return;
        var rule = document.getElementById("newTeamRule").value;
        apiSend("teams", "POST", { name: name, rule: rule, break_paid: rule === "interval" })
          .then(function () { return loadTeams(); })
          .then(function () { draw(); renderTeamStrip(); })
          .catch(function (e) { alert("No se pudo crear el equipo: " + e.message); });
      });
      document.getElementById("closeTeamBtn").addEventListener("click", function () {
        closeModal(); refreshQuiet();
      });
      document.getElementById("ovl3").addEventListener("click", function (ev) { if (ev.target.id === "ovl3") { closeModal(); refreshQuiet(); } });
    }
    draw();
  }

  // ---------------- plantilla semanal / generar mes ----------------
  // Orden Lun..Dom con la convención getDay() (0=dom).
  var WEEK_PICK = [{ d: 1, l: "Lun" }, { d: 2, l: "Mar" }, { d: 3, l: "Mié" }, { d: 4, l: "Jue" }, { d: 5, l: "Vie" }, { d: 6, l: "Sáb" }, { d: 0, l: "Dom" }];

  function monthLabelText() {
    var y = +state.monthKey.slice(0, 4), m = +state.monthKey.slice(5, 7);
    return MESES[m - 1] + " " + y;
  }

  function openTemplateEditor() {
    if (!EDITABLE || !state.team) return;
    var root = document.getElementById("modalRoot");
    var templates = [];

    function tplOf(empId, kind) {
      return templates.find(function (t) { return t.employee_id === +empId && t.kind === kind; });
    }

    function rowFields(empId, kind) {
      var t = tplOf(empId, kind) || { start_time: "08:00", end_time: "17:00", lunch_start: "13:00", cobro: "anticipado", active: false };
      var lunchField = isLunch()
        ? '<div class="field"><label>Almuerzo</label><input type="time" data-f="lunch_start" value="' + esc(t.lunch_start || "13:00") + '"></div>'
        : '';
      return '<div class="field-row" data-tpl="1" data-emp="' + empId + '" data-kind="' + kind + '">' +
        '<div class="field"><label>Entrada</label><input type="time" data-f="start_time" value="' + esc(t.start_time) + '"></div>' +
        '<div class="field"><label>Salida</label><input type="time" data-f="end_time" value="' + esc(t.end_time) + '"></div>' +
        lunchField +
        '<div class="field"><label>Cobro</label><select data-f="cobro"><option value="anticipado"' + (t.cobro !== "posterior" ? " selected" : "") + '>Anticipado</option><option value="posterior"' + (t.cobro === "posterior" ? " selected" : "") + '>Posterior</option></select></div>' +
        '<div class="field"><label>Activa</label><select data-f="active"><option value="1"' + (t.active ? " selected" : "") + '>Sí</option><option value="0"' + (t.active ? "" : " selected") + '>No</option></select></div>' +
        '<div class="field" style="align-self:end;"><button class="btn primary small" data-savetpl="1">Guardar</button></div>' +
        '</div>';
    }

    function empBlock(e) {
      return '<div class="team-rule-fields">' +
        '<div style="font-weight:600;font-size:13px;margin-bottom:6px;">' + escapeHtml(e.name) + '</div>' +
        '<div class="hint">Entre semana (Lun–Vie)</div>' + rowFields(e.id, "weekday") +
        '<div class="hint" style="margin-top:8px;">Fin de semana (Sáb–Dom) — pon «Activa: No» si no trabaja</div>' + rowFields(e.id, "weekend") +
        '</div>';
    }

    function draw() {
      var html = '<div class="overlay" id="ovlT"><div class="modal" style="max-width:640px;">' +
        '<h3>Plantilla semanal · ' + escapeHtml(team().name) + '</h3>' +
        '<div class="modal-sub">Turno fijo de cada empleado para entre semana y para fin de semana</div>' +
        '<div id="tplEmps">' + state.employees.map(empBlock).join("") + '</div>' +
        (state.employees.length ? '' : '<div class="modal-sub">Agrega empleados primero (botón 👤 Empleados).</div>') +
        '<div class="team-rule-fields" style="margin-top:12px;">' +
        '<div class="hint">Crea los turnos de <b>' + escapeHtml(monthLabelText()) + '</b> con estas plantillas. No borra lo que ya haya; los turnos idénticos ya existentes se omiten.</div>' +
        '<div class="share-box" style="margin-top:8px;">' +
        '<button class="btn" data-gen="both">Generar mes completo</button>' +
        '<button class="btn ghost" data-gen="weekday">Solo entre semana</button>' +
        '<button class="btn ghost" data-gen="weekend">Solo fines de semana</button>' +
        '</div><div id="genResult" class="hint"></div>' +
        '</div>' +
        '<div class="modal-actions"><div></div><div class="right"><button class="btn ghost" id="closeTplBtn">Cerrar</button></div></div>' +
        '</div></div>';
      root.innerHTML = html;

      root.querySelectorAll("[data-savetpl]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var row = btn.closest("[data-tpl]");
          var payload = { employee_id: +row.getAttribute("data-emp"), kind: row.getAttribute("data-kind") };
          row.querySelectorAll("[data-f]").forEach(function (el) {
            var k = el.getAttribute("data-f");
            payload[k] = (k === "active") ? (el.value === "1") : el.value;
          });
          btn.textContent = "…";
          apiSend("templates", "POST", payload)
            .then(function () { return apiGet("templates?team=" + state.team.id); })
            .then(function (t) { templates = t; btn.textContent = "✓ Guardada"; setTimeout(function () { btn.textContent = "Guardar"; }, 1400); })
            .catch(function (e) { btn.textContent = "Guardar"; alert("No se pudo guardar la plantilla: " + e.message); });
        });
      });

      root.querySelectorAll("[data-gen]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var mode = btn.getAttribute("data-gen");
          var kinds = mode === "both" ? ["weekday", "weekend"] : [mode];
          var out = document.getElementById("genResult");
          out.textContent = "Generando…";
          apiSend("schedule/generate", "POST", { team_id: state.team.id, month: state.monthKey, kinds: kinds })
            .then(function (r) {
              out.textContent = "Listo: " + r.created + " turno(s) creado(s), " + r.skipped + " omitido(s).";
              return loadMonth(state.monthKey);
            })
            .then(renderAll)
            .catch(function (e) { out.textContent = ""; alert("No se pudo generar: " + e.message); });
        });
      });

      document.getElementById("closeTplBtn").addEventListener("click", function () { closeModal(); });
      document.getElementById("ovlT").addEventListener("click", function (ev) { if (ev.target.id === "ovlT") closeModal(); });
    }

    apiGet("templates?team=" + state.team.id).then(function (t) { templates = t; draw(); }).catch(function (e) {
      alert("No se pudieron cargar las plantillas: " + e.message);
    });
  }

  // ---------------- navegación ----------------
  function bind(id, ev, fn) { var el = document.getElementById(id); if (el) el.addEventListener(ev, fn); }
  bind("prevMonth", "click", function () { shiftMonth(-1); });
  bind("nextMonth", "click", function () { shiftMonth(1); });
  bind("manageEmployeesBtn", "click", openEmployeeEditor);
  bind("manageTemplatesBtn", "click", openTemplateEditor);
  bind("refreshBtn", "click", refreshQuiet);

  function shiftMonth(delta) {
    var y = +state.monthKey.slice(0, 4), m = +state.monthKey.slice(5, 7);
    var d = new Date(y, m - 1 + delta, 1);
    state.monthKey = currentMonthKey(d);
    renderAll();
    var p = EDITABLE ? loadMonth(state.monthKey) : loadReadOnly();
    p.then(renderAll).catch(function (e) { console.error(e); setStatus("Sin conexión al servidor", "error"); });
  }

  renderAll();
  init();
})();
