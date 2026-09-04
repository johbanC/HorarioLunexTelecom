# Horario Lunex — versión Laravel

Planificador de horarios de Lunex Telecom en Laravel 13. Maneja **varios
equipos** (CSR, Contabilidad, …), cada uno con su propia regla de descanso, y
un **enlace de solo lectura** que se le comparte a cada equipo.

Reemplaza la versión PHP plano (que queda en la carpeta como referencia:
`index.html`, `schema.sql`, `api/`).

## Estructura

| Pieza | Archivo |
|---|---|
| Migraciones | `database/migrations/2026_09_02_*` (employees, shifts), `2026_09_03_*` (teams, team_id, lunch_start) |
| Modelos | `app/Models/Team.php`, `Employee.php`, `Shift.php` |
| Controladores API | `app/Http/Controllers/Api/{Team,Employee,Shift}Controller.php` |
| Solo lectura | `app/Http/Controllers/PublicScheduleController.php` |
| Rutas | `routes/api.php`, `routes/web.php` |
| Seeders | `TeamSeeder` (CSR, Contabilidad), `EmployeeSeeder` (5 asesores CSR) |
| Plantillas / generar | `app/Models/ShiftTemplate.php`, `Api/ShiftTemplateController.php`, `Api/ScheduleController.php`, `app/Support/ShiftRules.php` |
| Frontend | `resources/views/app.blade.php` (editable) y `public.blade.php` (solo lectura); lógica compartida en `public/assets/horario.js` + `public/assets/horario.css` |
| Pruebas | `tests/Feature/ScheduleApiTest.php` + `TemplateScheduleTest.php` (27 casos) |

## Equipos y reglas de descanso

Cada equipo (`teams`) tiene una `rule`:

- **`interval`** (equipo CSR): un descanso corto cada X horas. Por defecto
  **15 min cada 3 h completas**, y solo si queda turno después (nunca justo al
  salir): 6 h → 1 descanso, 9 h → 2. `break_paid = true` → **no se descuenta del
  pago**. Se calcula solo en el servidor (`ShiftController`).
- **`lunch`** (equipo Contabilidad): **1 hora de almuerzo**, con la hora de
  inicio elegida a mano en cada turno (`shifts.lunch_start`). `break_paid = false`
  → **sí se descuenta del pago** (turno 9:00–18:00 = 8 h pagadas). El servidor
  valida que el almuerzo quepa completo dentro del turno.

Los parámetros (`break_len_min`, `break_interval_min`, `lunch_min`,
`break_paid`) son editables por equipo desde el botón **⚙ Equipos**. Ahí también
se crean equipos nuevos y se ve/regenera el enlace de cada uno.

Turnos que cruzan medianoche se manejan sumando 24 h. Cada turno lleva
`cobro` = `anticipado` | `posterior`, resumido en las estadísticas.

**Filtro por días** (barra sobre la cuadrícula): «Todos / Lun–Vie / Sáb–Dom» +
7 botones L M M J V S D para armar un filtro a medida. Solo oculta/muestra filas
en la cuadrícula (para cargar más fácil, p. ej. la rotación de fines de semana);
el subtotal del pie se recalcula a los días visibles. Se recuerda por navegador
(`localStorage`). También está en la vista de solo lectura.

**Filtro por asesor** (fila debajo del anterior): pastillas con el color de cada
empleado del equipo actual; selección múltiple. Igual que el de días, solo
oculta/muestra filas y el «Total (filtrado)» del pie se recalcula. Se reinicia
al cambiar de equipo (no se guarda entre sesiones). Con un solo asesor
seleccionado, «+ agregar turno» ya lo pre-selecciona.

**Varios turnos por persona el mismo día** (cuando alguien cubre horas): en
la cuadrícula, cada empleado tiene un botón **＋ turno** que abre un turno nuevo
para esa persona ese día, con la entrada ya puesta al final de su último turno.
Cada turno calcula su descanso por separado. Si dos turnos de la misma persona
se cruzan en horario, el editor avisa («⚠ se contará doble en las horas») pero
no lo bloquea.

## Enlace de solo lectura para el equipo

Cada equipo tiene un `share_token`. El enlace que se comparte es:

```
https://TU-DOMINIO/ver/<share_token>
```

Muestra la misma cuadrícula (con navegación de meses y auto-refresco cada 20 s)
pero **sin poder editar** nada y solo con los empleados de ese equipo. La página
lleva `noindex`. Si el enlace se filtra, se regenera desde ⚙ Equipos y el
anterior deja de funcionar.

## Plantilla semanal y generar el mes

Para no cargar el mismo turno día por día:

- **Plantilla por empleado** (botón **🗓 Plantillas**): cada empleado tiene un
  turno fijo de **entre semana** (`kind = weekday`, Lun–Vie) y otro de **fin de
  semana** (`kind = weekend`, Sáb–Dom). Poner «Activa: No» en el de fin de semana
  = ese empleado no trabaja sábados/domingos. Una fila por (empleado, kind).
