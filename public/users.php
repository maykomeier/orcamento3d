<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
try { $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY AUTO_INCREMENT, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'users';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u !== '' && $p !== '') {
      $hash = password_hash($p, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?,?)');
      try { $stmt->execute([$u,$hash]); } catch (Throwable $e) {}
    }
    header('Location: /public/users.php');
    exit;
  } elseif ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($id>0 && $u !== '') {
      if ($p !== '') {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?')->execute([$u,$hash,$id]);
      } else {
        $pdo->prepare('UPDATE users SET username=? WHERE id=?')->execute([$u,$id]);
      }
    }
    header('Location: /public/users.php');
    exit;
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    if ($id>0 && $count>1) {
      try { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]); } catch (Throwable $e) {}
    }
    header('Location: /public/users.php');
    exit;
  }
}
$rows = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Usuários</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Usuários</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
echo '<div class="layout">';
echo '<aside class="sidebar">';
echo '<div class="menu-title">Menu Principal</div>';
echo '<a href="/public/dashboard.php" class="' . ($active==='dashboard'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#dashboard"></use></svg> Dashboard</a>';
echo '<a href="/public/parameters.php" class="' . ($active==='parameters'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#settings"></use></svg> Parâmetros</a>';
echo '<a href="/public/clients.php" class="' . ($active==='clients'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Clientes</a>';
echo '<a href="/public/users.php" class="active"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Usuários</a>';
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
echo '<div class="card"><h3>Adicionar Usuário</h3><div class="toolbar"><button class="button" onclick="openCreateUser()">Novo Usuário</button></div></div>';
echo '<div class="card"><h3>Lista de Usuários</h3>';
echo '<table><tr><th>ID</th><th>Usuário</th><th>Criado em</th><th>Ações</th></tr>';
foreach ($rows as $r) {
  echo '<tr>';
  echo '<td>' . (int)$r['id'] . '</td>';
  echo '<td>' . htmlspecialchars($r['username']) . '</td>';
  echo '<td>' . htmlspecialchars(fmt_datetime($r['created_at'] ?? '')) . '</td>';
  echo '<td class="actions">';
  echo '<button class="icon-button" title="Editar" onclick="openEditUser(this)" data-id="' . (int)$r['id'] . '" data-username="' . htmlspecialchars($r['username']) . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
  echo '<form method="post" class="inline" onsubmit="return confirm(\'Excluir usuário?\')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button></form>';
  echo '</td>';
  echo '</tr>';
}
echo '</table>';
echo '</div>';
echo '</main></div><div id="modal" class="modal"><div class="modal-content"><div id="modal-body"></div><div class="modal-actions"><button class="button" onclick="closeModal()">Fechar</button></div></div></div><script src="/public/assets/js/theme.js"></script>';
echo <<<'JS'
<script>
function openModal(html){document.getElementById('modal-body').innerHTML=html;document.getElementById('modal').classList.add('open');}
function closeModal(){document.getElementById('modal').classList.remove('open');}
function openCreateUser(){
  openModal('<form method="post"><input type="hidden" name="action" value="create">'
    + '<label>Usuário <input name="username" required></label><br>'
    + '<label>Senha <input type="password" name="password" required></label><br>'
    + '<button type="submit">Adicionar</button></form>');
}
function openEditUser(btn){
  var d=btn.dataset;
  openModal('<form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'+d.id+'">'
    + '<label>Usuário <input name="username" value="'+(d.username||'')+'" required></label><br>'
    + '<label>Nova Senha <input type="password" name="password" placeholder="(deixe em branco para manter)"></label><br>'
    + '<button type="submit">Salvar</button></form>');
}
</script>
JS;