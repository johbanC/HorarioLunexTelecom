# Horario Lunex — versión Laravel

Reconstrucción en Laravel 13 del planificador de horarios del equipo CSR de
Lunex Telecom. Reemplaza la versión PHP plano (que queda en la carpeta como
referencia: `index.html`, `schema.sql`, `api/`).

## Qué se creó

| Pieza | Archivo |
|---|---|
| Migración empleados | `database/migrations/2026_09_02_000001_create_employees_table.php` |
| Migración turnos | `database/migrations/2026_09_02_000002_create_shifts_table.php` |
| Modelos | `app/Models/Employee.php`, `app/Models/Shift.php` |
| Controladores API | `app/Http/Controllers/Api/EmployeeController.php`, `ShiftController.php` |
| Rutas API | `routes/api.php` (registradas en `bootstrap/app.php`) |
| Seeder | `database/seeders/EmployeeSeeder.php` (Karelys, Juana, Valentina, Juan Manuel, Juanita Restrepo) |
| Frontend | `resources/views/app.blade.php` (el `index.html` original, con `fetch` apuntando a `/api/...`) servido en `/` desde `routes/web.php` |
| Pruebas | `tests/Feature/ScheduleApiTest.php` |

## Reglas de negocio implementadas

- **Descanso automático**: 15 min por cada 3 horas completas trabajadas, y solo
  si queda turno después (nunca al salir). Turno de 6h = 1 descanso; de 9h = 2.
  Se calcula en el servidor (`ShiftController::autoBreakMinutes`) al guardar
  cuando `break_mode = auto`; con `manual` se respeta el valor enviado.
- **El descanso NO se descuenta del pago**: las horas pagadas = duración total
  del turno (el frontend lo calcula en `shiftHours`).
- Turnos que cruzan medianoche (ej. 22:00–02:00) se manejan sumando 24 h.
- Cada turno lleva `cobro` = `anticipado` | `posterior`, resumido en las stats.
- Borrar un empleado elimina en cascada sus turnos (FK `ON DELETE CASCADE`).

## Contrato de la API

```
GET    /api/employees              → [{id, name, sort_order}, ...]
POST   /api/employees   {name}     → {id, name, sort_order}
PUT    /api/employees   {id, name} → {ok:true}
DELETE /api/employees?id=X         → {ok:true}   (cascada a sus turnos)

GET    /api/shifts?month=YYYY-MM   → [{id, employee_id, work_date, start_time,
                                       end_time, break_min, break_mode, cobro}, ...]
                                      (work_date "YYYY-MM-DD", horas "HH:mm")
POST   /api/shifts   {employee_id, work_date, start_time, end_time,
                       break_min, break_mode, cobro}  → {id}
PUT    /api/shifts   {id, ...}      → {ok:true}
DELETE /api/shifts?id=X             → {ok:true}
```

## Correr en local (Laragon)

Ya está todo hecho, pero para reproducirlo desde cero:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

`.env` ya viene apuntando a MySQL de Laragon:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=horario_lunex
DB_USERNAME=root
DB_PASSWORD=
```

Crear tablas y datos iniciales:

```bash
php artisan migrate:fresh --seed
```

Abrir la app:

- `http://horariolunextelecom.test/` (Laragon detecta Laravel y sirve `public/`;
  si no toma el dominio, en el menú de Laragon: Apache → reload, o Menu → www →
  el proyecto).
- o `php artisan serve` y entrar a `http://127.0.0.1:8000/`.

Sesión/caché/colas están en `file`/`sync`, así que la base de datos solo tiene
las tablas `employees` y `shifts` (más limpio para el hosting).

## Pruebas

```bash
php artisan test
```

Cubren: listado y orden de empleados, alta al final de la lista, cascada al
borrar, la regla de descanso (varios casos, incluida medianoche), descanso
manual, y el filtro por mes.

## Subir a un hosting en línea (PHP + MySQL)

1. Subir todo el proyecto **menos** `/vendor` y `.env`.
2. En el servidor: `composer install --no-dev --optimize-autoloader`.
3. Crear `.env` (copiar de `.env.example`) con los datos de MySQL del hosting,
   `APP_ENV=production`, `APP_DEBUG=false`, y `php artisan key:generate`.
4. `php artisan migrate --seed --force`.
5. Apuntar el dominio a la carpeta `public/` (o usar el `public/.htaccess` que
   ya viene). `php artisan config:cache route:cache` para producción.
