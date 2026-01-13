<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/auth.php';
auth_start();
$pdoEarly = db();
$cntEarly = 0;
try { $cntEarly = (int)$pdoEarly->query('SELECT COUNT(*) AS c FROM users')->fetch()['c']; } catch (Throwable $e) { $cntEarly = 0; }
if ($cntEarly === 0) { header('Location: /public/register.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if (login($u, $p)) { header('Location: /public/index.php'); exit; }
    $error = 'Credenciais inválidas';
}
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Login</title><link rel="stylesheet" href="/public/assets/style.css"><style>.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}.brand-box{display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:16px}.brand-box img{max-height:120px;max-width:220px;width:auto;height:auto;border-radius:6px}.brand-box .name{font-size:20px;font-weight:600;color:#111827}.login-card{max-width:420px;width:100%}.login-card h2{margin:0 0 12px 0}</style></head><body data-theme="light">';
require_once __DIR__ . '/../src/db.php';
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
echo '<div class="login-wrap">';
echo '<div class="login-card card">';
echo '<div class="brand-box">';
if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
echo '<div class="name">' . htmlspecialchars($params['company_name'] ?? 'Custo3D') . '</div>';
echo '</div>';
echo '<h2>Entrar</h2>';
if ($error) { echo '<p style="color:#dc2626">' . htmlspecialchars($error) . '</p>'; }
echo '<form method="post">';
echo '<label>Usuário <input name="username" required></label><br>';
echo '<label>Senha <input type="password" name="password" required></label><br>';
echo '<button type="submit" class="button" style="width:100%">Entrar</button>';
echo '</form>';
echo '</div>';
echo '</div>';
echo '</body></html>';
