<?php
// api/estadisticas.php - Métricas Institucionales y Balances
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'dashboard') {
    // Configuración escolar
    $stmtCfg = $pdo->query("SELECT * FROM institucion_config WHERE id = 1");
    $config = $stmtCfg->fetch() ?: [
        'nombre_escuela' => 'luciano reyes',
        'cue' => '0601429-00',
        'distrito' => 'Dirección General de Cultura y Educación - Pcia. Bs. As.',
        'director_nombre' => 'Lucas Ariel Santucho',
        'delegado_patrimonial' => 'Mauro Ezequiel Gonzalez Ojeda',
        'secretario_nombre' => 'Ezequiel Alonso',
        'decreto_referencia' => 'Decreto Ley 3.300/72 DGCyE',
        'trimestre_activo' => 3,
        'anio_activo' => date('Y')
    ];

    // Totales del catálogo general
    $stmtCat = $pdo->query("SELECT 
        COUNT(*) as total_registros,
        COALESCE(SUM(cantidad), 0) as total_unidades,
        COALESCE(SUM(valor_total), 0) as valuacion_total,
        COALESCE(SUM(CASE WHEN auditado_anual = 1 THEN 1 ELSE 0 END), 0) as auditados_count
        FROM bienes WHERE activo = 1");
    $cat = $stmtCat->fetch();

    $totalReg = intval($cat['total_registros']);
    $auditados = intval($cat['auditados_count']);
    $auditProgress = $totalReg > 0 ? round(($auditados / $totalReg) * 100, 1) : 0;

    // Altas y Bajas del Trimestre Activo
    $stmtMov = $pdo->prepare("SELECT 
        tipo,
        COUNT(*) as total_movs,
        COALESCE(SUM(cantidad), 0) as total_cant,
        COALESCE(SUM(valor_total), 0) as total_monto
        FROM movimientos 
        WHERE trimestre = ? AND anio = ?
        GROUP BY tipo");
    $stmtMov->execute([$config['trimestre_activo'], $config['anio_activo']]);
    $movs = $stmtMov->fetchAll();

    $altasTrim = ['cant' => 0, 'monto' => 0, 'movs' => 0];
    $bajasTrim = ['cant' => 0, 'monto' => 0, 'movs' => 0];

    foreach ($movs as $m) {
        if ($m['tipo'] === 'ALTA') {
            $altasTrim = [
                'cant' => intval($m['total_cant']),
                'monto' => floatval($m['total_monto']),
                'movs' => intval($m['total_movs'])
            ];
        } else if ($m['tipo'] === 'BAJA') {
            $bajasTrim = [
                'cant' => intval($m['total_cant']),
                'monto' => floatval($m['total_monto']),
                'movs' => intval($m['total_movs'])
            ];
        }
    }

    // Desglose por Categoría
    $stmtCatList = $pdo->query("SELECT 
        categoria, 
        COUNT(*) as cantidad_bienes, 
        SUM(cantidad) as unidades, 
        SUM(valor_total) as valor_total 
        FROM bienes WHERE activo = 1 
        GROUP BY categoria 
        ORDER BY valor_total DESC");
    $porCategoria = $stmtCatList->fetchAll();

    // Desglose por Cuenta
    $stmtCuentaList = $pdo->query("SELECT 
        nro_cuenta, 
        COUNT(*) as cantidad_bienes, 
        SUM(cantidad) as unidades, 
        SUM(valor_total) as valor_total 
        FROM bienes WHERE activo = 1 
        GROUP BY nro_cuenta 
        ORDER BY nro_cuenta ASC");
    $porCuenta = $stmtCuentaList->fetchAll();

    // Estado de Bienes
    $stmtEstado = $pdo->query("SELECT 
        estado, COUNT(*) as cantidad 
        FROM bienes WHERE activo = 1 
        GROUP BY estado");
    $porEstado = $stmtEstado->fetchAll();

    jsonResponse([
        'success' => true,
        'config' => $config,
        'metricas' => [
            'total_registros' => $totalReg,
            'total_unidades' => intval($cat['total_unidades']),
            'valuacion_total' => floatval($cat['valuacion_total']),
            'auditados_count' => $auditados,
            'pendientes_auditoria' => $totalReg - $auditados,
            'progreso_auditoria' => $auditProgress,
            'altas_trimestre' => $altasTrim,
            'bajas_trimestre' => $bajasTrim
        ],
        'desglose' => [
            'por_categoria' => $porCategoria,
            'por_cuenta' => $porCuenta,
            'por_estado' => $porEstado
        ]
    ]);
}

jsonResponse(['success' => false, 'error' => 'Acción no permitida'], 400);
