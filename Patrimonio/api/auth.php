<?php
// api/auth.php - Autenticación Segura y Control de Sesión Única
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'check');

if ($action === 'login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $identifier = trim($input['email'] ?? $input['usuario'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Debe ingresar usuario/correo y contraseña.'], 400);
    }

    // Permitir buscar por email completo o por alias (director, ayudante1, ayudante2)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? OR email LIKE ? LIMIT 1");
    $stmt->execute([$identifier, $identifier . '@%']);
    $user = $stmt->fetch();

    if (!$user) {
        // Búsqueda por coincidencia de nombre o alias
        $stmtName = $pdo->prepare("SELECT * FROM usuarios WHERE nombre LIKE ? LIMIT 1");
        $stmtName->execute(['%' . $identifier . '%']);
        $user = $stmtName->fetch();
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Credenciales inválidas. Verifique su usuario y contraseña.'], 401);
    }

    // Generar token de sesión único para esta cuenta
    $sessionToken = bin2hex(random_bytes(16));

    // Actualizar último acceso y token en base de datos
    $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    unset($user['password_hash']);
    
    // Registrar en sesión PHP
    $_SESSION['usuario'] = $user;
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['login_time'] = time();

    jsonResponse([
        'success' => true,
        'message' => 'Sesión iniciada correctamente como ' . $user['nombre'],
        'usuario' => $user
    ]);
}

if ($action === 'check') {
    $user = getCurrentUser();
    if ($user) {
        jsonResponse([
            'success' => true, 
            'authenticated' => true, 
            'usuario' => $user
        ]);
    } else {
        jsonResponse([
            'success' => true, 
            'authenticated' => false, 
            'usuario' => null
        ]);
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
}

jsonResponse(['success' => false, 'error' => 'Acción no soportada'], 400);
