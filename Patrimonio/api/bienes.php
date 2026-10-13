<?php
// api/bienes.php - Gestión del Catálogo de Bienes Patrimoniales
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $categoria = trim($_GET['categoria'] ?? '');
    $ubicacion = trim($_GET['ubicacion'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $cuenta = trim($_GET['cuenta'] ?? '');
    $auditado = isset($_GET['auditado']) && $_GET['auditado'] !== '' ? intval($_GET['auditado']) : null;
    $soloActivos = isset($_GET['activos']) ? intval($_GET['activos']) : 1;

    $sql = "SELECT b.*, u.codigo as ubicacion_codigo, u.tipo as ubicacion_tipo 
            FROM bienes b 
            LEFT JOIN ubicaciones u ON b.ubicacion_id = u.id 
            WHERE 1=1";
    $params = [];

    if ($soloActivos === 1) {
        $sql .= " AND b.activo = 1";
    }

    if (!empty($search)) {
        $sql .= " AND (b.identificacion LIKE ? OR b.nro_cuenta LIKE ? OR b.expediente LIKE ? OR b.motivo_alta LIKE ?)";
        $wildcard = "%$search%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    if (!empty($categoria)) {
        $sql .= " AND b.categoria = ?";
        $params[] = $categoria;
    }

    if (!empty($ubicacion)) {
        $sql .= " AND (b.ubicacion_id = ? OR b.ubicacion_nombre LIKE ?)";
        $params[] = $ubicacion;
        $params[] = "%$ubicacion%";
    }

    if (!empty($estado)) {
        $sql .= " AND b.estado = ?";
        $params[] = $estado;
    }

    if (!empty($cuenta)) {
        $sql .= " AND b.nro_cuenta = ?";
        $params[] = $cuenta;
    }

    if ($auditado !== null) {
        $sql .= " AND b.auditado_anual = ?";
        $params[] = $auditado;
    }

    $sql .= " ORDER BY b.nro_cuenta ASC, b.nro_orden ASC, b.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bienes = $stmt->fetchAll();

    // Calcular totales
    $totalValor = 0;
    $totalUnidades = 0;
    foreach ($bienes as $b) {
        $totalValor += floatval($b['valor_total']);
        $totalUnidades += intval($b['cantidad']);
    }

    jsonResponse([
        'success' => true,
        'count' => count($bienes),
        'total_unidades' => $totalUnidades,
        'total_valor' => $totalValor,
        'data' => $bienes
    ]);
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM bienes WHERE id = ?");
    $stmt->execute([$id]);
    $bien = $stmt->fetch();

    if (!$bien) {
        jsonResponse(['success' => false, 'error' => 'Bien patrimonial no encontrado'], 404);
    }

    jsonResponse(['success' => true, 'data' => $bien]);
}

if ($action === 'create' && $method === 'POST') {
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $nro_cuenta = trim($input['nro_cuenta'] ?? '04-HER');
    $identificacion = trim($input['identificacion'] ?? '');
    $categoria = trim($input['categoria'] ?? 'Herramientas de Taller');
    $ubicacion_id = !empty($input['ubicacion_id']) ? intval($input['ubicacion_id']) : null;
    $ubicacion_nombre = trim($input['ubicacion_nombre'] ?? 'Taller de Mecánica y Ajuste');
    $cantidad = max(1, intval($input['cantidad'] ?? 1));
    $valor_unitario = max(0, floatval($input['valor_unitario'] ?? 0));
    $valor_total = $cantidad * $valor_unitario;
    $estado = in_array($input['estado'] ?? '', ['Bueno', 'Regular', 'En Reparación', 'Baja']) ? $input['estado'] : 'Bueno';
    $motivo_alta = trim($input['motivo_alta'] ?? 'Adquisición Fondos Provinciales');
    $expediente = trim($input['expediente'] ?? 'EX-' . date('Y') . '-' . rand(10000, 99999) . '-GDEBA-DGCYE');
    $fecha_alta = !empty($input['fecha_alta']) ? $input['fecha_alta'] : date('Y-m-d');
    $observaciones = trim($input['observaciones'] ?? '');
    $generar_movimiento = !empty($input['generar_movimiento']);

    if (empty($identificacion)) {
        jsonResponse(['success' => false, 'error' => 'La identificación/denominación del bien es obligatoria'], 400);
    }

    // Auto-calcular nro_orden para esta cuenta
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(nro_orden), 0) + 1 AS next_orden FROM bienes WHERE nro_cuenta = ?");
    $stmtMax->execute([$nro_cuenta]);
    $nextOrden = $stmtMax->fetchColumn() ?: 1;
    $nro_orden = !empty($input['nro_orden']) ? intval($input['nro_orden']) : $nextOrden;

    // Si viene ubicacion_id y no nombre, obtener nombre
    if ($ubicacion_id && empty($ubicacion_nombre)) {
        $stmtUbi = $pdo->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
        $stmtUbi->execute([$ubicacion_id]);
        $ubicacion_nombre = $stmtUbi->fetchColumn() ?: 'Ubicación General';
    }

    $insertSql = "INSERT INTO bienes (nro_cuenta, nro_orden, identificacion, categoria, ubicacion_id, ubicacion_nombre, 
                  cantidad, valor_unitario, valor_total, estado, motivo_alta, expediente, fecha_alta, observaciones, activo) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        $nro_cuenta, $nro_orden, $identificacion, $categoria, $ubicacion_id, $ubicacion_nombre,
        $cantidad, $valor_unitario, $valor_total, $estado, $motivo_alta, $expediente, $fecha_alta, $observaciones
    ]);
    $newId = $pdo->lastInsertId();

    // Registrar en movimientos de Alta oficial si está marcado o por defecto
    if ($generar_movimiento || true) {
        $trimestre = ceil(date('n', strtotime($fecha_alta)) / 3);
        $anio = date('Y', strtotime($fecha_alta));
        $userName = $user ? $user['nombre'] : 'Sistema Automático';
        $userId = $user ? $user['id'] : 1;

        $stmtMov = $pdo->prepare("INSERT INTO movimientos (bien_id, tipo, trimestre, anio, nro_cuenta, nro_orden, identificacion, 
                                  cantidad, valor_unitario, valor_total, motivo, expediente, fecha, usuario_id, usuario_nombre, observaciones) 
                                  VALUES (?, 'ALTA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtMov->execute([
            $newId, $trimestre, $anio, $nro_cuenta, $nro_orden, $identificacion,
            $cantidad, $valor_unitario, $valor_total, $motivo_alta, $expediente, $fecha_alta,
            $userId, $userName, $observaciones
        ]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Bien patrimonial registrado exitosamente en el catálogo oficial',
        'id' => $newId
    ]);
}

