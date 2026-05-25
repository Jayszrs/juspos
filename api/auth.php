<?php
// api/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php'; // sesuaikan path jika perlu
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? null;

// helper
function send($payload, $code = 200)
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $emailRaw = $in['email'] ?? null;
    $password = $in['password'] ?? null;
    $name = trim($in['name'] ?? '');
    $phone = trim($in['phone'] ?? '');

    if (!$emailRaw || !$password) {
        send(['success' => false, 'error' => 'missing_fields'], 400);
    }

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    if (!$email) send(['success' => false, 'error' => 'invalid_email'], 400);

    // derive username from email if not provided
    $username = $in['username'] ?? strtok($email, '@');

    try {
        // check email uniqueness
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $chk->execute([':email' => $email]);
        if ($chk->fetch()) {
            send(['success' => false, 'error' => 'email_exists'], 409);
        }

        // check username uniqueness (try to make unique by appending number if exists)
        $baseUsername = $username;
        $i = 0;
        while (true) {
            $s = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $s->execute([':u' => $username]);
            if (!$s->fetch()) break;
            $i++;
            $username = $baseUsername . $i;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // insert
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, name, phone, role, created_at) VALUES (:username, :email, :ph, :name, :phone, 'CASHIER', NOW())");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':ph' => $hash,
            ':name' => $name ?: null,
            ':phone' => $phone ?: null
        ]);

        $newId = $pdo->lastInsertId();

        send(['success' => true, 'user_id' => $newId], 201);
    } catch (PDOException $e) {
        // for dev show detail; in production mask this
        send(['success' => false, 'error' => 'register_failed', 'detail' => $e->getMessage()], 500);
    }
}

// login branch unchanged but make sure to reference password_hash column
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!($in['email'] ?? false) || !($in['password'] ?? false)) {
        send(['error' => 'missing_fields'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, password_hash, name, role, username, phone FROM users WHERE email = :e OR username = :u LIMIT 1");
        $stmt->execute([':e' => $in['email'], ':u' => $in['email']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($u && isset($u['password_hash']) && password_verify($in['password'], $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_role'] = isset($u['role']) ? strtoupper($u['role']) : null;
            $_SESSION['user_name'] = $u['name'] ?? $u['username'] ?? '';

            $role = $_SESSION['user_role'] ?? '';
            $redirect = ($role === 'ADMIN') ? 'admin_dashboard.php' : 'index.php';

            send([
                'success' => true,
                'user' => [
                    'id' => $u['id'],
                    'email' => $u['email'],
                    'role' => $u['role'],
                    'name' => $u['name'],
                    'phone' => $u['phone'] ?? null
                ],
                'redirect' => $redirect
            ], 200);
        } else {
            send(['error' => 'invalid_credentials'], 401);
        }
    } catch (Exception $e) {
        send(['error' => 'internal', 'detail' => $e->getMessage()], 500);
    }
}

if ($action === 'logout') {
    // logout handler (same as before)
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $acceptsJson = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax || $acceptsJson) {
        send(['success' => true]);
    }
    header('Location: /login.php');
    exit;
}

send(['error' => 'invalid_action'], 400);
