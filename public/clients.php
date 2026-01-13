<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'clients';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $pdo->prepare('INSERT INTO clients (name, phone, email, note) VALUES (?,?,?,?)')->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['note'] ?? '')
        ]);
    } elseif ($action === 'update') {
        $pdo->prepare('UPDATE clients SET name=?, phone=?, email=?, note=? WHERE id=?')->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['note'] ?? ''),
            (int)($_POST['id'] ?? 0)
        ]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM clients WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    }
    header('Location: /public/clients.php');
    exit;
}
$rows = $pdo->query('SELECT * FROM clients ORDER BY id DESC')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Clientes</title><link rel="stylesheet" href="/public/assets/style.css"><script src="/public/assets/js/mask.js"></script></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Clientes</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
echo '<div class="layout">';
echo '<aside class="sidebar">';
echo '<div class="menu-title">Menu Principal</div>';
echo '<a href="/public/dashboard.php" class="' . ($active==='dashboard'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#dashboard"></use></svg> Dashboard</a>';
echo '<a href="/public/parameters.php" class="' . ($active==='parameters'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#settings"></use></svg> Parâmetros</a>';
echo '<a href="/public/clients.php" class="' . ($active==='clients'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Clientes</a>';
echo '<a href="/public/users.php" class="' . ($active==='users'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#clients"></use></svg> Usuários</a>';
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
echo '<div class="toolbar"><button class="button" onclick="openCreateClient()">Novo Cliente</button></div>';
echo '<table><tr><th>ID</th><th>Nome</th><th>Telefone</th><th>E-mail</th><th>Obs</th><th>Ações</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . $r['id'] . '</td>';
    echo '<td>' . htmlspecialchars($r['name']) . '</td>';
    echo '<td>' . htmlspecialchars($r['phone']) . '</td>';
    echo '<td>' . htmlspecialchars($r['email']) . '</td>';
    echo '<td>' . htmlspecialchars($r['note']) . '</td>';
    echo '<td class="actions">';
    echo '<details><summary class="icon-button" title="Ver"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></summary><div>';        
    echo '<div>ID: ' . $r['id'] . '</div><div>Nome: ' . htmlspecialchars($r['name']) . '</div><div>Telefone: ' . htmlspecialchars($r['phone']) . '</div><div>E-mail: ' . htmlspecialchars($r['email']) . '</div><div>Obs: ' . htmlspecialchars($r['note']) . '</div>';
    echo '</div></details>';
    echo '<button class="icon-button" title="Editar" onclick="openEditClient(this)" data-id="' . $r['id'] . '" data-name="' . htmlspecialchars($r['name']) . '" data-phone="' . htmlspecialchars($r['phone']) . '" data-email="' . htmlspecialchars($r['email']) . '" data-note="' . htmlspecialchars($r['note']) . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
    echo '<form method="post" class="inline">';
    echo '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $r['id'] . '">';
    echo '<button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>';
echo '<p><a href="/public/index.php">Voltar</a></p>';
echo '</main></div><div id="modal" class="modal"><div class="modal-content"><div id="modal-body"></div><div class="modal-actions"><button class="button" onclick="closeModal()">Fechar</button></div></div></div><script src="/public/assets/js/theme.js"></script>';
echo <<<'JS'
<script>
function openModal(html){document.getElementById('modal-body').innerHTML=html;document.getElementById('modal').classList.add('open');}
function closeModal(){document.getElementById('modal').classList.remove('open');}
function openCreateClient(){
  openModal('<form method="post"><input type="hidden" name="action" value="create">'
    + '<label>Nome <input required name="name"></label> '
    + '<label>Telefone <input name="phone" id="phone" pattern="\\(\\d{2}\\)\\d{4,5}-\\d{4}"></label> '
    + '<label>E-mail <input type="email" name="email"></label> '
    + '<label>Observação <input name="note"></label> '
    + '<button type="submit">Adicionar</button></form>');
}
function openEditClient(btn){
  var d=btn.dataset;
  openModal('<form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'+d.id+'">'
    + '<label>Nome <input name="name" value="'+(d.name||'')+'"></label> '
    + '<label>Telefone <input name="phone" value="'+(d.phone||'')+'" pattern="\\(\\d{2}\\)\\d{4,5}-\\d{4}"></label> '
    + '<label>E-mail <input type="email" name="email" value="'+(d.email||'')+'"></label> '
    + '<label>Observação <input name="note" value="'+(d.note||'')+'"></label> '
    + '<button type="submit">Salvar</button></form>');
}
</script></body></html>
JS;