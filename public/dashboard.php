<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'dashboard';
$clients = (int)$pdo->query('SELECT COUNT(*) AS c FROM clients')->fetch()['c'];
$gcodes = (int)$pdo->query("SELECT COUNT(*) AS c FROM budget_items WHERE gcode_file IS NOT NULL AND gcode_file <> ''")->fetch()['c'];
$budgets = $pdo->query('SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM budgets')->fetch();
$approved = $pdo->query('SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM budgets WHERE approved=1')->fetch();
// receita recebida no financeiro
$received = 0.0;
try { $received = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) AS s FROM accounts_receivable WHERE received=1')->fetch()['s']; } catch (Throwable $e) { $received = 0.0; }
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Dashboard</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Dashboard</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#sun"></use></svg></button><a href="/public/index.php">Menu</a></header>';
echo '<div class="layout">';
echo '<aside class="sidebar">';
echo '<div class="menu-title">Menu Principal</div>';
echo '<a href="/public/dashboard.php" class="' . ($active==='dashboard'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#dashboard"></use></svg> Dashboard</a>';
echo '<a href="/public/parameters.php" class="' . ($active==='parameters'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#settings"></use></svg> Parâmetros</a>';
echo '<a href="/public/users.php" class="' . ($active==='users'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Usuários</a>';
echo '<a href="/public/clients.php" class="' . ($active==='clients'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Clientes</a>';
echo '<a href="/public/printers.php" class="' . ($active==='printers'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#printers"></use></svg> Impressoras</a>';
echo '<a href="/public/filaments.php" class="' . ($active==='filaments'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#filaments"></use></svg> Filamentos</a>';
echo '<a href="/public/services.php" class="' . ($active==='services'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#services"></use></svg> Serviços</a>';
echo '<a href="/public/budgets.php" class="' . ($active==='budgets'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#budgets"></use></svg> Orçamentos</a>';
echo '<a href="/public/orders.php" class="' . ($active==='orders'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Pedidos</a>';
echo '<a href="/public/finance.php" class="' . ($active==='finance'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#money"></use></svg> Financeiro</a>';
echo '<a href="/public/finance_categories.php" class="' . ($active==='finance_categories'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Categorias</a>';
echo '<a href="/public/reports.php" class="' . ($active==='reports'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Relatórios</a>';
echo '</aside>';
echo '<main class="content">';
echo '<h2>Visão geral do seu negócio de impressão 3D</h2>';
echo '<div class="widgets-grid">';
echo '<div class="widget w-money"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#money"></use></svg><h4>Receita Total</h4></div><div class="value">R$ ' . number_format($received,2,',','.') . '</div></div>';
echo '<div class="widget w-clients"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg><h4>Total de Clientes</h4></div><div class="value">' . $clients . '</div></div>';
echo '<div class="widget w-approved"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#check"></use></svg><h4>Orçamentos Aprovados</h4></div><div class="value">' . (int)$approved['c'] . '</div></div>';
echo '<div class="widget w-pending"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#clock"></use></svg><h4>Orçamentos Pendentes</h4></div><div class="value">' . ((int)$budgets['c'] - (int)$approved['c']) . '</div></div>';
echo '</div>';
echo '<h3>Orçamentos Recentes</h3>';
$recent = $pdo->query('SELECT id, client_id, date, total, approved FROM budgets ORDER BY date DESC, id DESC LIMIT 4')->fetchAll();
echo '<div class="widgets-grid">';
if ($recent) {
  foreach ($recent as $r) {
    $client = $pdo->prepare('SELECT name FROM clients WHERE id=?');
    $client->execute([$r['client_id']]);
    $cn = $client->fetch();
    $cls = ($r['approved']? 'w-approved' : 'w-pending');
    echo '<div class="widget ' . $cls . '">';
    echo '<div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg><h4>' . htmlspecialchars($cn['name'] ?? '') . '</h4></div>';
    echo '<div style="display:flex;justify-content:space-between;align-items:center">';
    echo '<span style="color:var(--muted)">' . htmlspecialchars(fmt_date($r['date'])) . '</span>';
    echo '<span>R$ ' . number_format((float)$r['total'],2,',','.') . '</span>';
    echo '</div>';
    echo ($r['approved']? '<span class="badge approved">Aprovado</span>' : '<span class="badge rejected">Pendente</span>');
    echo '</div>';
  }
}
echo '</div>';
echo '<h3>Recursos</h3>';
echo '<div class="widgets-grid">';
echo '<div class="widget w-resource"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#printers"></use></svg><h4>Impressoras</h4></div><div class="value">' . (int)$pdo->query('SELECT COUNT(*) AS c FROM printers')->fetch()['c'] . '</div></div>';
echo '<div class="widget w-resource"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#filaments"></use></svg><h4>Filamentos</h4></div><div class="value">' . (int)$pdo->query('SELECT COUNT(*) AS c FROM filaments')->fetch()['c'] . '</div></div>';
echo '<div class="widget w-resource"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#budgets"></use></svg><h4>Total Orçamentos</h4></div><div class="value">' . (int)$budgets['c'] . '</div></div>';
echo '</div>';
echo '<div class="widgets-grid">';
echo '<div class="widget w-trending"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#trending"></use></svg><h4>Continue Crescendo</h4></div><p>Gerencie seus orçamentos e maximize seus lucros</p><a class="button" href="/public/budgets.php">Ver Orçamentos</a></div>';
echo '</div>';
echo '</main></div>';
echo '<script src="/public/assets/js/theme.js"></script></body></html>';