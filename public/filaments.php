<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'filaments';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $pdo->prepare('INSERT INTO filaments (description, brand, type, price_per_kg) VALUES (?,?,?,?)')->execute([
            trim($_POST['description'] ?? ''),
            trim($_POST['brand'] ?? ''),
            trim($_POST['type'] ?? 'PLA'),
            (float)($_POST['price_per_kg'] ?? 0)
        ]);
    } elseif ($action === 'update') {
        $pdo->prepare('UPDATE filaments SET description=?, brand=?, type=?, price_per_kg=? WHERE id=?')->execute([
            trim($_POST['description'] ?? ''),
            trim($_POST['brand'] ?? ''),
            trim($_POST['type'] ?? 'PLA'),
            (float)($_POST['price_per_kg'] ?? 0),
            (int)($_POST['id'] ?? 0)
        ]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM filaments WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    }
    header('Location: /public/filaments.php');
    exit;
}
$rows = $pdo->query('SELECT * FROM filaments ORDER BY id DESC')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Filamentos</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Filamentos</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<div class="toolbar"><button class="button" onclick="openCreateFilament()">Novo Filamento</button></div>';
echo '<table><tr><th>ID</th><th>Descrição</th><th>Marca</th><th>Tipo</th><th>Preço/kg</th><th>Ações</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . $r['id'] . '</td>';
    echo '<td>' . htmlspecialchars($r['description']) . '</td>';
    echo '<td>' . htmlspecialchars($r['brand']) . '</td>';
    echo '<td>' . htmlspecialchars($r['type']) . '</td>';
    echo '<td>' . number_format((float)$r['price_per_kg'],2,',','.') . '</td>';
    echo '<td class="actions">';
    echo '<details><summary class="icon-button" title="Ver"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></summary><div>';
    echo '<div>ID: ' . $r['id'] . '</div><div>Descrição: ' . htmlspecialchars($r['description']) . '</div><div>Marca: ' . htmlspecialchars($r['brand']) . '</div><div>Tipo: ' . htmlspecialchars($r['type']) . '</div><div>Preço/kg: R$ ' . number_format((float)$r['price_per_kg'],2,',','.') . '</div>';
    echo '</div></details>';
    echo '<button class="icon-button" title="Editar" onclick="openEditFilament(this)" data-id="' . $r['id'] . '" data-description="' . htmlspecialchars($r['description']) . '" data-brand="' . htmlspecialchars($r['brand']) . '" data-type="' . htmlspecialchars($r['type']) . '" data-price="' . htmlspecialchars((string)$r['price_per_kg']) . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
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
function openCreateFilament(){
  openModal('<form method="post"><input type="hidden" name="action" value="create">'
    + '<label>Descrição <input required name="description"></label> '
    + '<label>Marca <input name="brand"></label> '
    + '<label>Tipo <select name="type"><option>PLA</option><option>PET-G</option><option>ABS</option><option>TPU</option><option>ASA</option></select></label> '
    + '<label>Preço/kg (R$) <input type="number" step="0.01" name="price_per_kg"></label> '
    + '<button type="submit">Adicionar</button></form>');
}
function openEditFilament(btn){
  var d=btn.dataset;
  var opts=['PLA','PET-G','ABS','TPU','ASA'].map(function(t){return '<option'+(d.type===t?' selected':'')+'>'+t+'</option>';}).join('');
  openModal('<form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'+d.id+'">'
    + '<label>Descrição <input name="description" value="'+(d.description||'')+'"></label> '
    + '<label>Marca <input name="brand" value="'+(d.brand||'')+'"></label> '
    + '<label>Tipo <select name="type">'+opts+'</select></label> '
    + '<label>Preço/kg (R$) <input type="number" step="0.01" name="price_per_kg" value="'+(d.price||'')+'"></label> '
    + '<button type="submit">Salvar</button></form>');
}
</script></body></html>
JS;