<?php
// api/admin_users.php
// CRUD users — updated to include email & phone (if present in DB).
// Documentasi singkat: file ini mendeteksi kolom email/phone/password_hash/password dan updated_at.
// - GET  (list)           -> ?page=&per_page=&q=  (mengembalikan rows + pagination)
// - GET  (single)         -> ?id=123
// - POST (create)         -> JSON { username, name, role, password, email?, phone? }
// - PUT  (update)         -> JSON { id, username?, name?, role?, password?, email?, phone? }
// - DELETE (delete)       -> JSON { id }
//
// Pastikan includes/db.php menginisialisasi $pdo (PDO) dan session sudah berjalan.

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// simple auth guard: must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if (!isset($pdo) || !$pdo) respond(['success' => false, 'error' => 'Database connection missing'], 500);

    // detect useful columns dynamically (robust to schema differences)
    $colEmail = db_column_exists($pdo, 'users', 'email');
    $colPhone = db_column_exists($pdo, 'users', 'phone');
    $colPasswordHash = db_column_exists($pdo, 'users', 'password_hash');
    $colPassword = db_column_exists($pdo, 'users', 'password');
    $colUpdatedAt = db_column_exists($pdo, 'users', 'updated_at');

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET handlers ---
    if ($method === 'GET') {
        // single
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int)$_GET['id'];
            $select = 'id, username, name, role, created_at';
            if ($colUpdatedAt) $select .= ', updated_at';
            if ($colEmail) $select .= ', email';
            if ($colPhone) $select .= ', phone';

            $stmt = $pdo->prepare("SELECT {$select} FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['success' => false, 'error' => 'User not found'], 404);
            respond(['success' => true, 'data' => $row]);
        }

        // list + pagination + q (search)
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(1, intval($_GET['per_page'] ?? 25));
        $q = trim($_GET['q'] ?? '');
        $offset = ($page - 1) * $per_page;

        $where = '1=1';
        $params = [];

        if ($q !== '') {
            // search username, name, role, email (if present)
            $like = "%$q%";
            $whereClauses = ["username LIKE :q", "name LIKE :q", "role LIKE :q"];
            if ($colEmail) $whereClauses[] = "email LIKE :q";
            $where = '(' . implode(' OR ', $whereClauses) . ')';
            $params[':q'] = $like;
        }

        // count
        $countSql = "SELECT COUNT(*) FROM users WHERE {$where}";
        $cnt = $pdo->prepare($countSql);
        $cnt->execute($params);
        $total_rows = (int)$cnt->fetchColumn();

        // select columns
        $select = 'id, username, name, role, created_at';
        if ($colUpdatedAt) $select .= ', updated_at';
        if ($colEmail) $select .= ', email';
        if ($colPhone) $select .= ', phone';

        // note: bind limit/offset as integers
        $sql = "SELECT {$select} FROM users WHERE {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = $per_page ? (int)ceil($total_rows / $per_page) : 1;
        respond([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_rows' => $total_rows,
                    'total_pages' => $total_pages
                ]
            ]
        ]);
    }

    // read JSON body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- POST (create) ---
    if ($method === 'POST') {
        if (!is_array($input)) respond(['success' => false, 'error' => 'Invalid JSON'], 400);
        $username = trim($input['username'] ?? '');
        $name = trim($input['name'] ?? '');
        $role = trim($input['role'] ?? '');
        $password = $input['password'] ?? '';
        $email = $colEmail ? trim($input['email'] ?? '') : null;
        $phone = $colPhone ? trim($input['phone'] ?? '') : null;

        if ($username === '' || $name === '' || $role === '') {
            respond(['success' => false, 'error' => 'username, name and role are required'], 400);
        }
        if ($password === '') {
            respond(['success' => false, 'error' => 'password is required for new user'], 400);
        }

        // optional: validate email format if provided
        if ($colEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'error' => 'Invalid email format'], 400);
        }

        // unique checks: username and email
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
        $chk->execute([':u' => $username]);
        if ($chk->fetchColumn() > 0) {
            respond(['success' => false, 'error' => 'Username already exists'], 409);
        }
        if ($colEmail && $email !== '') {
            $chkE = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
            $chkE->execute([':e' => $email]);
            if ($chkE->fetchColumn() > 0) {
                respond(['success' => false, 'error' => 'Email already exists'], 409);
            }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        // build insert based on available columns
        $fields = ['username','name','role','created_at'];
        $placeholders = [':username',':name',':role', 'NOW()'];
        $params = [
            ':username' => $username,
            ':name' => $name,
            ':role' => $role
        ];

        if ($colPasswordHash) {
            $fields[] = 'password_hash';
            $placeholders[] = ':ph';
            $params[':ph'] = $hash;
        } elseif ($colPassword) {
            $fields[] = db_ident('password');
            $placeholders[] = ':pw';
            $params[':pw'] = $hash;
        }

        if ($colEmail) {
            $fields[] = 'email';
            $placeholders[] = ':email';
            $params[':email'] = ($email === '' ? null : $email);
        }
        if ($colPhone) {
            $fields[] = 'phone';
            $placeholders[] = ':phone';
            $params[':phone'] = ($phone === '' ? null : $phone);
        }

        // build SQL
        $sqlFields = implode(', ', $fields);
        // remove the NOW() placeholder if present in placeholders and handle separately
        $phs = [];
        foreach ($placeholders as $p) {
            if ($p === 'NOW()') $phs[] = 'NOW()';
            else $phs[] = $p;
        }
        $sqlPlaceholders = implode(', ', $phs);

        $stmt = $pdo->prepare("INSERT INTO users ({$sqlFields}) VALUES ({$sqlPlaceholders})");
        $stmt->execute($params);

        $newId = db_last_insert_id($pdo, 'users');
        $select = 'id, username, name, role, created_at' . ($colUpdatedAt ? ', updated_at' : '');
        if ($colEmail) $select .= ', email';
        if ($colPhone) $select .= ', phone';
        $sel = $pdo->prepare("SELECT {$select} FROM users WHERE id = :id LIMIT 1");
        $sel->execute([':id' => $newId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        respond(['success' => true, 'data' => $row], 201);
    }

    // --- PUT (update) ---
    if ($method === 'PUT') {
        if (!is_array($input) || empty($input['id'])) respond(['success' => false, 'error' => 'id required'], 400);
        $id = (int)$input['id'];

        // fetch existing
        $chk = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $chk->execute([':id' => $id]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) respond(['success' => false, 'error' => 'User not found'], 404);

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('username', $input)) {
            $username = trim($input['username']);
            if ($username === '') respond(['success' => false, 'error' => 'username cannot be empty'], 400);
            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u AND id <> :id");
            $chk2->execute([':u' => $username, ':id' => $id]);
            if ($chk2->fetchColumn() > 0) respond(['success' => false, 'error' => 'Username already taken'], 409);
            $fields[] = "username = :username";
            $params[':username'] = $username;
        }
        if (array_key_exists('name', $input)) {
            $fields[] = "name = :name";
            $params[':name'] = trim($input['name']);
        }
        if (array_key_exists('role', $input)) {
            $fields[] = "role = :role";
            $params[':role'] = trim($input['role']);
        }
        if ($colEmail && array_key_exists('email', $input)) {
            $emailVal = trim($input['email']);
            if ($emailVal !== '' && !filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
                respond(['success' => false, 'error' => 'Invalid email format'], 400);
            }
            // ensure unique if changed
            if ($emailVal !== ($existing['email'] ?? '')) {
                $chkE = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id");
                $chkE->execute([':e' => $emailVal, ':id' => $id]);
                if ($chkE->fetchColumn() > 0) respond(['success' => false, 'error' => 'Email already taken'], 409);
            }
            $fields[] = "email = :email";
            $params[':email'] = ($emailVal === '' ? null : $emailVal);
        }
        if ($colPhone && array_key_exists('phone', $input)) {
            $phoneVal = trim($input['phone']);
            $fields[] = "phone = :phone";
            $params[':phone'] = ($phoneVal === '' ? null : $phoneVal);
        }
        if (array_key_exists('password', $input) && ($input['password'] !== null) && $input['password'] !== '') {
            $hash = password_hash($input['password'], PASSWORD_DEFAULT);
            if ($colPasswordHash) { $fields[] = "password_hash = :ph"; $params[':ph'] = $hash; }
            elseif ($colPassword) { $fields[] = db_ident('password') . " = :pw"; $params[':pw'] = $hash; }
        }

        if (count($fields) === 0) respond(['success' => false, 'error' => 'Nothing to update'], 400);

        if ($colUpdatedAt) $fields[] = "updated_at = NOW()";

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $select = 'id, username, name, role, created_at' . ($colUpdatedAt ? ', updated_at' : '');
        if ($colEmail) $select .= ', email';
        if ($colPhone) $select .= ', phone';
        $sel = $pdo->prepare("SELECT {$select} FROM users WHERE id = :id LIMIT 1");
        $sel->execute([':id' => $id]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        respond(['success' => true, 'data' => $row]);
    }

    // --- DELETE ---
    if ($method === 'DELETE') {
        if (!is_array($input) || empty($input['id']) || !is_numeric($input['id'])) respond(['success' => false, 'error' => 'id required'], 400);
        $id = (int)$input['id'];
        // prevent deleting yourself
        $meId = (int)($_SESSION['user_id']);
        if ($id === $meId) respond(['success' => false, 'error' => "You cannot delete your own account"], 400);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        respond(['success' => true]);
    }

    respond(['success' => false, 'error' => 'Method not allowed'], 405);

} catch (PDOException $ex) {
    respond(['success' => false, 'error' => 'Internal error', 'detail' => $ex->getMessage()], 500);
} catch (Exception $e) {
    respond(['success' => false, 'error' => 'Internal error', 'detail' => $e->getMessage()], 500);
}
