<?php
// config/db.php - Conexión a Base de Datos con Auto-instalación de respaldo
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'patrimonio_escolar');

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Si no existe la base de datos, intentar crearla automáticamente
        try {
            $rootDsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            
            // Reintentar conexión
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Si no hay tablas, ejecutar database.sql
            $sqlFile = dirname(__DIR__) . '/database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
            return $pdo;
        } catch (Exception $ex) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Error de conexión a la Base de Datos: ' . $ex->getMessage()
            ]);
            exit;
        }
    }
}

// Función helper para enviar respuestas JSON limpias
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Helper para verificar usuario logueado
function getCurrentUser() {
    if (!empty($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }
    return null;
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'No autorizado. Por favor inicie sesión.'], 401);
    }
    return $user;
}

function requireRole($allowedRoles = ['director']) {
    $user = requireAuth();
    if (!in_array($user['rol'], (array)$allowedRoles)) {
        jsonResponse(['success' => false, 'error' => 'Acceso denegado: permiso exclusivo para ' . implode(' o ', (array)$allowedRoles)], 403);
    }
    return $user;
}
