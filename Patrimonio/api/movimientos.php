<?php
// api/movimientos.php - Planillas de Altas y Bajas Trimestrales
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $tipo = trim($_GET['tipo'] ?? ''); // 'ALTA', 'BAJA', o vacío para todos
    $trimestre = isset($_GET['trimestre']) && $_GET['trimestre'] !== '' ? intval($_GET['trimestre']) : null;
    $anio = isset($_GET['anio']) && $_GET['anio'] !== '' ? intval($_GET['anio']) : null;

    $sql = "SELECT m.*, u.rol as usuario_rol 
            FROM movimientos m 
            LEFT JOIN usuarios u ON m.usuario_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($tipo)) {
        $sql .= " AND m.tipo = ?";
        $params[] = strtoupper($tipo);
    }

    if ($trimestre !== null) {
        $sql .= " AND m.trimestre = ?";
        $params[] = $trimestre;
    }

    if ($anio !== null) {
        $sql .= " AND m.anio = ?";
        $params[] = $anio;
    }

    $sql .= " ORDER BY m.fecha DESC, m.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll();

    // Sumatorias
    $totalAltasValor = 0;
    $totalAltasCant = 0;
    $totalBajasValor = 0;
    $totalBajasCant = 0;

    foreach ($movimientos as $m) {
        if ($m['tipo'] === 'ALTA') {
            $totalAltasValor += floatval($m['valor_total']);
            $totalAltasCant += intval($m['cantidad']);
        } else {
            $totalBajasValor += floatval($m['valor_total']);
            $totalBajasCant += intval($m['cantidad']);
        }
    }

    jsonResponse([
        'success' => true,
        'count' => count($movimientos),
        'totales' => [
            'altas_cant' => $totalAltasCant,
            'altas_valor' => $totalAltasValor,
            'bajas_cant' => $totalBajasCant,
            'bajas_valor' => $totalBajasValor
        ],
        'data' => $movimientos
    ]);
}

if ($action === 'create' && $method === 'POST') {
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $tipo = strtoupper(trim($input['tipo'] ?? 'ALTA'));
    if (!in_array($tipo, ['ALTA', 'BAJA'])) {
        jsonResponse(['success' => false, 'error' => 'Tipo de movimiento inválido'], 400);
    }

    $nro_cuenta = trim($input['nro_cuenta'] ?? '04-HER');
    $nro_orden = intval($input['nro_orden'] ?? 1);
    $identificacion = trim($input['identificacion'] ?? '');
    $cantidad = max(1, intval($input['cantidad'] ?? 1));
    $valor_unitario = max(0, floatval($input['valor_unitario'] ?? 0));
    $valor_total = $cantidad * $valor_unitario;
    $motivo = trim($input['motivo'] ?? ($tipo === 'ALTA' ? 'Compra DGCyE' : 'Desuso por deterioro'));
    $expediente = trim($input['expediente'] ?? 'EX-' . date('Y') . '-OFICIAL-PBA');
    $acta_disposicion = trim($input['acta_disposicion'] ?? '');
    $fecha = !empty($input['fecha']) ? $input['fecha'] : date('Y-m-d');
    $trimestre = intval($input['trimestre'] ?? ceil(date('n', strtotime($fecha)) / 3));
    $anio = intval($input['anio'] ?? date('Y', strtotime($fecha)));
    $observaciones = trim($input['observaciones'] ?? '');

    $userName = $user ? $user['nombre'] : 'Ayudante Técnico';
    $userId = $user ? $user['id'] : 1;

    $sql = "INSERT INTO movimientos (tipo, trimestre, anio, nro_cuenta, nro_orden, identificacion, cantidad, 
            valor_unitario, valor_total, motivo, expediente, fecha, usuario_id, usuario_nombre, acta_disposicion, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tipo, $trimestre, $anio, $nro_cuenta, $nro_orden, $identificacion, $cantidad,
        $valor_unitario, $valor_total, $motivo, $expediente, $fecha, $userId, $userName, $acta_disposicion, $observaciones
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Movimiento de ' . $tipo . ' registrado oficialmente',
        'id' => $pdo->lastInsertId()
    ]);
}

jsonResponse(['success' => false, 'error' => 'Acción no permitida'], 400);
