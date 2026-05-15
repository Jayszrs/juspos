<?php
// includes/auth_middleware.php
session_start();
function require_login(){
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'not_authenticated']);
        exit;
    }
}
function require_role($roles = []) {
    if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], (array)$roles)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'forbidden']);
        exit;
    }
}