if ($action === 'update' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'ID inválido'], 400);
    }

    $nro_cuenta = trim($input['nro_cuenta'] ?? '');
    $nro_orden = intval($input['nro_orden'] ?? 1);
    $identificacion = trim($input['identificacion'] ?? '');
    $categoria = trim($input['categoria'] ?? '');
    $ubicacion_id = !empty($input['ubicacion_id']) ? intval($input['ubicacion_id']) : null;
    $ubicacion_nombre = trim($input['ubicacion_nombre'] ?? '');
    $cantidad = max(1, intval($input['cantidad'] ?? 1));
    $valor_unitario = max(0, floatval($input['valor_unitario'] ?? 0));
    $valor_total = $cantidad * $valor_unitario;
    $estado = $input['estado'] ?? 'Bueno';
    $motivo_alta = trim($input['motivo_alta'] ?? '');
    $expediente = trim($input['expediente'] ?? '');
    $observaciones = trim($input['observaciones'] ?? '');

    $stmt = $pdo->prepare("UPDATE bienes SET nro_cuenta=?, nro_orden=?, identificacion=?, categoria=?, 
                          ubicacion_id=?, ubicacion_nombre=?, cantidad=?, valor_unitario=?, valor_total=?, 
                          estado=?, motivo_alta=?, expediente=?, observaciones=? WHERE id=?");
    $stmt->execute([
        $nro_cuenta, $nro_orden, $identificacion, $categoria, $ubicacion_id, $ubicacion_nombre,
        $cantidad, $valor_unitario, $valor_total, $estado, $motivo_alta, $expediente, $observaciones, $id
    ]);

    jsonResponse(['success' => true, 'message' => 'Bien patrimonial actualizado correctamente']);
}

