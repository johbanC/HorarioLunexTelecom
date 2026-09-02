<?php
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id, name, sort_order FROM employees ORDER BY sort_order, name');
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $input = read_json_body();
    $name = trim($input['name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el nombre del empleado']);
        exit;
    }
    $nextOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM employees')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO employees (name, sort_order) VALUES (?, ?)');
    $stmt->execute([$name, $nextOrder]);
    echo json_encode(['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'sort_order' => $nextOrder]);
    exit;
}

if ($method === 'PUT') {
    $input = read_json_body();
    $id = (int) ($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    if (!$id || $name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id o nombre']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE employees SET name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id']);
        exit;
    }
    // ON DELETE CASCADE en la base de datos también borra los turnos de este empleado.
    $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
