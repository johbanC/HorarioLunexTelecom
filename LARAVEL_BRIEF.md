# Horario Lunex — contexto completo para reconstruir en Laravel

Este documento reúne todo lo necesario para que Claude Code (con acceso completo
a tu máquina: Composer, artisan, MySQL) construya la versión Laravel de esta
app. Los archivos actuales de la versión PHP plano que funcionan como
referencia de la lógica ya están en esta misma carpeta
(`C:\laragon\www\HorarioLunexTelecom`): `index.html`, `schema.sql`,
`api/db.php`, `api/employees.php`, `api/shifts.php`. Ábrelos junto con este
documento — son el "código fuente de verdad" del comportamiento actual.

## Qué es la app

Un planificador de horarios de trabajo para un equipo de asesores (CSR), que
reemplaza una hoja de Google Sheets. Varias personas del equipo la usan al
mismo tiempo desde distintos computadores, así que los datos viven en una
base de datos compartida (MySQL), no en el navegador de cada quien.

## Modelo de datos

Dos tablas (ver `schema.sql` para el DDL exacto, que debe convertirse en
migraciones de Laravel):

**employees**
- `id` (PK autoincremental)
- `name` (string, requerido)
- `sort_order` (int, define el orden en que aparecen en la grilla y en la
  lista de empleados; nuevos empleados van al final)
- timestamps

**shifts** (turnos)
- `id` (PK autoincremental)
- `employee_id` (FK a employees, `ON DELETE CASCADE` — si se borra un
  empleado, se borran sus turnos)
- `work_date` (DATE — el día del turno)
- `start_time` (TIME — hora de entrada)
- `end_time` (TIME — hora de salida; puede cruzar medianoche, ej. 22:00 a
  02:00)
- `break_min` (int, minutos de descanso total del turno — ver regla de
  descansos abajo)
- `break_mode` (ENUM 'auto' | 'manual' — por ahora siempre se calcula
  'auto', pero el campo existe por si en el futuro se permite ajuste manual)
- `cobro` (ENUM 'anticipado' | 'posterior' — tipo de facturación de ese
  turno/empleado ese día)
- timestamps

En Laravel: modelos `Employee` (hasMany Shift) y `Shift` (belongsTo
Employee), migraciones equivalentes, y usar `casts` para `work_date`,
`start_time`, `end_time` según convenga (o mantenerlos como strings "HH:mm"
para simplificar el front, ver abajo).

## Reglas de negocio (importante, ya corregidas tras varias iteraciones)

1. **Un empleado puede tener varios turnos por día**, y varios empleados
   pueden compartir el mismo día (cada fila de la grilla es un turno de un
   empleado en una fecha).

2. **Regla de descanso**: 15 minutos de descanso **cada 3 horas trabajadas,
   al completarlas, no antes, y nunca exactamente al final del turno.**
   Es decir, un turno de 6 horas tiene exactamente **1** descanso (no 2), y
   uno de 9 horas tiene **2** (no 3). La fórmula correcta (ya verificada con
   el usuario):

   ```
   función breakCount(totalMinutosTrabajados):
     count = 0, k = 1
     mientras (k * 180 + 15) <= totalMinutosTrabajados:
       count += 1
       k += 1
     retornar count
   ```

   Y para ubicar los descansos en el tiempo (offset en minutos desde el
   inicio del turno):

   ```
   función breakSlots(inicioTurno, totalMinutos, cantidadDescansos):
     slots = []
     para k de 1 a cantidadDescansos:
       bStart = k * 180
       si (bStart + 15) > totalMinutos: parar
       slots.push({ desde: bStart, hasta: bStart + 15 })
     retornar slots
   ```

   Esto se calcula automáticamente al guardar el turno (a partir de
   start_time/end_time) y se guarda en `break_min` (total de minutos, ej. 2
   descansos = 30) — pero la posición exacta de cada descanso se
   **recalcula en el frontend** con `breakSlots()` a partir de start/end,
   no se guarda posición por posición en la BD.

3. **El descanso NO se descuenta del pago.** Los asesores cobran por hora
   por turno completo. El descanso es un alivio programado, visualmente
   marcado en la grilla, pero las horas pagadas = duración total del turno
   sin restar el descanso:

   ```
   función shiftHours(start, end):
     minutos = end - start (ajustando +24h si end < start, turno cruza
     medianoche)
     retornar minutos / 60
   ```

4. **Cobro anticipado / posterior**: cada turno tiene un campo de tipo de
   facturación (`cobro`), que se resume también en las estadísticas.

## Vista de la app (frontend)

`index.html` en esta carpeta es la implementación de referencia completa
del frontend — cópiala/adáptala tal cual, solo hay que cambiar las llamadas
`fetch("api/employees.php")` etc. por las rutas de la API de Laravel
(ej. `/api/employees`, `/api/shifts?month=2026-09`). El diseño visual está
inspirado en la hoja de Excel original del equipo (capturas de pantalla
vistas antes en esta conversación): una grilla por horas, con:

- Columnas fijas: Fecha, Empleado (sticky al hacer scroll horizontal).
- Columnas de horas dinámicas según el rango de turnos del mes
  (`hourRange()`).
- Una fila por (fecha, empleado, turno) — así que un mismo día puede tener
  varias filas si hay varios empleados o varios turnos.
- Cada celda de hora coloreada por empleado (paleta HSL generada por
  `employeeColor()`, determinista según el nombre/id del empleado).
