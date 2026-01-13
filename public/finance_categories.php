<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'finance';
$pdo->exec("CREATE TABLE IF NOT EXISTS finance_categories (id INT PRIMARY KEY AUTO_INCREMENT, type ENUM('PAYABLE','RECEIVABLE') NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(16) DEFAULT NULL, UNIQUE KEY uniq_type_name (type,name))");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add_category') {
    $type = $_POST['type'] ?? 'PAYABLE';
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '');
    if ($name !== '') { $pdo->prepare('INSERT INTO finance_categories (type,name,color) VALUES (?,?,?)')->execute([$type,$name,$color!==''?$color:null]); }
    header('Location: /public/finance_categories.php');
    exit;
  } elseif ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) { $pdo->prepare('DELETE FROM finance_categories WHERE id=?')->execute([$id]); }
    header('Location: /public/finance_categories.php');
    exit;
  } elseif ($action === 'update_category') {
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? 'PAYABLE';
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '');
    if ($id>0 && $name!=='') { $pdo->prepare('UPDATE finance_categories SET type=?, name=?, color=? WHERE id=?')->execute([$type,$name,$color!==''?$color:null,$id]); }
    header('Location: /public/finance_categories.php');
    exit;
  }
}
$cats = []; try { $cats = $pdo->query("SELECT * FROM finance_categories ORDER BY type, name")->fetchAll(); } catch (Throwable $e) { $cats = []; }
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Categorias Financeiras</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Categorias</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<a href="/public/finance_categories.php" class="active"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Categorias</a>';
echo '<a href="/public/reports.php" class="' . ($active==='reports'?'active':'') . '"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Relatórios</a>';
echo '</aside>';
echo '<main class="content">';
echo '<div class="card"><h3>Adicionar Categoria</h3><div class="toolbar"><button class="button" onclick="openCreateCategory()">Nova Categoria</button></div></div>';
echo '<div class="card"><h3>Lista de Categorias</h3>';
echo '<table><tr><th>Tipo</th><th>Nome</th><th>Cor</th><th>Ações</th></tr>';
foreach ($cats as $cat) {
  echo '<tr>';
  echo '<td>' . ($cat['type']==='PAYABLE'?'Pagar':'Receber') . '</td>';
  echo '<td>' . htmlspecialchars($cat['name']) . '</td>';
  echo '<td>' . htmlspecialchars($cat['color'] ?? '') . '</td>';
  echo '<td class="actions"><div class="action-box">';
  echo '<button type="button" class="icon-button" title="Editar" data-open="cat-' . (int)$cat['id'] . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
  echo '<form method="post" class="inline" onsubmit="return confirm(\'Excluir categoria?\')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="' . (int)$cat['id'] . '"><button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button></form>';
  echo '</div></td>';
  echo '</tr>';
  echo '<tr><td colspan="4">';
  echo '<div class="modal" id="cat-' . (int)$cat['id'] . '"><div class="modal-content"><h4>Editar Categoria</h4>';
  echo '<form method="post" class="inline"><input type="hidden" name="action" value="update_category"><input type="hidden" name="id" value="' . (int)$cat['id'] . '">';
  echo '<label>Tipo <select name="type"><option value="PAYABLE"' . ($cat['type']==='PAYABLE'?' selected':'') . '>Pagar</option><option value="RECEIVABLE"' . ($cat['type']==='RECEIVABLE'?' selected':'') . '>Receber</option></select></label><br>';
  echo '<label>Nome <input name="name" value="' . htmlspecialchars($cat['name']) . '" required></label><br>';
  echo '<label>Cor <input name="color" value="' . htmlspecialchars($cat['color'] ?? '') . '"></label><br>';
  echo '<div class="modal-actions"><button type="submit" class="button">Salvar</button><button type="button" class="icon-button" data-close="cat-' . (int)$cat['id'] . '" title="Fechar"><svg class="icon"><use href="/public/assets/icons.svg#close"></use></svg></button></div>';
  echo '</form></div></div>';
  echo '</td></tr>';
}
echo '</table>';
echo '</div>';
echo '</main></div><div id="modal" class="modal"><div class="modal-content"><div id="modal-body"></div><div class="modal-actions"><button class="button" onclick="closeModal()">Fechar</button></div></div></div><script src="/public/assets/js/theme.js"></script>';
echo <<<'JS'
<script>
document.addEventListener("DOMContentLoaded",function(){
  document.querySelectorAll("[data-open]").forEach(function(btn){
    btn.addEventListener("click",function(){
      var id=btn.getAttribute("data-open");
      var m=document.getElementById(id);
      if(m){m.classList.add("open");}
    });
  });
  document.querySelectorAll("[data-close]").forEach(function(btn){
    btn.addEventListener("click",function(){
      var id=btn.getAttribute("data-close");
      var m=document.getElementById(id);
      if(m){m.classList.remove("open");}
    });
  });
});
function openModal(html){document.getElementById('modal-body').innerHTML=html;document.getElementById('modal').classList.add('open');}
function closeModal(){document.getElementById('modal').classList.remove('open');}
function openCreateCategory(){
  openModal('<form method="post" class="inline"><input type="hidden" name="action" value="add_category">'
    + ' <label>Tipo <select name="type"><option value="PAYABLE">Pagar</option><option value="RECEIVABLE">Receber</option></select></label> '
    + ' <label>Nome <input name="name" required></label> '
    + ' <label>Cor <input name="color" placeholder="#HEX opcional"></label> '
    + ' <div class="modal-actions"><button type="submit" class="button">Adicionar</button></div></form>');
}
</script>
</body></html>
JS;
?>