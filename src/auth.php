<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
function auth_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
}
function current_user_id(): ?int {
    auth_start();
    $uid = $_SESSION['uid'] ?? null;
    if ($uid === null) return null;
    return is_int($uid) ? $uid : (int)$uid;
}
function require_login(): void {
    auth_start();
    if (!isset($_SESSION['uid'])) {
        header('Location: /public/login.php');
        exit;
    }
}
function login(string $username, string $password): bool {
    auth_start();
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY AUTO_INCREMENT, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username=?');
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u) return false;
        if (!password_verify($password, (string)$u['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['username'] = (string)$u['username'];
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
function logout(): void {
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    @session_destroy();
}
