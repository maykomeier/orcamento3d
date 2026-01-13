<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS finance_categories (id INT PRIMARY KEY AUTO_INCREMENT, type ENUM('PAYABLE','RECEIVABLE') NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(16) DEFAULT NULL, UNIQUE KEY uniq_type_name (type,name))");
$ccol = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='accounts_payable' AND COLUMN_NAME='category_id'");
try { $ccol->execute(); if ((int)($ccol->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE accounts_payable ADD COLUMN category_id INT NULL"); } } catch (Throwable $e) {}
try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ap_cat ON accounts_payable(category_id)"); } catch (Throwable $e) {}
try { $pdo->exec("INSERT IGNORE INTO finance_categories (type,name) VALUES ('PAYABLE','Filamento'),('PAYABLE','Energia'),('PAYABLE','Outros')"); } catch (Throwable $e) {}
$chk = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='accounts_receivable' AND COLUMN_NAME='description'");
try { $chk->execute(); if ((int)($chk->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE accounts_receivable ADD COLUMN description VARCHAR(160) NOT NULL DEFAULT 'Recebível'"); } } catch (Throwable $e) {}
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'finance';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'init') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INT PRIMARY KEY AUTO_INCREMENT, budget_id INT NOT NULL, budget_item_id INT NOT NULL, production_date DATE, delivery_date DATE, due_date DATE, amount DECIMAL(12,2) NOT NULL, status ENUM('open','produced','delivered','paid') DEFAULT 'open', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE, FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS accounts_receivable (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT DEFAULT NULL, description VARCHAR(160) NOT NULL, due_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, received TINYINT(1) NOT NULL DEFAULT 0, received_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS accounts_payable (id INT PRIMARY KEY AUTO_INCREMENT, category ENUM('FILAMENTO','ENERGIA','OUTROS') NOT NULL, description VARCHAR(160) NOT NULL, due_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, paid TINYINT(1) NOT NULL DEFAULT 0, paid_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'receivable_paid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $pdo->prepare('UPDATE accounts_receivable SET received=1, received_date=CURDATE() WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'receivable_unpaid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $pdo->prepare('UPDATE accounts_receivable SET received=0, received_date=NULL WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'add_receivable') {
        $due = $_POST['due_date'] ?? NULL;
        $amt = (float)($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($due && $amt > 0) { $pdo->prepare('INSERT INTO accounts_receivable (order_id, description, due_date, amount) VALUES (NULL, ?, ?, ?)')->execute([$desc!==''?$desc:'Recebível', $due, $amt]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'add_payable') {
        $cat = $_POST['category'] ?? 'OUTROS';
        $catId = (int)($_POST['category_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $due = $_POST['due_date'] ?? NULL;
        $amt = (float)($_POST['amount'] ?? 0);
        if ($desc !== '' && $due) { $pdo->prepare('INSERT INTO accounts_payable (category, category_id, description, due_date, amount) VALUES (?,?,?,?,?)')->execute([$cat,$catId>0?$catId:null,$desc,$due,$amt]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'payable_paid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $pdo->prepare('UPDATE accounts_payable SET paid=1, paid_date=CURDATE() WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'update_receivable') {
        $id = (int)($_POST['id'] ?? 0);
        $due = $_POST['due_date'] ?? NULL;
        $amt = (float)($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($id>0 && $due) { $pdo->prepare('UPDATE accounts_receivable SET description=?, due_date=?, amount=? WHERE id=?')->execute([$desc,$due,$amt,$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'delete_receivable') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) { $pdo->prepare('DELETE FROM accounts_receivable WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'update_payable') {
        $id = (int)($_POST['id'] ?? 0);
        $cat = $_POST['category'] ?? 'OUTROS';
        $catId = (int)($_POST['category_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $due = $_POST['due_date'] ?? NULL;
        $amt = (float)($_POST['amount'] ?? 0);
        if ($id>0 && $due && $desc!=='') { $pdo->prepare('UPDATE accounts_payable SET category=?, category_id=?, description=?, due_date=?, amount=? WHERE id=?')->execute([$cat,$catId>0?$catId:null,$desc,$due,$amt,$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'delete_payable') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) { $pdo->prepare('DELETE FROM accounts_payable WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'add_category') {
        $type = $_POST['type'] ?? 'PAYABLE';
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') { $pdo->prepare('INSERT INTO finance_categories (type,name) VALUES (?,?)')->execute([$type,$name]); }
        header('Location: /public/finance.php');
        exit;
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) { $pdo->prepare('DELETE FROM finance_categories WHERE id=?')->execute([$id]); }
        header('Location: /public/finance.php');
        exit;
    }
}
$receivables = [];
try {
    $receivables = $pdo->query('SELECT ar.*, o.budget_id, o.budget_item_id, b.client_id, c.name AS client_name FROM accounts_receivable ar LEFT JOIN orders o ON o.id=ar.order_id LEFT JOIN budgets b ON b.id=o.budget_id LEFT JOIN clients c ON c.id=b.client_id ORDER BY ar.due_date')->fetchAll();
} catch (Throwable $e) { $receivables = []; }
$payables = [];
try { $payables = $pdo->query('SELECT ap.*, fc.name AS category_name FROM accounts_payable ap LEFT JOIN finance_categories fc ON fc.id=ap.category_id ORDER BY ap.due_date')->fetchAll(); } catch (Throwable $e) { $payables = []; }
$cats = []; try { $cats = $pdo->query("SELECT * FROM finance_categories ORDER BY type, name")->fetchAll(); } catch (Throwable $e) { $cats = []; }
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Financeiro</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Financeiro</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<div class="widgets-grid">';
echo '<div class="widget w-money"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#money"></use></svg><h4>Contas a Receber</h4></div><div class="value">R$ ' . number_format((float)($pdo->query('SELECT COALESCE(SUM(amount),0) AS s FROM accounts_receivable WHERE received=0')->fetch()['s'] ?? 0),2,',','.') . '</div></div>';
echo '<div class="widget w-pending"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#toolbox"></use></svg><h4>Contas a Pagar</h4></div><div class="value">R$ ' . number_format((float)($pdo->query('SELECT COALESCE(SUM(amount),0) AS s FROM accounts_payable WHERE paid=0')->fetch()['s'] ?? 0),2,',','.') . '</div></div>';
echo '</div>';
echo '<div class="card"><h3>Receber</h3><div class="toolbar"><button class="button" data-open="rec-add">Novo Recebível</button></div>';
echo '<div class="modal" id="rec-add"><div class="modal-content">';
echo '<h4>Novo Recebível</h4>';
echo '<form method="post" class="inline">';
echo '<input type="hidden" name="action" value="add_receivable">';
echo '<label>Descrição <input name="description" placeholder="Ex.: Pedido #123"></label><br>';
echo '<label>Vencimento <input type="date" name="due_date" required></label><br>';
echo '<label>Valor <input type="number" step="0.01" name="amount" required></label><br>';
echo '<div class="modal-actions"><button type="submit" class="button">Adicionar</button><button type="button" class="icon-button" data-close="rec-add" title="Fechar"><svg class="icon"><use href="/public/assets/icons.svg#close"></use></svg></button></div>';
echo '</form>';
echo '</div></div>';
if (count($receivables) === 0) { echo '<p>Nenhum lançamento.</p>'; }
else {
  echo '<table><tr><th>Pedido</th><th>Cliente</th><th>Descrição</th><th>Orçamento</th><th>Item</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr>';
  foreach ($receivables as $r) {
    echo '<tr>';
    echo '<td>' . ($r['order_id'] ? ('#' . (int)$r['order_id']) : '—') . '</td>';
    echo '<td>' . ($r['client_name'] ? htmlspecialchars($r['client_name']) : 'Manual') . '</td>';
    echo '<td>' . htmlspecialchars($r['description'] ?? '') . '</td>';
    echo '<td>' . ($r['budget_id'] ? ('#' . (int)$r['budget_id']) : '—') . '</td>';
    echo '<td>' . ($r['budget_item_id'] ? ('#' . (int)$r['budget_item_id']) : '—') . '</td>';
    echo '<td>R$ ' . number_format((float)$r['amount'],2,',','.') . '</td>';
    echo '<td>' . htmlspecialchars(fmt_date($r['due_date'])) . '</td>';
    echo '<td>' . ($r['received']? '<span class="badge approved">Recebido</span>' : '<span class="badge rejected">Pendente</span>') . '</td>';
    echo '<td class="actions">';
    echo '<div class="action-box">';
    if (!$r['received']) {
      echo '<form method="post" class="inline"><input type="hidden" name="action" value="receivable_paid"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button type="submit" class="icon-button" title="Marcar como recebido"><svg class="icon"><use href="/public/assets/icons.svg#check"></use></svg></button></form>';
    } else {
      echo '<form method="post" class="inline"><input type="hidden" name="action" value="receivable_unpaid"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button type="submit" class="icon-button" title="Marcar como não recebido"><svg class="icon"><use href="/public/assets/icons.svg#clock"></use></svg></button></form>';
    }
    echo '<button type="button" class="icon-button" title="Editar" data-open="rec-' . (int)$r['id'] . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
    echo '<div class="modal" id="rec-' . (int)$r['id'] . '"><div class="modal-content">';
    echo '<h4>Editar Recebível</h4>';
    echo '<form method="post" class="inline"><input type="hidden" name="action" value="update_receivable"><input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    echo '<label>Descrição <input name="description" value="' . htmlspecialchars($r['description'] ?? '') . '"></label><br>';
    echo '<label>Vencimento <input type="date" name="due_date" value="' . htmlspecialchars($r['due_date']) . '" required></label><br>';
    echo '<label>Valor <input type="number" step="0.01" name="amount" value="' . htmlspecialchars((string)$r['amount']) . '" required></label><br>';
    echo '<div class="modal-actions"><button type="submit" class="button">Salvar</button><button type="button" class="icon-button" data-close="rec-' . (int)$r['id'] . '" title="Fechar"><svg class="icon"><use href="/public/assets/icons.svg#close"></use></svg></button></div>';
    echo '</form>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'Excluir esta conta a receber?\')"><input type="hidden" name="action" value="delete_receivable"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button></form>';
    echo '</div></div>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
  }
  echo '</table>';
}
echo '</div>';
echo '<div class="card"><h3>Pagar</h3><div class="toolbar"><button class="button" data-open="pay-add">Novo Pagável</button></div>';
echo '<div class="modal" id="pay-add"><div class="modal-content">';
echo '<h4>Novo Pagável</h4>';
echo '<form method="post" class="inline">';
echo '<input type="hidden" name="action" value="add_payable">';
echo '<label>Categoria <select name="category_id">';
foreach ($cats as $cat) { if ($cat['type']==='PAYABLE') { echo '<option value="' . (int)$cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>'; } }
echo '</select></label><br>';
echo '<input type="hidden" name="category" value="OUTROS">';
echo '<label>Descrição <input name="description" required></label><br>';
echo '<label>Vencimento <input type="date" name="due_date" required></label><br>';
echo '<label>Valor <input type="number" step="0.01" name="amount" required></label><br>';
echo '<div class="modal-actions"><button type="submit" class="button">Adicionar</button><button type="button" class="icon-button" data-close="pay-add" title="Fechar"><svg class="icon"><use href="/public/assets/icons.svg#close"></use></svg></button></div>';
echo '</form>';
echo '</div></div>';
if (count($payables) === 0) { echo '<p>Nenhum lançamento.</p>'; }
else {
  echo '<table><tr><th>Categoria</th><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr>';
  foreach ($payables as $p) {
    echo '<tr>';
    $catLabel = $p['category_name'] ? $p['category_name'] : ($p['category']==='FILAMENTO'?'Filamento':($p['category']==='ENERGIA'?'Energia':'Outros'));
    echo '<td>' . htmlspecialchars($catLabel) . '</td>';
    echo '<td>' . htmlspecialchars($p['description']) . '</td>';
    echo '<td>' . htmlspecialchars(fmt_date($p['due_date'])) . '</td>';
    echo '<td>R$ ' . number_format((float)$p['amount'],2,',','.') . '</td>';
    echo '<td>' . ($p['paid']? '<span class="badge approved">Pago</span>' : '<span class="badge rejected">Pendente</span>') . '</td>';
    echo '<td class="actions">';
    echo '<div class="action-box">';
    if (!$p['paid']) { echo '<form method="post" class="inline"><input type="hidden" name="action" value="payable_paid"><input type="hidden" name="id" value="' . (int)$p['id'] . '"><button type="submit" class="icon-button" title="Marcar como pago"><svg class="icon"><use href="/public/assets/icons.svg#check"></use></svg></button></form>'; }
    echo '<button type="button" class="icon-button" title="Editar" data-open="pay-' . (int)$p['id'] . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
    echo '<div class="modal" id="pay-' . (int)$p['id'] . '"><div class="modal-content">';
    echo '<h4>Editar Pagável</h4>';
    echo '<form method="post" class="inline"><input type="hidden" name="action" value="update_payable"><input type="hidden" name="id" value="' . (int)$p['id'] . '">';
    echo '<label>Categoria <select name="category_id">';
    foreach ($cats as $cat) { if ($cat['type']==='PAYABLE') { $sel = ((int)$p['category_id'] === (int)$cat['id'])?' selected':''; echo '<option value="' . (int)$cat['id'] . '"' . $sel . '>' . htmlspecialchars($cat['name']) . '</option>'; } }
    echo '</select></label><br>';
    echo '<input type="hidden" name="category" value="' . htmlspecialchars($p['category']) . '">';
    echo '<label>Descrição <input name="description" value="' . htmlspecialchars($p['description']) . '" required></label><br>';
    echo '<label>Vencimento <input type="date" name="due_date" value="' . htmlspecialchars($p['due_date']) . '" required></label><br>';
    echo '<label>Valor <input type="number" step="0.01" name="amount" value="' . htmlspecialchars((string)$p['amount']) . '" required></label><br>';
    echo '<div class="modal-actions"><button type="submit" class="button">Salvar</button><button type="button" class="icon-button" data-close="pay-' . (int)$p['id'] . '" title="Fechar"><svg class="icon"><use href="/public/assets/icons.svg#close"></use></svg></button></div>';
    echo '</form>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'Excluir esta conta a pagar?\')"><input type="hidden" name="action" value="delete_payable"><input type="hidden" name="id" value="' . (int)$p['id'] . '"><button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button></form>';
    echo '</div></div>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
  }
  echo '</table>';
}
echo '</div>';
echo '<div class="card"><h3>Categorias</h3><p>Acesse o gerenciamento de categorias <a href="/public/finance_categories.php">nesta página</a>.</p></div>';
echo '<div class="card"><h3>Relatórios</h3><p>Acesse seus relatórios detalhados <a href="/public/reports.php">nesta página</a>.</p></div>';
echo '</main></div><script src="/public/assets/js/theme.js"></script><script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("[data-open]").forEach(function(btn){btn.addEventListener("click",function(){var id=btn.getAttribute("data-open");var m=document.getElementById(id);if(m){m.classList.add("open");}})});document.querySelectorAll("[data-close]").forEach(function(btn){btn.addEventListener("click",function(){var id=btn.getAttribute("data-close");var m=document.getElementById(id);if(m){m.classList.remove("open");}})});});</script></body></html>';