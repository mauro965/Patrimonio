<?php
// api/exportar.php - Generación de Planillas Oficiales y Archivos para Provincia
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$format = $_GET['format'] ?? 'json'; // 'csv', 'json'
$tipo = strtoupper($_GET['tipo'] ?? 'ALTAS'); // 'ALTAS', 'BAJAS', 'INVENTARIO'
$trimestre = intval($_GET['trimestre'] ?? 3);
$anio = intval($_GET['anio'] ?? 2024);

// Configuración de la institución
$stmtCfg = $pdo->query("SELECT * FROM institucion_config WHERE id = 1");
$config = $stmtCfg->fetch() ?: [
    'nombre_escuela' => 'luciano reyes',
    'cue' => '0601429-00',
    'distrito' => 'Dirección General de Cultura y Educación - Pcia. Bs. As.',
    'director_nombre' => 'Lucas Ariel Santucho',
    'delegado_patrimonial' => 'Mauro Ezequiel Gonzalez Ojeda',
    'secretario_nombre' => 'Ezequiel Alonso',
    'decreto_referencia' => 'Decreto Ley 3.300/72 DGCyE'
];

if ($tipo === 'INVENTARIO') {
    $stmt = $pdo->query("SELECT nro_cuenta, nro_orden, identificacion, categoria, ubicacion_nombre, 
                         cantidad, valor_unitario, valor_total, estado, motivo_alta, expediente, fecha_alta 
                         FROM bienes WHERE activo = 1 ORDER BY nro_cuenta, nro_orden");
    $items = $stmt->fetchAll();
    $titulo = "INVENTARIO GENERAL DE BIENES Y MOBILIARIO ESCOLAR";
} else {
    // ALTAS o BAJAS
    $tipoMov = ($tipo === 'BAJAS') ? 'BAJA' : 'ALTA';
    $stmt = $pdo->prepare("SELECT nro_cuenta, nro_orden, identificacion, cantidad, valor_unitario, valor_total, 
                           motivo, expediente, fecha, acta_disposicion, usuario_nombre, observaciones 
                           FROM movimientos 
                           WHERE tipo = ? AND trimestre = ? AND anio = ? 
                           ORDER BY nro_cuenta ASC, nro_orden ASC, fecha ASC");
    $stmt->execute([$tipoMov, $trimestre, $anio]);
    $items = $stmt->fetchAll();
    $titulo = ($tipo === 'BAJAS') 
        ? "PLANILLA TRIMESTRAL DE BAJAS PATRIMONIALES (ANEXO DECRETO 3.300/72)"
        : "PLANILLA TRIMESTRAL DE ALTAS PATRIMONIALES (ANEXO DECRETO 3.300/72)";
}

if ($format === 'csv') {
    $filename = "Patrimonio_" . $tipo . "_T" . $trimestre . "_" . $anio . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');

    // Metadatos oficiales en encabezado del archivo
    fputcsv($output, ["GOBIERNO DE LA PROVINCIA DE BUENOS AIRES - DGCYE - DIRECCION DE PATRIMONIO"]);
    fputcsv($output, [$titulo]);
    fputcsv($output, ["Institucion:", $config['nombre_escuela'], "CUE:", $config['cue']]);
    fputcsv($output, ["Distrito:", $config['distrito'], "Periodo:", $trimestre . " Trimestre " . $anio]);
    fputcsv($output, ["Marco Normativo:", $config['decreto_referencia']]);
    fputcsv($output, []); // linea vacia

    if ($tipo === 'INVENTARIO') {
        fputcsv($output, ['N° Cuenta', 'N° Orden', 'Denominacion / Identificacion', 'Categoria', 'Ubicacion', 'Cantidad', 'Valor Unitario ($)', 'Valor Total ($)', 'Estado', 'Motivo Alta', 'Expediente', 'Fecha']);
        $totalGeneral = 0;
        foreach ($items as $row) {
            $totalGeneral += floatval($row['valor_total']);
            fputcsv($output, [
                $row['nro_cuenta'],
                $row['nro_orden'],
                $row['identificacion'],
                $row['categoria'],
                $row['ubicacion_nombre'],
                $row['cantidad'],
                number_format($row['valor_unitario'], 2, ',', '.'),
                number_format($row['valor_total'], 2, ',', '.'),
                $row['estado'],
                $row['motivo_alta'],
                $row['expediente'],
                $row['fecha_alta']
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['', '', '', '', '', '', 'TOTAL VALUACION ($):', number_format($totalGeneral, 2, ',', '.')]);
    } else {
        fputcsv($output, ['N° Cuenta', 'N° Orden', 'Identificacion del Bien', 'Cantidad', 'Valor Unitario ($)', 'Valor Total ($)', 'Motivo Legal', 'N° Expediente', 'Acta/Disposicion', 'Fecha', 'Responsable']);
        $totalGeneral = 0;
        foreach ($items as $row) {
            $totalGeneral += floatval($row['valor_total']);
            fputcsv($output, [
                $row['nro_cuenta'],
                $row['nro_orden'],
                $row['identificacion'],
                $row['cantidad'],
                number_format($row['valor_unitario'], 2, ',', '.'),
                number_format($row['valor_total'], 2, ',', '.'),
                $row['motivo'],
                $row['expediente'],
                $row['acta_disposicion'] ?: 'N/A',
                $row['fecha'],
                $row['usuario_nombre']
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['', '', '', '', 'TOTAL MOVIMIENTO ($):', number_format($totalGeneral, 2, ',', '.')]);
    }

    fputcsv($output, []);
    fputcsv($output, ["Director:", $config['director_nombre'], "Delegado Patrimonial:", $config['delegado_patrimonial']]);
    fclose($output);
    exit;
}

// JSON Oficial para envío manual al servidor provincial o frontend
jsonResponse([
    'success' => true,
    'normativa' => 'Decreto Ley 3.300/72 de la Provincia de Buenos Aires',
    'institucion' => $config,
    'periodo' => [
        'tipo' => $tipo,
        'trimestre' => $trimestre,
        'anio' => $anio
    ],
    'titulo' => $titulo,
    'total_registros' => count($items),
    'items' => $items
]);
