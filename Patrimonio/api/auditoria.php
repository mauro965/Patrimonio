<?php
// api/auditoria.php - Mapa Digital y Auditoría Física de Fin de Ciclo Lectivo
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$action = $_GET['action'] ?? 'map_data';

if ($action === 'map_data') {
    // Obtener todas las ubicaciones y sus estadísticas de bienes
    $stmtUbi = $pdo->query("SELECT * FROM ubicaciones ORDER BY id ASC");
    $ubicaciones = $stmtUbi->fetchAll();

    $stmtBienes = $pdo->query("SELECT id, identificacion, nro_cuenta, nro_orden, categoria, ubicacion_id, 
                               ubicacion_nombre, cantidad, valor_unitario, valor_total, estado, auditado_anual, 
                               fecha_auditoria, auditor_nombre 
                               FROM bienes WHERE activo = 1 ORDER BY nro_cuenta, nro_orden");
    $todosBienes = $stmtBienes->fetchAll();

    // Agrupar bienes por ubicacion_id
    $bienesPorUbicacion = [];
    $totalAuditadosGlobal = 0;
    $totalBienesGlobal = count($todosBienes);

    foreach ($todosBienes as $bien) {
        $uId = $bien['ubicacion_id'] ?: 0;
        if (!isset($bienesPorUbicacion[$uId])) {
            $bienesPorUbicacion[$uId] = [];
        }
        $bienesPorUbicacion[$uId][] = $bien;
        if ($bien['auditado_anual']) {
            $totalAuditadosGlobal++;
        }
    }

    $resultadoUbicaciones = [];
    foreach ($ubicaciones as $u) {
        $items = $bienesPorUbicacion[$u['id']] ?? [];
        $totalItems = count($items);
        $auditados = 0;
        $valorTotal = 0;
        $totalUnidades = 0;

        foreach ($items as $it) {
            if ($it['auditado_anual']) {
                $auditados++;
            }
            $valorTotal += floatval($it['valor_total']);
            $totalUnidades += intval($it['cantidad']);
        }

        $progreso = $totalItems > 0 ? round(($auditados / $totalItems) * 100, 1) : 100;

        $resultadoUbicaciones[] = array_merge($u, [
            'total_items' => $totalItems,
            'total_unidades' => $totalUnidades,
            'total_auditados' => $auditados,
            'total_pendientes' => $totalItems - $auditados,
            'valor_total' => $valorTotal,
            'progreso_porcentaje' => $progreso,
            'bienes' => $items
        ]);
    }

    $porcentajeGlobal = $totalBienesGlobal > 0 ? round(($totalAuditadosGlobal / $totalBienesGlobal) * 100, 1) : 0;

    jsonResponse([
        'success' => true,
        'resumen' => [
            'total_bienes' => $totalBienesGlobal,
            'total_auditados' => $totalAuditadosGlobal,
            'total_pendientes' => $totalBienesGlobal - $totalAuditadosGlobal,
            'porcentaje_global' => $porcentajeGlobal,
            'ciclo_lectivo' => date('Y')
        ],
        'ubicaciones' => $resultadoUbicaciones
    ]);
}

jsonResponse(['success' => false, 'error' => 'Acción no permitida'], 400);
