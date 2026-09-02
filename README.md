# Horario Lunex — versión PHP + MySQL

Esta es la versión con base de datos real (MySQL) del planificador de horarios,
pensada para correr en tu servidor local (Laragon) y poder subirse después a
un hosting en línea sin cambios grandes.

## Estructura

```
HorarioLunexTelecom/
├── index.html          ← la app (interfaz completa)
├── schema.sql           ← script para crear la base de datos y sus tablas
├── api/
│   ├── db.php            ← conexión a MySQL (usuario/clave)
│   ├── employees.php      ← endpoint de empleados
│   └── shifts.php         ← endpoint de turnos
└── README.md            ← este archivo
```

## 1. Crear la base de datos (Laragon)

Laragon trae phpMyAdmin y HeidiSQL. Con cualquiera de los dos:

1. Abre phpMyAdmin (desde el menú de Laragon: "Database" → "phpMyAdmin", o
   entra a `http://localhost/phpmyadmin`).
2. Ve a la pestaña **"Importar"** ("Import").
3. Selecciona el archivo `schema.sql` de esta carpeta y dale a "Continuar" /
   "Go".

Esto crea la base de datos `horario_lunex`, las tablas `employees` y
`shifts`, y deja cargados los 5 empleados iniciales (Karelys, Juana,
Valentina, Juan Manuel, Juanita Restrepo) — luego los puedes editar, agregar
o borrar desde la misma app.

Alternativa por línea de comandos (si usas la terminal de Laragon):

```
mysql -u root < schema.sql
```

## 2. Revisar las credenciales

Abre `api/db.php`. Por defecto Laragon usa el usuario `root` sin contraseña,
que es lo que ya está configurado:

```php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'horario_lunex';
$DB_USER = 'root';
$DB_PASS = '';
```

Si tu instalación de Laragon usa otro usuario o clave, ajústalos aquí.

## 3. Abrir la app

Como la carpeta ya está dentro de `C:\laragon\www\`, Laragon la sirve
automáticamente. Puedes entrar por cualquiera de estas rutas:

- `http://horariolunextelecom.test/` (dominio automático de Laragon, revisa
  el menú de Laragon si no carga así)
- `http://localhost/HorarioLunexTelecom/`

Si todo quedó bien conectado, arriba a la derecha debe aparecer
**"Conectado · MySQL"**. Si sale un error de conexión, revisa el paso 2.

## 4. Cómo funciona a partir de aquí

- Todos los datos (empleados y turnos) ahora viven en la base de datos MySQL,
  no en el navegador — cualquiera que entre a la misma URL en la red local ve
  y edita la misma información.
- La app se actualiza sola cada 20 segundos y también al volver a la pestaña,
  para que varias personas trabajando a la vez vean los cambios de las demás
  sin recargar manualmente.
- Las reglas de descanso (15 min cada 3 horas, sin descontar del pago) y la
  cuadrícula por horas funcionan igual que antes.

## 5. Subir esto a un servidor en línea más adelante

Esta versión ya no depende de Claude ni de Laragon específicamente — es PHP +
MySQL estándar, así que sirve en cualquier hosting que ofrezca ambos (por
ejemplo Hostinger, cPanel, cualquier VPS con Apache/Nginx + PHP + MySQL).
Cuando llegue el momento:

1. Sube todos los archivos de esta carpeta (`index.html`, `schema.sql`, la
   carpeta `api/`) al hosting, tal como están.
2. Crea la base de datos en el panel del hosting (o usa `schema.sql` igual
   que en el paso 1) e importa `schema.sql`.
3. Actualiza `api/db.php` con los datos de conexión que te dé el hosting
   (host, nombre de base de datos, usuario y clave — normalmente distintos a
   los de Laragon).
4. Listo — entras por la URL del hosting y todo el equipo puede usarla desde
   internet, no solo en la red local.