// Registrar BAJA Patrimonial Oficial (Decreto 3.300/72)
if ($action === 'baja' && $method === 'POST') {
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM bienes WHERE id = ?");
    $stmt->execute([$id]);
    $bien = $stmt->fetch();

    if (!$bien) {
        jsonResponse(['success' => false, 'error' => 'Bien no encontrado'], 404);
    }

    $motivo_baja = trim($input['motivo_baja'] ?? 'Deterioro irreparable / Desuso');
    $expediente_baja = trim($input['expediente_baja'] ?? 'EX-' . date('Y') . '-BAJA-DGCYE');
    $acta_disposicion = trim($input['acta_disposicion'] ?? 'DISPO-' . date('Y') . '-' . rand(10, 99));
    $cantidad_baja = min(intval($bien['cantidad']), max(1, intval($input['cantidad_baja'] ?? $bien['cantidad'])));
    $fecha_baja = !empty($input['fecha_baja']) ? $input['fecha_baja'] : date('Y-m-d');
    $observaciones = trim($input['observaciones'] ?? '');

    $trimestre = ceil(date('n', strtotime($fecha_baja)) / 3);
    $anio = date('Y', strtotime($fecha_baja));
    $userName = $user ? $user['nombre'] : 'Director / Delegado Patrimonial';
    $userId = $user ? $user['id'] : 1;

    $valor_total_baja = $cantidad_baja * floatval($bien['valor_unitario']);

    // Insertar en tabla de Movimientos Oficiales
    $stmtMov = $pdo->prepare("INSERT INTO movimientos (bien_id, tipo, trimestre, anio, nro_cuenta, nro_orden, identificacion, 
                              cantidad, valor_unitario, valor_total, motivo, expediente, fecha, usuario_id, usuario_nombre, 
                              acta_disposicion, observaciones) 
                              VALUES (?, 'BAJA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtMov->execute([
        $bien['id'], $trimestre, $anio, $bien['nro_cuenta'], $bien['nro_orden'], $bien['identificacion'],
        $cantidad_baja, $bien['valor_unitario'], $valor_total_baja, $motivo_baja, $expediente_baja, $fecha_baja,
        $userId, $userName, $acta_disposicion, $observaciones
    ]);

    // Si se dio de baja toda la cantidad, desactivar bien o marcar estado Baja
    if ($cantidad_baja >= intval($bien['cantidad'])) {
        $stmtUpd = $pdo->prepare("UPDATE bienes SET activo = 0, estado = 'Baja', observaciones = CONCAT(COALESCE(observaciones,''), ' [DADO DE BAJA Acta: ', ?, ']') WHERE id = ?");
        $stmtUpd->execute([$acta_disposicion, $id]);
    } else {
        $nuevaCantidad = intval($bien['cantidad']) - $cantidad_baja;
        $nuevoTotal = $nuevaCantidad * floatval($bien['valor_unitario']);
        $stmtUpd = $pdo->prepare("UPDATE bienes SET cantidad = ?, valor_total = ? WHERE id = ?");
        $stmtUpd->execute([$nuevaCantidad, $nuevoTotal, $id]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Baja patrimonial procesada y registrada en planilla oficial Decreto 3.300/72'
    ]);
}

// Alternar verificación de auditoría física anual
if ($action === 'toggle_audit' && $method === 'POST') {
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT auditado_anual FROM bienes WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        jsonResponse(['success' => false, 'error' => 'Bien no encontrado'], 404);
    }

    $newAudit = $current ? 0 : 1;
    $auditor = $newAudit ? ($user ? $user['nombre'] : 'Ayudante Técnico') : null;
    $fechaAud = $newAudit ? date('Y-m-d H:i:s') : null;

    $stmtUpd = $pdo->prepare("UPDATE bienes SET auditado_anual = ?, fecha_auditoria = ?, auditor_nombre = ? WHERE id = ?");
    $stmtUpd->execute([$newAudit, $fechaAud, $auditor, $id]);

    jsonResponse([
        'success' => true,
        'auditado_anual' => $newAudit,
        'auditor' => $auditor,
        'fecha' => $fechaAud,
        'message' => $newAudit ? 'Bien verificado físicamente para auditoría' : 'Verificación desmarcada'
    ]);
}

jsonResponse(['success' => false, 'error' => 'Acción no permitida'], 400);
