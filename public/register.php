<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY AUTO_INCREMENT, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$cnt = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
if ($cnt > 0) { header('Location: /public/login.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u !== '' && $p !== '') {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?,?)');
        try { $stmt->execute([$u,$hash]); header('Location: /public/login.php'); exit; } catch (Throwable $e) { $error = 'Usuário já existe'; }
    } else { $error = 'Preencha usuário e senha'; }
}
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Registrar</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<span class="spacer"></span></header>';
echo '<h2>Registrar primeiro usuário</h2>';
if ($error) { echo '<p style="color:red">' . htmlspecialchars($error) . '</p>'; }
echo '<form method="post">';
echo '<label>Usuário <input name="username"></label><br>';
echo '<label>Senha <input type="password" name="password"></label><br>';
echo '<button type="submit">Registrar</button>';
echo '</form>';
echo '</body></html>';