- Los descansos se pintan como un segmento proporcional dentro de la celda
  de la hora en que ocurren (25% del ancho de la columna para 15 min de una
  hora de 60 min), **no** bloquean la hora completa — esto fue un bug ya
  corregido, ojo con no reintroducirlo.
- Un resumen de horas totales por empleado (y por tipo de cobro) debajo o
  al lado de la grilla.
- Un modal para agregar/editar un turno: selector de empleado, fecha, hora
  entrada/salida, tipo de cobro (anticipado/posterior), y botón "Guardar".
- Un modal aparte para gestionar empleados (agregar, renombrar, eliminar) —
  editable desde la misma app, sin tocar la base de datos a mano.
- Actualización automática cada ~20 segundos (polling) + al volver el foco
  a la pestaña, para que varias personas vean cambios recientes de las
  demás sin recargar manualmente (Laravel puede eventualmente cambiar esto
  por WebSockets/Reverb, pero no es necesario para la v1 — polling está
  bien).
- Indicador de estado de conexión arriba a la derecha ("Conectado · MySQL"
  / error).

## API que el frontend espera (contrato REST, adaptar a rutas Laravel)

```
GET    /api/employees                 → [{id, name, sort_order}, ...]
POST   /api/employees   {name}        → {id, name, sort_order}
PUT    /api/employees   {id, name}    → {ok:true}
DELETE /api/employees?id=X            → {ok:true}   (cascada a sus turnos)

GET    /api/shifts?month=YYYY-MM      → [{id, employee_id, work_date,
                                           start_time, end_time, break_min,
                                           break_mode, cobro}, ...]
                                          (start_time/end_time en formato
                                           "HH:mm", work_date en "YYYY-MM-DD")
POST   /api/shifts  {employee_id, work_date, start_time, end_time,
                      break_min, break_mode, cobro}   → {id}
PUT    /api/shifts  {id, employee_id, work_date, start_time, end_time,
                      break_min, break_mode, cobro}   → {ok:true}
DELETE /api/shifts?id=X               → {ok:true}
```

Ver `api/employees.php` y `api/shifts.php` para la implementación exacta de
validaciones (campos requeridos, valores permitidos de `break_mode` y
`cobro`) que hay que replicar como `FormRequest` o validación inline en los
controladores de Laravel.

## Datos iniciales (seeder)

Al crear la base de datos vacía, sembrar estos empleados (mismo orden =
`sort_order` 0..4), tal como está en `schema.sql`:

```
Karelys, Juana, Valentina, Juan Manuel, Juanita Restrepo
```

(el usuario los puede editar/agregar/quitar después desde la app; esto es
solo la carga inicial).

## Entorno de despliegue

- Ahora mismo: Laragon local en Windows, carpeta
  `C:\laragon\www\HorarioLunexTelecom`. Laragon reconoce automáticamente un
  proyecto Laravel y sirve `public/` como raíz, con URL tipo
  `http://horariolunextelecom.test/`.
- MySQL local de Laragon: host `127.0.0.1`, usuario `root`, sin contraseña,
  base de datos a crear: `horario_lunex` (mismo nombre que ya usa la
  versión PHP plano, para poder reusar los datos si ya cargaron algo).
- **Importante, motivo del proyecto**: esto se planea subir más adelante a
  un hosting en línea con PHP + MySQL (o cualquier hosting compatible con
  Laravel), así que la configuración de base de datos debe leerse de
  `.env` (ya es el estándar de Laravel) para poder cambiarla fácil al
  migrar.

## Qué pidió el usuario, en sus palabras (para no perder intención)

1. App para crear horarios como una hoja de Google Sheets del equipo, con
   suma automática de horas.
2. Compartida entre todo el equipo (multi-usuario, datos centralizados).
3. Descanso de 15 min cada 3 horas trabajadas, visible en la grilla a la
   hora exacta en que ocurre (no como un número escondido).
4. El descanso no se descuenta del pago.
5. Poder agregar varios empleados el mismo día.
6. Empleados editables desde la app.
7. Base de datos MySQL real (ya no la simulación de Claude), porque lo
   van a subir a un servidor en línea más adelante.
8. Ahora: reconstruir todo esto con Laravel porque el usuario trabaja
   mejor con ese framework.

## Estado actual (lo que ya existe y funciona, como referencia)

La versión PHP plano (sin framework) en esta misma carpeta ya tiene toda
esta lógica funcionando (o casi — quedó pendiente de que el usuario
importe `schema.sql` en su MySQL para terminar de probarla). Se recomienda
usarla como referencia 1:1 de comportamiento al portar a Laravel, en vez de
rediseñar desde cero.

## Sugerencia de estructura Laravel (no obligatoria, pero razonable)

```
app/Models/Employee.php
app/Models/Shift.php
app/Http/Controllers/Api/EmployeeController.php
app/Http/Controllers/Api/ShiftController.php
database/migrations/xxxx_create_employees_table.php
database/migrations/xxxx_create_shifts_table.php
database/seeders/EmployeeSeeder.php
routes/api.php          (las 7 rutas de arriba)
resources/views/welcome.blade.php   (o public/index.html servido directo)
```

El frontend puede quedarse casi igual a `index.html` (mismo CSS/JS de
grilla, colores, modal, reglas de descanso) — el trabajo real de "portar a
Laravel" es sobre todo el backend (migraciones, modelos, controladores,
rutas) más ajustar las URLs del `fetch()` en el frontend.
