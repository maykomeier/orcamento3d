<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'printers';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $pdo->prepare('INSERT INTO printers (name, brand, model, price, is_multimaterial, power_w) VALUES (?,?,?,?,?,?)')->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['brand'] ?? ''),
            trim($_POST['model'] ?? ''),
            (float)($_POST['price'] ?? 0),
            (int)($_POST['is_multimaterial'] ?? 0),
            (int)($_POST['power_w'] ?? 0)
        ]);
    } elseif ($action === 'update') {
        $pdo->prepare('UPDATE printers SET name=?, brand=?, model=?, price=?, is_multimaterial=?, power_w=? WHERE id=?')->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['brand'] ?? ''),
            trim($_POST['model'] ?? ''),
            (float)($_POST['price'] ?? 0),
            (int)($_POST['is_multimaterial'] ?? 0),
            (int)($_POST['power_w'] ?? 0),
            (int)($_POST['id'] ?? 0)
        ]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM printers WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    }
    header('Location: /public/printers.php');
    exit;
}
$rows = $pdo->query('SELECT * FROM printers ORDER BY id DESC')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Impressoras</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Impressoras 3D</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<div class="toolbar"><button class="button" onclick="openCreatePrinter()">Nova Impressora</button></div>';
echo '<table><tr><th>ID</th><th>Nome</th><th>Marca</th><th>Modelo</th><th>Valor</th><th>Multimaterial</th><th>Potência (W)</th><th>Ações</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . $r['id'] . '</td>';
    echo '<td>' . htmlspecialchars($r['name']) . '</td>';
    echo '<td>' . htmlspecialchars($r['brand']) . '</td>';
    echo '<td>' . htmlspecialchars($r['model']) . '</td>';
    echo '<td>' . number_format((float)$r['price'],2,',','.') . '</td>';
    echo '<td>' . ($r['is_multimaterial'] ? 'Sim' : 'Não') . '</td>';
    echo '<td>' . (int)$r['power_w'] . '</td>';
    echo '<td class="actions">';
    echo '<details><summary class="icon-button" title="Ver"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></summary><div>';
    echo '<div>ID: ' . $r['id'] . '</div><div>Nome: ' . htmlspecialchars($r['name']) . '</div><div>Marca: ' . htmlspecialchars($r['brand']) . '</div><div>Modelo: ' . htmlspecialchars($r['model']) . '</div><div>Valor: R$ ' . number_format((float)$r['price'],2,',','.') . '</div><div>Multimaterial: ' . ($r['is_multimaterial'] ? 'Sim' : 'Não') . '</div><div>Potência: ' . (int)$r['power_w'] . ' W</div>';
    echo '</div></details>';
    echo '<button class="icon-button" title="Editar" onclick="openEditPrinter(this)" data-id="' . $r['id'] . '" data-name="' . htmlspecialchars($r['name']) . '" data-brand="' . htmlspecialchars($r['brand']) . '" data-model="' . htmlspecialchars($r['model']) . '" data-price="' . htmlspecialchars((string)$r['price']) . '" data-mm="' . ($r['is_multimaterial']? '1':'0') . '" data-power="' . (int)$r['power_w'] . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
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
function openCreatePrinter(){
  openModal('<form method="post"><input type="hidden" name="action" value="create">'
    + '<label>Nome <input required name="name"></label> '
    + '<label>Marca <input name="brand"></label> '
    + '<label>Modelo <input name="model"></label> '
    + '<label>Valor (R$) <input type="number" step="0.01" name="price"></label> '
    + '<label>Multimaterial <select name="is_multimaterial"><option value="0">Não</option><option value="1">Sim</option></select></label> '
    + '<label>Potência (W) <input type="number" name="power_w"></label> '
    + '<button type="submit">Adicionar</button></form>');
}
function openEditPrinter(btn){
  var d=btn.dataset;
  var mmOpts='<option value="0"'+(d.mm==='0'?' selected':'')+'>Não</option><option value="1"'+(d.mm==='1'?' selected':'')+'>Sim</option>';
  openModal('<form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'+d.id+'">'
    + '<label>Nome <input name="name" value="'+(d.name||'')+'"></label> '
    + '<label>Marca <input name="brand" value="'+(d.brand||'')+'"></label> '
    + '<label>Modelo <input name="model" value="'+(d.model||'')+'"></label> '
    + '<label>Valor (R$) <input type="number" step="0.01" name="price" value="'+(d.price||'')+'"></label> '
    + '<label>Multimaterial <select name="is_multimaterial">'+mmOpts+'</select></label> '
    + '<label>Potência (W) <input type="number" name="power_w" value="'+(d.power||'')+'"></label> '
    + '<button type="submit">Salvar</button></form>');
}
</script></body></html>
JS;