- **Generar mes**: desde el mismo modal, «Generar mes completo» / «Solo entre
  semana» / «Solo fines de semana» crea los turnos del mes visible a partir de
  las plantillas. Aplica la regla de descanso/almuerzo del equipo. **No borra**
  nada y **omite** los turnos que quedarían idénticos a uno ya guardado
  (mismo empleado, fecha, entrada y salida), así que se puede volver a correr.
  Si cambias la plantilla y regeneras, los turnos nuevos se **agregan** además
  de los que ya había.
- **Repetir un turno puntual** (dentro del editor de turno → «Repetir este
  turno…»): casillas L–D (Lun–Vie marcadas) → crea copias de ese turno en los
  días elegidos del mes visible. También hay «↳ guardar como plantilla».

`weekdays` en `/api/shifts/repeat` usa la convención de JS `getDay()`:
0 = domingo … 6 = sábado.

## Contrato de la API

```
GET    /api/teams                        → [{id, name, slug, share_token, rule,
                                             break_len_min, break_interval_min,
                                             lunch_min, break_paid}, ...]
POST   /api/teams   {name, rule, ...}    → {id, ...}
PUT    /api/teams   {id, ...}            → {ok:true}
DELETE /api/teams?id=X                   → {ok:true}  (409 si tiene empleados)
POST   /api/teams/regenerate-token {id}  → {share_token}

GET    /api/employees?team=ID            → [{id, team_id, name, sort_order}, ...]
POST   /api/employees {name, team_id}    → {id, team_id, name, sort_order}
PUT    /api/employees {id, name[, team_id]} → {ok:true}
DELETE /api/employees?id=X               → {ok:true}   (cascada a sus turnos)

GET    /api/shifts?month=YYYY-MM[&team=ID] → [{id, employee_id, work_date,
                                              start_time, end_time, break_min,
                                              break_mode, lunch_start, cobro}, ...]
POST   /api/shifts {employee_id, work_date, start_time, end_time,
                    lunch_start?, break_min?, break_mode?, cobro?} → {id}
PUT    /api/shifts {id, ...}             → {ok:true}
DELETE /api/shifts?id=X                  → {ok:true}

GET    /api/ver/{token}/data?month=YYYY-MM → { team, employees, shifts }  (solo lectura)

GET    /api/templates?team=ID            → [{id, employee_id, kind, start_time,
                                             end_time, lunch_start, cobro, active}, ...]
POST   /api/templates {employee_id, kind, start_time, end_time,
                       lunch_start?, cobro?, active?}  → template  (upsert por employee_id+kind)
DELETE /api/templates?employee_id=X&kind=weekday|weekend → {ok:true}

POST   /api/schedule/generate {team_id, month, kinds?:["weekday","weekend"]}
                                          → {created, skipped}
POST   /api/shifts/repeat {employee_id, month, weekdays:[0..6], start_time,
                           end_time, lunch_start?, cobro?}  → {created, skipped}
```

`work_date` en `YYYY-MM-DD`, horas en `HH:mm`. El `break_min` / `lunch_start`
finales los decide el servidor según la regla del equipo del empleado.

## Correr en local (Laragon)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed        # base nueva
# o, sobre una base ya existente con datos: php artisan migrate
```

`.env` ya apunta a MySQL de Laragon (`horario_lunex`, `root`, sin clave).
Sesión/caché/colas en `file`/`sync`: la base solo tiene `teams`, `employees` y
`shifts`.

Abrir:
- `http://horariolunextelecom.test/` (si el dominio no resuelve, botón **Reload**
  en Laragon para que registre el vhost y el `hosts`).
- o `php artisan serve` → `http://127.0.0.1:8000/`.

## Pruebas

```bash
php artisan test
```

Cubren: creación de los dos equipos base, empleados por equipo, cascada al
borrar, regla de descanso CSR (varios casos + medianoche), almuerzo de
Contabilidad (se guarda, se descuenta, se rechaza si no cabe), filtro por
equipo y por mes, enlace de solo lectura (solo su equipo, token inválido → 404),
CRUD de equipos con el guard de borrado y regeneración de token, plantillas
(upsert único por empleado+kind), generar mes (cuenta de días entre semana / fin
de semana, plantilla inactiva no genera, regenerar omite idénticos pero sigue
siendo aditivo) y repetir turno por días de la semana.

## Subir a un hosting en línea (PHP + MySQL)

1. Subir el proyecto **sin** `/vendor` ni `.env`.
2. `composer install --no-dev --optimize-autoloader`.
3. `.env` desde `.env.example` con MySQL del hosting, `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL=https://tu-dominio`, y `php artisan key:generate`.
   `APP_URL` importa: es la base de los enlaces `/ver/...` que ve el equipo.
4. `php artisan migrate --seed --force`.
5. Dominio apuntando a `public/`. `php artisan config:cache route:cache`.
