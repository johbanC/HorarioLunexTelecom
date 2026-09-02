<?php
// Conexión a la base de datos. Ajusta estos valores si tu MySQL usa otro
// usuario/clave (en Laragon por defecto es root sin contraseña), y de nuevo
// cuando subas esto a un servidor en línea (el hosting te dará sus propios
// datos de conexión).
$DB_HOST = '127.0.0.1';
$DB_NAME = 'horario_lunex';
$DB_USER = 'root';
$DB_PASS = '';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Cualquier error no controlado (ej: la tabla todavía no existe porque no se
// importó schema.sql) se devuelve como JSON con el detalle, en vez de una
// página de error en blanco que el navegador reporta como "HTTP 500".
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
    exit;
});

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos. Revisa host/usuario/clave en api/db.php. Detalle: ' . $e->getMessage()]);
    exit;
}

// Lee el cuerpo JSON de la petición (para POST/PUT).
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
