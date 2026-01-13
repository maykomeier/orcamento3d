<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'orders';
$rows = [];
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INT PRIMARY KEY AUTO_INCREMENT, budget_id INT NOT NULL, budget_item_id INT NOT NULL, production_date DATE, delivery_date DATE, due_date DATE, amount DECIMAL(12,2) NOT NULL, status ENUM('open','produced','delivered','paid') DEFAULT 'open', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE, FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE)");
} catch (Throwable $e) {}
try {
  $q = $pdo->query('SELECT o.*, b.client_id, c.name AS client_name FROM orders o JOIN budgets b ON b.id=o.budget_id LEFT JOIN clients c ON c.id=b.client_id ORDER BY o.created_at DESC');
  $rows = $q->fetchAll();
} catch (Throwable $e) { $rows = []; }
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Pedidos</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Pedidos</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<div class="card"><h3>Todos os Pedidos</h3>';
echo '<table><tr><th>ID</th><th>Cliente</th><th>Orçamento</th><th>Item</th><th>Produção</th><th>Entrega</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Criado em</th><th>Ações</th></tr>';
foreach ($rows as $r) {
  $st = (string)($r['status'] ?? 'open');
  $badge = $st==='paid' ? '<span class="badge approved">Pago</span>' : ($st==='delivered' ? '<span class="badge approved">Entregue</span>' : ($st==='produced' ? '<span class="badge approved">Produzido</span>' : '<span class="badge">Aberto</span>'));
  echo '<tr data-id="' . (int)$r['id'] . '" data-client="' . htmlspecialchars($r['client_name'] ?? '') . '" data-budget="' . (int)$r['budget_id'] . '" data-item="' . (int)$r['budget_item_id'] . '" data-prod="' . htmlspecialchars(fmt_date($r['production_date'] ?? '')) . '" data-del="' . htmlspecialchars(fmt_date($r['delivery_date'] ?? '')) . '" data-due="' . htmlspecialchars(fmt_date($r['due_date'] ?? '')) . '" data-amount="' . number_format((float)$r['amount'],2,',','.') . '" data-status="' . htmlspecialchars($st) . '" data-created="' . htmlspecialchars(fmt_datetime($r['created_at'] ?? '')) . '">';
  echo '<td>#' . (int)$r['id'] . '</td>';
  echo '<td>' . htmlspecialchars($r['client_name'] ?? '') . '</td>';
  echo '<td>#' . (int)$r['budget_id'] . '</td>';
  echo '<td>#' . (int)$r['budget_item_id'] . '</td>';
  echo '<td>' . htmlspecialchars(fmt_date($r['production_date'] ?? '')) . '</td>';
  echo '<td>' . htmlspecialchars(fmt_date($r['delivery_date'] ?? '')) . '</td>';
  echo '<td>' . htmlspecialchars(fmt_date($r['due_date'] ?? '')) . '</td>';
  echo '<td>R$ ' . number_format((float)$r['amount'],2,',','.') . '</td>';
  echo '<td>' . $badge . '</td>';
  echo '<td>' . htmlspecialchars(fmt_datetime($r['created_at'] ?? '')) . '</td>';
  echo '<td class="actions"><button class="icon-button" title="Detalhes" onclick="openOrder(' . (int)$r['id'] . ')"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></button></td>';
  echo '</tr>';
}
echo '</table></div>';
echo '<div id="order-modal" class="modal"><div class="modal-content"><button id="order-modal-close" class="button">Fechar</button><div id="order-modal-body"></div></div></div>';
echo '</main></div><script src="/public/assets/js/theme.js"></script>';
echo <<<'HTML'
<script>
function openOrder(id){
  var tr=null;
  document.querySelectorAll("table tr[data-id]").forEach(function(row){ if(parseInt(row.dataset.id)==id){ tr=row; } });
  if(!tr) return;
  var b=document.getElementById("order-modal-body");
  b.innerHTML="<h3>Pedido #" + tr.dataset.id + "</h3>"
    + "<p><strong>Cliente:</strong> " + (tr.dataset.client||"") + "</p>"
    + "<p><strong>Orçamento:</strong> #" + tr.dataset.budget + " <strong>Item:</strong> #" + tr.dataset.item + "</p>"
    + "<p><strong>Produção:</strong> " + (tr.dataset.prod||"-") + " <strong>Entrega:</strong> " + (tr.dataset.del||"-") + "</p>"
    + "<p><strong>Vencimento:</strong> " + (tr.dataset.due||"-") + "</p>"
    + "<p><strong>Valor:</strong> R$ " + tr.dataset.amount + "</p>"
    + "<p><strong>Status:</strong> " + tr.dataset.status + "</p>"
    + "<p><strong>Criado em:</strong> " + tr.dataset.created + "</p>";
  document.getElementById("order-modal").classList.add("open");
}
document.getElementById("order-modal-close").addEventListener("click",function(){document.getElementById("order-modal").classList.remove("open");});
document.getElementById("order-modal").addEventListener("click",function(e){ if(e.target.id==="order-modal"){ this.classList.remove("open"); }});
</script></body></html>
HTML;
?>