<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Horario Lunex</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/horario.css?v=6">
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

  <div id="teamTabs" class="team-tabs"></div>

  <div class="stats" id="stats"></div>

  <div class="toolbar">
    <h2 id="gridTitle">Cuadrícula del mes</h2>
    <div style="display:flex; gap:8px;">
      <button class="btn" id="manageTemplatesBtn">🗓 Plantillas</button>
      <button class="btn" id="manageEmployeesBtn">👤 Empleados</button>
    </div>
  </div>

  <div id="dayFilter" class="day-filter"></div>
  <div id="empFilter"></div>

  <div class="grid-scroll">
    <table class="sched" id="schedTable"></table>
  </div>

  <footer class="note">Cada fila es un turno · el color identifica al empleado · el borde izquierdo marca el cobro (<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--accent);vertical-align:1px;"></span> anticipado, <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--warn);vertical-align:1px;"></span> posterior) · las celdas con rayado <span style="display:inline-block;width:14px;height:9px;vertical-align:-1px;border:1px solid var(--border);background:repeating-linear-gradient(45deg, var(--surface) 0 3px, var(--border) 3px 4px);"></span> son el descanso / almuerzo · datos guardados en MySQL, se actualiza solo cada 20s</footer>
</div>

<div id="modalRoot"></div>

<script>window.HORARIO = { editable: true, apiBase: "/api" };</script>
<script src="/assets/horario.js?v=6"></script>
</body>
</html>
