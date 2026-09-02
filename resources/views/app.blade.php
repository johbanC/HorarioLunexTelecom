@verbatim
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Horario Lunex</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap');

  :root{
    --bg:#eef1ef;
    --surface:#ffffff;
    --surface-2:#f4f6f5;
    --border:#d9dfdb;
    --ink:#182422;
    --ink-soft:#4c5a56;
    --muted:#7c8d88;
    --accent:#1f6f66;
    --accent-ink:#ffffff;
    --accent-soft:#e2f1ee;
    --warn:#a6642a;
    --warn-soft:#f7ebde;
    --danger:#b3453f;
    --danger-soft:#fbe9e7;
    --weekend:#eef6f4;
    --shadow: 0 1px 2px rgba(20,30,28,.06), 0 8px 24px -12px rgba(20,30,28,.18);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --bg:#0f1614;
      --surface:#16201d;
      --surface-2:#1b2622;
      --border:#2a3733;
      --ink:#e7efec;
      --ink-soft:#b7c4c0;
      --muted:#7f938d;
      --accent:#4fb8ab;
      --accent-ink:#04211d;
      --accent-soft:#173330;
      --warn:#d99a5b;
      --warn-soft:#3a2c1c;
      --danger:#e08079;
      --danger-soft:#3a201e;
      --weekend:#131e1b;
      --shadow: 0 1px 2px rgba(0,0,0,.4), 0 8px 24px -12px rgba(0,0,0,.5);
    }
  }
  :root[data-theme="dark"]{
    --bg:#0f1614;
    --surface:#16201d;
    --surface-2:#1b2622;
    --border:#2a3733;
    --ink:#e7efec;
    --ink-soft:#b7c4c0;
    --muted:#7f938d;
    --accent:#4fb8ab;
    --accent-ink:#04211d;
    --accent-soft:#173330;
    --warn:#d99a5b;
    --warn-soft:#3a2c1c;
    --danger:#e08079;
    --danger-soft:#3a201e;
    --weekend:#131e1b;
    --shadow: 0 1px 2px rgba(0,0,0,.4), 0 8px 24px -12px rgba(0,0,0,.5);
  }

  *{box-sizing:border-box;}
  body{
    margin:0; background:var(--bg); color:var(--ink);
    font-family:'Work Sans', system-ui, sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3{ font-family:'Fraunces', Georgia, serif; font-weight:600; margin:0; text-wrap:balance;}
  .mono{ font-family:'IBM Plex Mono', ui-monospace, monospace; font-variant-numeric:tabular-nums; }
  button{ font-family:inherit; }
  ::selection{ background:var(--accent-soft); }

  .wrap{ max-width:1180px; margin:0 auto; padding:20px 20px 60px; }

  /* ---- header ---- */
  header.top{
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    flex-wrap:wrap; margin-bottom:18px;
  }
  .brand{ display:flex; align-items:baseline; gap:10px; }
  .brand .badge{
    display:inline-flex; align-items:center; justify-content:center;
    width:34px; height:34px; border-radius:9px; background:var(--accent); color:var(--accent-ink);
    font-family:'Fraunces',serif; font-weight:700; font-size:16px; flex:none;
  }
  .brand h1{ font-size:22px; }
  .brand .sub{ color:var(--muted); font-size:12.5px; margin-top:2px; }

  .month-nav{ display:flex; align-items:center; gap:6px; }
  .month-nav button{
    width:32px; height:32px; border-radius:8px; border:1px solid var(--border);
    background:var(--surface); color:var(--ink); cursor:pointer; font-size:15px;
    display:flex; align-items:center; justify-content:center;
  }
  .month-nav button:hover{ background:var(--surface-2); }
  .month-label{ font-family:'Fraunces',serif; font-weight:600; font-size:16px; min-width:172px; text-align:center; text-transform:capitalize; }

  .status-dot{ width:8px; height:8px; border-radius:50%; background:var(--muted); flex:none; }
  .status-dot.live{ background:var(--accent); }
  .status-dot.error{ background:var(--danger); }
  .status-pill{
    display:inline-flex; align-items:center; gap:6px; font-size:11.5px; color:var(--muted);
    border:1px solid var(--border); padding:4px 9px; border-radius:99px; background:var(--surface);
  }

  /* ---- stats strip ---- */
  .stats{
    display:grid; grid-auto-flow:column; grid-auto-columns:minmax(128px,1fr);
    gap:10px; overflow-x:auto; padding-bottom:2px; margin-bottom:18px;
  }
  .stat{
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    padding:12px 14px; box-shadow:var(--shadow);
  }
  .stat .label{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
  .stat .value{ font-family:'IBM Plex Mono',monospace; font-size:20px; font-weight:600; font-variant-numeric:tabular-nums; }
  .stat.accent{ background:var(--accent-soft); border-color:transparent; }
  .stat.accent .value{ color:var(--accent); }
  .stat .tag{ display:inline-block; width:8px;height:8px;border-radius:2px; margin-right:6px; vertical-align:1px;}

  /* ---- toolbar ---- */
  .toolbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; flex-wrap:wrap;}
  .toolbar h2{ font-size:15px; color:var(--ink-soft); font-weight:600; font-family:'Work Sans';}
  .btn{
    border:1px solid var(--border); background:var(--surface); color:var(--ink);
    padding:7px 12px; border-radius:8px; font-size:13px; cursor:pointer; font-weight:500;
    display:inline-flex; align-items:center; gap:6px;
  }
  .btn:hover{ background:var(--surface-2); }
  .btn.primary{ background:var(--accent); color:var(--accent-ink); border-color:transparent; }
  .btn.primary:hover{ filter:brightness(1.05); }
  .btn.ghost{ border-color:transparent; background:transparent; color:var(--muted); }
  .btn.danger{ color:var(--danger); }
  .btn:disabled{ opacity:.5; cursor:not-allowed; }
  .btn.small{ padding:4px 9px; font-size:12px; }

  /* ---- grid (cuadrícula por hora, como el Excel) ---- */
  .grid-scroll{
    overflow:auto; border:1px solid var(--border); border-radius:14px; background:var(--surface);
    box-shadow:var(--shadow);
  }
  table.sched{ border-collapse:separate; border-spacing:0; width:100%; min-width:900px; }
  table.sched th, table.sched td{ border-bottom:1px solid var(--border); border-right:1px solid var(--border); }
  table.sched thead th{
    position:sticky; top:0; z-index:3; background:var(--surface-2);
    padding:8px 8px; font-size:11px; color:var(--ink-soft); font-weight:600; text-align:center;
    border-bottom:1px solid var(--border); white-space:nowrap; font-family:'IBM Plex Mono',monospace;
  }
  table.sched thead th.date-col, table.sched thead th.emp-col{ font-family:'Work Sans'; text-align:left; }
  table.sched thead th.hour-col{ min-width:40px; }
  table.sched td.date-col, table.sched th.date-col{
    position:sticky; left:0; z-index:2; background:var(--surface); width:84px; min-width:84px;
    padding:8px 10px; vertical-align:top;
  }
  table.sched td.emp-col, table.sched th.emp-col{
    position:sticky; left:84px; z-index:2; background:var(--surface); min-width:150px;
    padding:6px 10px; vertical-align:middle; cursor:pointer;
  }
  table.sched thead th.date-col, table.sched thead th.emp-col{ z-index:4; background:var(--surface-2); }
  table.sched td.date-col .d{ font-weight:600; font-size:13.5px; }
  table.sched td.date-col .w{ font-size:11px; color:var(--muted); text-transform:capitalize; }
  table.sched td.date-col .daytot{ font-size:10.5px; color:var(--muted); margin-top:4px; font-family:'IBM Plex Mono',monospace; }
  tr.weekend td.date-col, tr.weekend td.emp-col{ background:var(--weekend); }
  tr.weekend .hseg.off{ background:var(--weekend); }

  .emp-name{ font-size:12.5px; font-weight:600; line-height:1.25; }
  .emp-time{ font-size:10.5px; color:var(--muted); margin-top:1px; }
  td.emp-col:hover{ background:var(--accent-soft); }

  td.hcell{ padding:0; height:34px; cursor:pointer; min-width:40px; }
  td.hcell:hover .hbar{ filter:brightness(0.93); }
  .hbar{ display:flex; height:100%; width:100%; }
  .hseg{ height:100%; }
  .hseg.off{ background:var(--surface); }
  .hseg.brk{
    background:repeating-linear-gradient(45deg, var(--surface) 0 4px, var(--border) 4px 6px);
    position:relative;
  }

  tr.add-row td{ cursor:pointer; }
  tr.add-row .add-cell{
    padding:9px 10px; color:var(--muted); font-size:12px; font-weight:500; text-align:left;
  }
  tr.add-row:hover .add-cell{ color:var(--accent); }

  td.total-col, th.total-col{
    min-width:70px; text-align:right; padding:8px 12px; font-family:'IBM Plex Mono',monospace;
    font-weight:600; color:var(--ink-soft); background:var(--surface-2); font-size:12.5px;
  }
  th.total-col.day-total{ min-width:80px; }
  td.total-col.day-total{ background:var(--surface-2); vertical-align:middle; }
  tr.weekend td.total-col{ background:var(--weekend); }
  td.total-col.zero{ color:var(--muted); font-weight:400; }

  tfoot td{ padding:9px 10px; font-family:'IBM Plex Mono',monospace; font-weight:600; background:var(--surface-2); border-top:2px solid var(--border); font-size:12.5px; }
  tfoot td.foot-label{ font-family:'Work Sans'; font-weight:600; color:var(--ink-soft); }

  .timeline{ position:relative; height:9px; border-radius:4px; overflow:hidden; display:flex; background:var(--surface-2); border:1px solid var(--border); }
  .timeline .seg{ height:100%; }
  .timeline .seg.work.anticipado{ background:var(--accent); }
  .timeline .seg.work.posterior{ background:var(--warn); }
  .timeline .seg.brk{
    background:repeating-linear-gradient(45deg, var(--surface) 0 3px, var(--muted) 3px 4px);
    opacity:.65;
  }
  .timeline.lg{ height:22px; border-radius:6px; }
  .brk-label{ font-size:10.5px; color:var(--muted); }
  .brk-label .dash{ display:inline-block; width:6px; height:6px; margin-right:4px; vertical-align:1px; border-radius:1px; background:repeating-linear-gradient(45deg, var(--surface) 0 2px, var(--muted) 2px 3px); border:1px solid var(--border); }

  /* ---- modal ---- */
  .overlay{
    position:fixed; inset:0; background:rgba(10,16,14,.45); display:flex; align-items:center; justify-content:center;
    z-index:50; padding:16px;
  }
  .modal{
    background:var(--surface); border-radius:16px; padding:22px; width:100%; max-width:420px;
    box-shadow:0 24px 64px -20px rgba(0,0,0,.4); border:1px solid var(--border);
    max-height:88vh; overflow:auto;
  }
  .modal h3{ font-size:17px; margin-bottom:2px; }
  .modal .modal-sub{ color:var(--muted); font-size:12.5px; margin-bottom:16px; text-transform:capitalize; }
  .field-row{ display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px; }
  .field label{ font-size:11px; color:var(--muted); display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:.04em; }
  .field input, .field select{
    width:100%; border:1px solid var(--border); border-radius:8px; padding:7px 8px; background:var(--surface);
    color:var(--ink); font-size:13.5px; font-family:'IBM Plex Mono',monospace;
  }
  .field select{ font-family:'Work Sans'; }
  .cobro-toggle{ display:flex; gap:6px; margin-top:6px; }
  .cobro-toggle button{
    flex:1; padding:6px; border-radius:7px; border:1px solid var(--border); background:var(--surface);
    font-size:11.5px; cursor:pointer; color:var(--ink-soft);
  }
  .cobro-toggle button.active.anticipado{ background:var(--accent); color:var(--accent-ink); border-color:transparent; }
  .cobro-toggle button.active.posterior{ background:var(--warn); color:#2a1c0c; border-color:transparent; }
  .modal-actions{ display:flex; justify-content:space-between; gap:8px; margin-top:16px; }
  .modal-actions .right{ display:flex; gap:8px; }

  input[type=text].plain{
    width:100%; border:1px solid var(--border); border-radius:8px; padding:8px 10px; background:var(--surface);
    color:var(--ink); font-size:14px; font-family:'Work Sans'; margin-bottom:10px;
  }
  .emp-list-row{ display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid var(--border); }
  .emp-list-row:last-child{ border-bottom:none; }
  .emp-list-row input.rename{ flex:1; border:1px solid var(--border); border-radius:6px; padding:5px 7px; background:var(--surface); color:var(--ink); font-size:13px; }

  footer.note{ margin-top:22px; font-size:12px; color:var(--muted); text-align:center; }
  a{ color:var(--accent); }

  @media (max-width:680px){
    .field-row{ grid-template-columns:1fr; }
  }
</style>
</head>
<body>

<div class="wrap">
  <header class="top">
    <div class="brand">
      <div class="badge">L</div>
      <div>
        <h1>Horario Lunex</h1>
        <div class="sub">Turnos y suma de horas del equipo · edición Laravel + MySQL</div>
      </div>
    </div>
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
      <button class="btn small" id="refreshBtn" title="Actualizar ahora">🔄</button>
      <span class="status-pill" id="statusPill"><span class="status-dot" id="statusDot"></span><span id="statusText">Conectando…</span></span>
      <div class="month-nav">
        <button id="prevMonth" aria-label="Mes anterior">‹</button>
        <div class="month-label" id="monthLabel">—</div>
        <button id="nextMonth" aria-label="Mes siguiente">›</button>
      </div>
    </div>
  </header>

  <div class="stats" id="stats"></div>

  <div class="toolbar">
    <h2 id="gridTitle">Cuadrícula del mes</h2>
    <div style="display:flex; gap:8px;">
      <button class="btn" id="manageEmployeesBtn">👤 Empleados</button>
    </div>
  </div>

  <div class="grid-scroll">
    <table class="sched" id="schedTable"></table>
  </div>

  <footer class="note">Cada fila es un turno · el color identifica al empleado · el borde izquierdo marca el cobro (<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--accent);vertical-align:1px;"></span> anticipado, <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--warn);vertical-align:1px;"></span> posterior) · las celdas con rayado <span style="display:inline-block;width:14px;height:9px;vertical-align:-1px;border:1px solid var(--border);background:repeating-linear-gradient(45deg, var(--surface) 0 3px, var(--border) 3px 4px);"></span> son el descanso (no se descuenta de las horas pagadas) · datos guardados en MySQL, se actualiza solo cada 20s</footer>
</div>

<div id="modalRoot"></div>

<script>
(function(){
  "use strict";

  var API = "/api/";
  var MESES = ["enero","febrero","marzo","abril","mayo","junio","julio","agosto","septiembre","octubre","noviembre","diciembre"];
  var DIAS = ["domingo","lunes","martes","miércoles","jueves","viernes","sábado"];
  var DIAS_CORTOS = ["dom","lun","mar","mié","jue","vie","sáb"];
  var DEFAULT_EMPLOYEES = ["Karelys","Juana","Valentina","Juan Manuel","Juanita Restrepo"];

  var state = {
    employees: [],           // [{id,name,order}]  (id siempre como string)
    monthKey: currentMonthKey(),
    monthData: { days: {} }, // { days: { "YYYY-MM-DD": { empId: [ {dbId,start,end,breakMin,breakMode,cobro} ] } } }
    ready: false
  };

  function currentMonthKey(d){
    d = d || new Date();
    return d.getFullYear() + "-" + String(d.getMonth()+1).padStart(2,"0");
  }

  function pad(n){ return String(n).padStart(2,"0"); }

  function daysInMonth(key){
    var y = +key.slice(0,4), m = +key.slice(5,7);
    return new Date(y, m, 0).getDate();
  }

  function dateStr(key, day){ return key + "-" + pad(day); }

  function weekdayOf(key, day){
    var y = +key.slice(0,4), m = +key.slice(5,7);
    return new Date(y, m-1, day).getDay();
  }

  function timeToMin(t){
    if(!t) return null;
    var p = t.split(":"); return (+p[0])*60 + (+p[1]);
  }

  // Al asesor se le paga por hora de turno completo: el descanso NO se descuenta del pago,
  // solo se muestra como referencia de a qué hora lo puede tomar.
  function shiftHours(s){
    var a = timeToMin(s.start), b = timeToMin(s.end);
    if(a==null || b==null) return 0;
    var mins = b - a;
    if(mins <= 0) mins += 24*60;
    if(mins < 0) mins = 0;
    return mins/60;
  }

  // Regla del equipo: 15 minutos de descanso por cada 3 horas trabajadas.
  var BREAK_EVERY_HOURS = 3, BREAK_MIN = 15;
  function grossMinutes(start, end){
    var a = timeToMin(start), b = timeToMin(end);
    if(a==null || b==null) return 0;
    var mins = b - a;
    if(mins <= 0) mins += 24*60;
    return mins;
  }
  // Un descanso solo cuenta si, al completar las 3 horas, todavía queda turno después
  // (no se da un descanso justo al salir). Por eso un turno de exactamente 6h tiene
  // 1 descanso (a las 3h) y no 2 — el segundo caería justo cuando termina el turno.
  function breakCount(total){
    var count = 0, k = 1;
    while(k*BREAK_EVERY_HOURS*60 + BREAK_MIN <= total){ count++; k++; }
    return count;
  }
  function autoBreakMinutes(start, end){
    return breakCount(grossMinutes(start, end)) * BREAK_MIN;
  }
  function minToTime(mins){
    mins = ((mins % 1440) + 1440) % 1440;
    return pad(Math.floor(mins/60)) + ":" + pad(mins%60);
  }
  // Dónde caen los descansos dentro del turno: uno de 15 min cada 3h completas, con turno pendiente después.
  function breakSlots(s){
    var startAbs = timeToMin(s.start);
    var total = grossMinutes(s.start, s.end);
    var count = Math.round((s.breakMin || 0) / BREAK_MIN);
    var slots = [];
    for(var k=1; k<=count; k++){
      var bStart = k*BREAK_EVERY_HOURS*60;
      if(bStart + BREAK_MIN > total) break; // no alcanza a caber un descanso completo con turno después: se omite
      slots.push({ startOffset:bStart, endOffset:bStart+BREAK_MIN, start:minToTime(startAbs+bStart), end:minToTime(startAbs+bStart+BREAK_MIN) });
    }
    return slots;
  }
  // Segmentos trabajo/descanso para dibujar la línea de tiempo del turno.
  function shiftTimeline(s){
    var total = grossMinutes(s.start, s.end);
    if(total <= 0) return [];
    var brks = breakSlots(s).sort(function(a,b){ return a.startOffset-b.startOffset; });
    var segs = [], cursor = 0;
    brks.forEach(function(b){
      if(b.startOffset > cursor) segs.push({ type:"work", pct:(b.startOffset-cursor)/total*100 });
      segs.push({ type:"brk", pct:(b.endOffset-b.startOffset)/total*100 });
      cursor = b.endOffset;
    });
    if(cursor < total) segs.push({ type:"work", pct:(total-cursor)/total*100 });
    return segs;
  }
  function timelineHtml(s, big){
    var cls = s.cobro==="posterior" ? "posterior" : "anticipado";
    var segs = shiftTimeline(s);
    var html = '<div class="timeline'+(big?' lg':'')+'">';
    segs.forEach(function(seg){
      html += '<div class="seg '+(seg.type==="brk"?"brk":"work "+cls)+'" style="width:'+seg.pct+'%"></div>';
    });
    html += '</div>';
    return html;
  }

  function overlaps(aStart,aEnd,bStart,bEnd){ return aStart < bEnd && bStart < aEnd; }

  // Divide una celda de una hora en tramos proporcionales (trabajo / descanso / nada),
  // para que un descanso de 15 min se vea como una franja delgada (25% del ancho)
  // en el minuto exacto en que ocurre, y no como si tapara la hora completa.
  function hourCellSegments(cellStart, cellEnd, sMin, sEnd, brks){
    var points = [cellStart, cellEnd, sMin, sEnd];
    brks.forEach(function(b){ points.push(sMin+b.startOffset, sMin+b.endOffset); });
    points = points.filter(function(p){ return p>cellStart && p<cellEnd; });
    points.push(cellStart, cellEnd);
    points = Array.from(new Set(points)).sort(function(a,b){ return a-b; });
    var segs = [];
    for(var i=0;i<points.length-1;i++){
      var a = points[i], b = points[i+1];
      if(b<=a) continue;
      var mid = (a+b)/2;
      var isBrk = brks.some(function(br){ return mid>=sMin+br.startOffset && mid<sMin+br.endOffset; });
      var isWork = !isBrk && mid>=sMin && mid<sEnd;
      segs.push({ type: isBrk?"brk":(isWork?"work":"off"), pct:(b-a)/(cellEnd-cellStart)*100 });
    }
    return segs;
  }

  var EMP_HUES = [142, 208, 28, 352, 285, 190, 48, 322, 96, 4];
  function employeeColor(index){
    var hue = EMP_HUES[((index%EMP_HUES.length)+EMP_HUES.length)%EMP_HUES.length];
    return { bg:'hsl('+hue+',56%,40%)', fg:'#ffffff' };
  }

  // Rango de horas a mostrar en la cuadrícula: 08:00-23:00 por defecto, se amplía si hay turnos fuera de ese rango.
  function hourRange(){
    var minH = 8, maxH = 23;
    var days = state.monthData.days || {};
    Object.keys(days).forEach(function(dk){
      var dayObj = days[dk] || {};
      Object.keys(dayObj).forEach(function(eid){
        (dayObj[eid] || []).forEach(function(s){
          var a = timeToMin(s.start);
          if(a == null) return;
          var total = grossMinutes(s.start, s.end);
          var startH = Math.floor(a/60);
          var endH = Math.ceil((a+total)/60);
          if(startH < minH) minH = Math.max(0, startH);
          if(endH > maxH) maxH = Math.min(24, endH);
        });
      });
    });
    return { start:minH, end:maxH };
  }

  function fmtH(h){
    var r = Math.round(h*100)/100;
    return (r % 1 === 0) ? String(r) : r.toFixed(2).replace(/0$/,"").replace(/\.$/,"");
  }

  // ---------------- API (fetch a los archivos PHP) ----------------
  function setStatus(text, cls){
    document.getElementById("statusText").textContent = text;
    var dot = document.getElementById("statusDot");
    dot.classList.remove("live","error");
    if(cls) dot.classList.add(cls);
  }

  function apiGet(path){
    return fetch(API+path).then(function(res){
      if(!res.ok) return res.json().then(function(e){ throw new Error(e.error || ("HTTP "+res.status)); }).catch(function(){ throw new Error("HTTP "+res.status); });
      return res.json();
    });
  }
  function apiSend(path, method, body){
    return fetch(API+path, {
      method: method,
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(body || {})
    }).then(function(res){
      if(!res.ok) return res.json().then(function(e){ throw new Error(e.error || ("HTTP "+res.status)); }).catch(function(){ throw new Error("HTTP "+res.status); });
      return res.json();
    });
  }
  function apiDelete(path){
    return fetch(API+path, {method:"DELETE"}).then(function(res){
      if(!res.ok) return res.json().then(function(e){ throw new Error(e.error || ("HTTP "+res.status)); }).catch(function(){ throw new Error("HTTP "+res.status); });
      return res.json();
    });
  }

  async function loadEmployees(){
    var data = await apiGet("employees");
    state.employees = data.map(function(e){ return {id:String(e.id), name:e.name, order:e.sort_order}; });
    state.employees.sort(function(a,b){ return (a.order-b.order) || a.name.localeCompare(b.name); });
  }

  async function loadMonth(key){
    var rows = await apiGet("shifts?month="+encodeURIComponent(key));
    var days = {};
    rows.forEach(function(r){
      var d = r.work_date, eid = String(r.employee_id);
      if(!days[d]) days[d] = {};
      if(!days[d][eid]) days[d][eid] = [];
      days[d][eid].push({
        dbId: r.id,
        start: r.start_time,
        end: r.end_time,
        breakMin: +r.break_min,
        breakMode: r.break_mode,
        cobro: r.cobro
      });
    });
    state.monthData = { days: days };
  }

  async function seedEmployees(){
    for(var i=0;i<DEFAULT_EMPLOYEES.length;i++){
      await apiSend("employees", "POST", { name: DEFAULT_EMPLOYEES[i] });
    }
  }

  var pollTimer = null;
  async function initApi(){
    try{
      await loadEmployees();
      if(state.employees.length === 0){
        await seedEmployees();
        await loadEmployees();
      }
      await loadMonth(state.monthKey);
      state.ready = true;
      setStatus("Conectado · MySQL", "live");
      renderAll();
    }catch(e){
      console.error("init error", e);
      setStatus("Sin conexión al servidor (revisa api/db.php)", "error");
      renderAll();
    }
    if(pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshQuiet, 20000);
    window.addEventListener("focus", refreshQuiet);
  }

  async function refreshQuiet(){
    try{
      await loadEmployees();
      await loadMonth(state.monthKey);
      setStatus("Conectado · MySQL", "live");
      renderAll();
    }catch(e){
      console.error("refresh error", e);
      setStatus("Sin conexión al servidor", "error");
    }
  }

  // ---------------- render ----------------
  function renderAll(){
    renderMonthLabel();
    renderStats();
    renderGrid();
  }

  function renderMonthLabel(){
    var y = +state.monthKey.slice(0,4), m = +state.monthKey.slice(5,7);
    document.getElementById("monthLabel").textContent = MESES[m-1] + " " + y;
  }

  function computeTotals(){
    var perEmp = {};
    state.employees.forEach(function(e){ perEmp[e.id] = 0; });
    var perDay = {};
    var anticipado = 0, posterior = 0, total = 0;
    var days = state.monthData.days || {};
    Object.keys(days).forEach(function(dateK){
      var dayObj = days[dateK] || {};
      var dayTotal = 0;
      Object.keys(dayObj).forEach(function(empId){
        (dayObj[empId]||[]).forEach(function(s){
          var h = shiftHours(s);
          dayTotal += h; total += h;
          if(perEmp[empId] != null) perEmp[empId] += h; else perEmp[empId] = h;
          if(s.cobro === "posterior") posterior += h; else anticipado += h;
        });
      });
      perDay[dateK] = dayTotal;
    });
    return { perEmp:perEmp, perDay:perDay, anticipado:anticipado, posterior:posterior, total:total };
  }

  function renderStats(){
    var t = computeTotals();
    var el = document.getElementById("stats");
    var html = "";
    html += statTile("Total del mes", fmtH(t.total)+"h", true);
    state.employees.forEach(function(e){
      html += statTile(e.name, fmtH(t.perEmp[e.id]||0)+"h", false);
    });
    html += statTile("Cobro anticipado", fmtH(t.anticipado)+"h", false, "var(--accent)");
    html += statTile("Cobro posterior", fmtH(t.posterior)+"h", false, "var(--warn)");
    el.innerHTML = html;
  }

  function statTile(label, value, accent, tagColor){
    return '<div class="stat'+(accent?' accent':'')+'"><div class="label">'+
      (tagColor ? '<span class="tag" style="background:'+tagColor+'"></span>' : '') +
      escapeHtml(label)+'</div><div class="value">'+value+'</div></div>';
  }

  function empIndex(empId){
    for(var i=0;i<state.employees.length;i++) if(state.employees[i].id===empId) return i;
    return 0;
  }

  function renderGrid(){
    var table = document.getElementById("schedTable");
    var nDays = daysInMonth(state.monthKey);
    var t = computeTotals();
    var hr = hourRange();

    var thead = '<thead><tr><th class="date-col">Fecha</th><th class="emp-col">Empleado</th>';
    for(var h=hr.start; h<hr.end; h++){ thead += '<th class="hour-col">'+pad(h)+':00</th>'; }
    thead += '<th class="total-col">Turno</th><th class="total-col day-total">Total día</th></tr></thead>';

    var body = "<tbody>";
    for(var day=1; day<=nDays; day++){
      var dk = dateStr(state.monthKey, day);
      var wd = weekdayOf(state.monthKey, day);
      var isWeekend = (wd===0 || wd===6);
      var rowClass = isWeekend ? "weekend" : "";

      var rows = [];
      state.employees.forEach(function(e){
        var shifts = (state.monthData.days[dk] && state.monthData.days[dk][e.id]) || [];
        shifts.forEach(function(s, idx){ rows.push({ emp:e, shift:s, idx:idx }); });
      });
      var rowSpan = rows.length + 1;

      var dayTotal = t.perDay[dk] || 0;
      var dateCell = '<td class="date-col" rowspan="'+rowSpan+'"><div class="d">'+day+'</div><div class="w">'+DIAS_CORTOS[wd]+'</div>' +
        '<div class="daytot">'+fmtH(dayTotal)+'h</div></td>';
      var dayTotalCell = '<td class="total-col day-total'+(dayTotal===0?' zero':'')+'" rowspan="'+rowSpan+'">'+fmtH(dayTotal)+'h</td>';

      rows.forEach(function(r, i){
        var col = employeeColor(empIndex(r.emp.id));
        var cobroColor = r.shift.cobro === "posterior" ? "var(--warn)" : "var(--accent)";
        body += '<tr class="'+rowClass+'">';
        if(i===0) body += dateCell;
        body += '<td class="emp-col" data-edit-shift="1" data-date="'+dk+'" data-emp="'+r.emp.id+'" data-idx="'+r.idx+'" style="border-left:4px solid '+cobroColor+';">' +
          '<div class="emp-name">'+escapeHtml(r.emp.name)+'</div>' +
          '<div class="emp-time mono">'+esc(r.shift.start)+'–'+esc(r.shift.end)+'</div>' +
        '</td>';
        var sMin = timeToMin(r.shift.start);
        var total = grossMinutes(r.shift.start, r.shift.end);
        var sEnd = sMin + total;
        var brks = breakSlots(r.shift);
        for(var hh=hr.start; hh<hr.end; hh++){
          var cs = hh*60, ce = hh*60+60;
          var segs = hourCellSegments(cs, ce, sMin, sEnd, brks);
          var brkInCell = segs.some(function(sg){ return sg.type==="brk"; });
          body += '<td class="hcell" title="'+(brkInCell?"Descanso":"")+'" data-edit-shift="1" data-date="'+dk+'" data-emp="'+r.emp.id+'" data-idx="'+r.idx+'">';
          body += '<div class="hbar">';
          segs.forEach(function(sg){
            var style = 'width:'+sg.pct+'%;';
            if(sg.type==="work") style += 'background:'+col.bg+';';
            body += '<div class="hseg '+sg.type+'" style="'+style+'"></div>';
          });
          body += '</div></td>';
        }
        body += '<td class="total-col">'+fmtH(shiftHours(r.shift))+'h</td>';
        if(i===0) body += dayTotalCell;
        body += '</tr>';
      });

      body += '<tr class="'+rowClass+' add-row" data-new-date="'+dk+'">';
      if(rows.length === 0) body += dateCell;
      body += '<td class="add-cell" colspan="'+(2+(hr.end-hr.start))+'">+ agregar turno</td>';
      if(rows.length === 0) body += dayTotalCell;
      body += '</tr>';
    }
    body += "</tbody>";

    var tfoot = '<tfoot><tr><td class="foot-label" colspan="2">Total mes</td>';
    for(var hh2=hr.start; hh2<hr.end; hh2++){ tfoot += '<td></td>'; }
    tfoot += '<td colspan="2">'+fmtH(t.total)+'h</td></tr></tfoot>';

    table.innerHTML = thead + body + tfoot;

    table.querySelectorAll("[data-edit-shift]").forEach(function(td){
      td.addEventListener("click", function(){
        openShiftEditor(td.getAttribute("data-date"), td.getAttribute("data-emp"), +td.getAttribute("data-idx"));
      });
    });
    table.querySelectorAll("tr.add-row[data-new-date]").forEach(function(tr){
      tr.addEventListener("click", function(){
        openShiftEditor(tr.getAttribute("data-new-date"), null, -1);
      });
    });
  }

  function esc(s){ return s==null ? "" : String(s); }
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
    });
  }

  // ---------------- shift editor modal (un turno a la vez) ----------------
  function openShiftEditor(dateK, empId, shiftIdx){
    if(state.employees.length === 0){ alert("Primero agrega al menos un empleado."); return; }
    var isNew = !empId || shiftIdx == null || shiftIdx < 0;
    var s;
    if(!isNew){
      var src = (state.monthData.days[dateK] && state.monthData.days[dateK][empId] && state.monthData.days[dateK][empId][shiftIdx]);
      s = src ? Object.assign({}, src) : {start:"08:00", end:"17:00", cobro:"anticipado"};
    } else {
      s = {start:"08:00", end:"17:00", cobro:"anticipado"};
    }
    if(!s.breakMode) s.breakMode = "auto";
    if(s.breakMode === "auto") s.breakMin = autoBreakMinutes(s.start, s.end);

    var takenIds = Object.keys((state.monthData.days[dateK] || {})).filter(function(id){ return (state.monthData.days[dateK][id]||[]).length; });
    var defaultEmp = empId || (state.employees.find(function(e){ return takenIds.indexOf(e.id) === -1; }) || state.employees[0]).id;
    var selectedEmp = defaultEmp;

    var y = +dateK.slice(0,4), m = +dateK.slice(5,7), d = +dateK.slice(8,10);
    var wd = new Date(y, m-1, d).getDay();
    var dateLabel = DIAS[wd] + " " + d + " de " + MESES[m-1];

    var root = document.getElementById("modalRoot");
    function draw(){
      var html = '<div class="overlay" id="ovl"><div class="modal">' +
        '<h3>'+(isNew ? "Nuevo turno" : "Editar turno")+'</h3>' +
        '<div class="modal-sub">'+escapeHtml(dateLabel)+'</div>' +
        '<div class="field" style="margin-bottom:10px;"><label>Empleado</label>' +
          '<select id="empSelect">' + state.employees.map(function(e){
            return '<option value="'+e.id+'"'+(e.id===selectedEmp?' selected':'')+'>'+escapeHtml(e.name)+'</option>';
          }).join('') + '</select>' +
        '</div>' +
        '<div class="field-row">' +
          '<div class="field"><label>Entrada</label><input type="time" id="fStart" value="'+esc(s.start)+'"></div>' +
          '<div class="field"><label>Salida</label><input type="time" id="fEnd" value="'+esc(s.end)+'"></div>' +
        '</div>' +
        '<div class="field-row">' +
          '<div class="field"><label>Descanso (min)'+(s.breakMode==="auto" ? ' · auto' : '')+'</label><input type="number" min="0" step="5" id="fBreak" value="'+(s.breakMin||0)+'"'+(s.breakMode==="auto" ? ' disabled' : '')+'></div>' +
          '<div class="field"><label>Horas pagadas</label><input type="text" class="mono" value="'+fmtH(shiftHours(s))+'h" disabled></div>' +
        '</div>' +
        timelineHtml(s, true) +
        (breakSlots(s).length ? '<div class="brk-label" style="margin-top:6px;"><span class="dash"></span>Descanso: '+breakSlots(s).map(function(b){ return b.start+'–'+b.end; }).join(', ')+' · no se descuenta del pago</div>' : '<div class="brk-label" style="margin-top:6px;">Sin descanso (turno menor a 3h)</div>') +
        '<button type="button" class="btn ghost small" style="padding:4px 0 0;" id="toggleBreak">'+(s.breakMode==="auto" ? "Ajustar descanso manualmente" : "Usar regla automática (15 min cada 3h)")+'</button>' +
        '<div class="cobro-toggle" style="margin-top:12px;">' +
          '<button type="button" class="'+(s.cobro!=="posterior"?"active anticipado":"")+'" data-cobro="anticipado">Cobro anticipado</button>' +
          '<button type="button" class="'+(s.cobro==="posterior"?"active posterior":"")+'" data-cobro="posterior">Cobro posterior</button>' +
        '</div>' +
        '<div class="modal-actions">' +
          (isNew ? '<div></div>' : '<button class="btn danger" id="removeShift">Eliminar turno</button>') +
          '<div class="right"><button class="btn ghost" id="cancelBtn">Cancelar</button><button class="btn primary" id="saveBtn">Guardar</button></div>' +
        '</div>' +
      '</div></div>';
      root.innerHTML = html;

      document.getElementById("empSelect").addEventListener("change", function(){ selectedEmp = this.value; });
      document.getElementById("fStart").addEventListener("input", function(){
        s.start = this.value;
        if(s.breakMode !== "manual") s.breakMin = autoBreakMinutes(s.start, s.end);
        draw();
      });
      document.getElementById("fEnd").addEventListener("input", function(){
        s.end = this.value;
        if(s.breakMode !== "manual") s.breakMin = autoBreakMinutes(s.start, s.end);
        draw();
      });
      document.getElementById("fBreak").addEventListener("input", function(){
        s.breakMin = (+this.value || 0);
        draw();
      });
      document.getElementById("toggleBreak").addEventListener("click", function(){
        if(s.breakMode === "auto"){ s.breakMode = "manual"; }
        else { s.breakMode = "auto"; s.breakMin = autoBreakMinutes(s.start, s.end); }
        draw();
      });
      root.querySelectorAll("[data-cobro]").forEach(function(btn){
        btn.addEventListener("click", function(){ s.cobro = btn.getAttribute("data-cobro"); draw(); });
      });
      document.getElementById("cancelBtn").addEventListener("click", closeModal);
      document.getElementById("ovl").addEventListener("click", function(ev){ if(ev.target.id==="ovl") closeModal(); });

      var rm = document.getElementById("removeShift");
      if(rm) rm.addEventListener("click", function(){
        apiDelete("shifts?id="+encodeURIComponent(s.dbId))
          .then(function(){ return loadMonth(state.monthKey); })
          .then(function(){ renderAll(); closeModal(); })
          .catch(function(err){ console.error(err); alert("No se pudo eliminar el turno: " + err.message); });
      });

      document.getElementById("saveBtn").addEventListener("click", function(){
        if(!s.start || !s.end){ alert("Falta la hora de entrada o salida."); return; }
        var payload = {
          employee_id: +selectedEmp,
          work_date: dateK,
          start_time: s.start,
          end_time: s.end,
          break_min: s.breakMin || 0,
          break_mode: s.breakMode || "auto",
          cobro: s.cobro === "posterior" ? "posterior" : "anticipado"
        };
        var req;
        if(isNew){
          req = apiSend("shifts", "POST", payload);
        } else {
          payload.id = s.dbId;
          req = apiSend("shifts", "PUT", payload);
        }
        req.then(function(){ return loadMonth(state.monthKey); })
           .then(function(){ renderAll(); closeModal(); })
           .catch(function(err){ console.error(err); alert("No se pudo guardar el turno: " + err.message); });
      });
    }
    draw();
  }

  function closeModal(){ document.getElementById("modalRoot").innerHTML = ""; }

  // ---------------- employee management ----------------
  function openEmployeeEditor(){
    var root = document.getElementById("modalRoot");
    function draw(){
      var html = '<div class="overlay" id="ovl2"><div class="modal">' +
        '<h3>Empleados</h3>' +
        '<div class="modal-sub">Guardado en la base de datos MySQL</div>' +
        '<div id="empRows"></div>' +
        '<input type="text" class="plain" id="newEmpName" placeholder="Nombre del nuevo empleado" style="margin-top:10px;">' +
        '<button class="btn primary small" id="addEmpBtn">+ Agregar empleado</button>' +
        '<div class="modal-actions"><div></div><div class="right"><button class="btn ghost" id="closeEmpBtn">Cerrar</button></div></div>' +
      '</div></div>';
      root.innerHTML = html;

      var rows = document.getElementById("empRows");
      rows.innerHTML = state.employees.map(function(e){
        return '<div class="emp-list-row"><input class="rename" data-id="'+e.id+'" type="text" value="'+escapeHtml(e.name)+'">' +
          '<button class="btn small danger" data-del="'+e.id+'">Eliminar</button></div>';
      }).join("") || '<div class="modal-sub">Sin empleados todavía.</div>';

      rows.querySelectorAll("input.rename").forEach(function(inp){
        inp.addEventListener("change", function(){
          renameEmployee(inp.getAttribute("data-id"), inp.value.trim());
        });
      });
      rows.querySelectorAll("[data-del]").forEach(function(btn){
        btn.addEventListener("click", function(){
          var id = btn.getAttribute("data-del");
          var emp = state.employees.find(function(e){return e.id===id;});
          if(confirm('¿Eliminar a "'+(emp?emp.name:"")+'"? También se eliminarán todos sus turnos guardados.')){
            removeEmployee(id).then(draw);
          }
        });
      });
      document.getElementById("addEmpBtn").addEventListener("click", function(){
        var v = document.getElementById("newEmpName").value.trim();
        if(!v) return;
        addEmployee(v).then(draw);
      });
      document.getElementById("closeEmpBtn").addEventListener("click", closeModal2);
      document.getElementById("ovl2").addEventListener("click", function(ev){ if(ev.target.id==="ovl2") closeModal2(); });
    }
    draw();
  }
  function closeModal2(){ document.getElementById("modalRoot").innerHTML = ""; renderAll(); }

  async function addEmployee(name){
    try{
      await apiSend("employees", "POST", { name: name });
      await loadEmployees();
    }catch(e){ console.error(e); alert("No se pudo agregar el empleado: " + e.message); }
  }
  async function renameEmployee(id, name){
    if(!name) return;
    try{
      await apiSend("employees", "PUT", { id: +id, name: name });
      await loadEmployees();
    }catch(e){ console.error(e); alert("No se pudo renombrar: " + e.message); }
  }
  async function removeEmployee(id){
    try{
      await apiDelete("employees?id="+encodeURIComponent(id));
      await loadEmployees();
      await loadMonth(state.monthKey);
    }catch(e){ console.error(e); alert("No se pudo eliminar: " + e.message); }
  }

  // ---------------- nav ----------------
  document.getElementById("prevMonth").addEventListener("click", function(){ shiftMonth(-1); });
  document.getElementById("nextMonth").addEventListener("click", function(){ shiftMonth(1); });
  document.getElementById("manageEmployeesBtn").addEventListener("click", function(){ openEmployeeEditor(); });
  document.getElementById("refreshBtn").addEventListener("click", function(){ refreshQuiet(); });

  function shiftMonth(delta){
    var y = +state.monthKey.slice(0,4), m = +state.monthKey.slice(5,7);
    var d = new Date(y, m-1+delta, 1);
    state.monthKey = currentMonthKey(d);
    renderAll();
    loadMonth(state.monthKey).then(renderAll).catch(function(e){
      console.error(e); setStatus("Sin conexión al servidor", "error");
    });
  }

  renderAll();
  initApi();
})();
</script>
</body>
</html>

@endverbatim
