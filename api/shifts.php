<?php
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

function valid_break_mode($v) {
    return in_array($v, ['auto', 'manual'], true) ? $v : 'auto';
}
function valid_cobro($v) {
    return $v === 'posterior' ? 'posterior' : 'anticipado';
}

if ($method === 'GET') {
    $month = $_GET['month'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetro month inválido (usa YYYY-MM)']);
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT id, employee_id,
                DATE_FORMAT(work_date, '%Y-%m-%d') AS work_date,
                TIME_FORMAT(start_time, '%H:%i') AS start_time,
                TIME_FORMAT(end_time, '%H:%i') AS end_time,
                break_min, break_mode, cobro
         FROM shifts
         WHERE DATE_FORMAT(work_date, '%Y-%m') = ?
         ORDER BY work_date, employee_id, id"
    );
    $stmt->execute([$month]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $input = read_json_body();
    foreach (['employee_id', 'work_date', 'start_time', 'end_time'] as $f) {
        if (empty($input[$f])) {
            http_response_code(400);
            echo json_encode(['error' => "Falta el campo $f"]);
            exit;
        }
    }
    $stmt = $pdo->prepare(
        'INSERT INTO shifts (employee_id, work_date, start_time, end_time, break_min, break_mode, cobro)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $input['employee_id'],
        $input['work_date'],
        $input['start_time'],
        $input['end_time'],
        (int) ($input['break_min'] ?? 0),
        valid_break_mode($input['break_mode'] ?? 'auto'),
        valid_cobro($input['cobro'] ?? 'anticipado'),
    ]);
    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $input = read_json_body();
    $id = (int) ($input['id'] ?? 0);
    foreach (['employee_id', 'work_date', 'start_time', 'end_time'] as $f) {
        if (empty($input[$f])) {
            http_response_code(400);
            echo json_encode(['error' => "Falta el campo $f"]);
            exit;
        }
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id']);
        exit;
    }
    $stmt = $pdo->prepare(
        'UPDATE shifts
         SET employee_id = ?, work_date = ?, start_time = ?, end_time = ?, break_min = ?, break_mode = ?, cobro = ?
         WHERE id = ?'
    );
    $stmt->execute([
        (int) $input['employee_id'],
        $input['work_date'],
        $input['start_time'],
        $input['end_time'],
        (int) ($input['break_min'] ?? 0),
        valid_break_mode($input['break_mode'] ?? 'auto'),
        valid_cobro($input['cobro'] ?? 'anticipado'),
        $id,
    ]);
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
    $stmt = $pdo->prepare('DELETE FROM shifts WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
