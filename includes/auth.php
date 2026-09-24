<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function require_login(): void {
    session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /public/login.php?err=' . urlencode('Please log in'));
        exit;
    }
}

function current_user(): ?array {
    session_start();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, name, email, avatar_url, auth_provider, email_verified FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function logout(): void {
    session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
    header('Location: /public/login.php');
    exit;
}

