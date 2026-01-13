<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/cost.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'budgets';
$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $pdo->prepare('UPDATE budgets SET approved=? WHERE id=?')->execute([(int)($_POST['approved'] ?? 0),(int)($_POST['id'] ?? 0)]);
    } elseif ($action === 'create') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $pdo->prepare('INSERT INTO budgets (client_id) VALUES (?)')->execute([$clientId]);
            $id = (int)$pdo->lastInsertId();
            header('Location: /public/budget_items.php?budget_id=' . $id);
            exit;
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        $approved = (int)($_POST['approved'] ?? 0);
        if ($id > 0 && $clientId > 0) { $pdo->prepare('UPDATE budgets SET client_id=?, approved=? WHERE id=?')->execute([$clientId,$approved,$id]); }
    } elseif ($action === 'recalc') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $items = $pdo->prepare('SELECT id, printer_id, print_time_seconds, quantity, addition, discount FROM budget_items WHERE budget_id=?');
            $items->execute([$id]);
            foreach ($items->fetchAll() as $it) {
                $pr = $pdo->prepare('SELECT * FROM printers WHERE id=?');
                $pr->execute([(int)$it['printer_id']]);
                $printerRow = $pr->fetch();
                if (!$printerRow) { continue; }
                $fstmt = $pdo->prepare('SELECT f.id, f.price_per_kg, bif.grams FROM budget_item_filaments bif JOIN filaments f ON f.id=bif.filament_id WHERE bif.budget_item_id=?');
                $fstmt->execute([(int)$it['id']]);
                $filaments = [];
                foreach ($fstmt->fetchAll() as $fr) { $filaments[] = ['id'=>(int)$fr['id'],'grams'=>(float)$fr['grams'],'price_per_kg'=>(float)$fr['price_per_kg']]; }
                $sstmt = $pdo->prepare('SELECT SUM(s.price) AS s FROM budget_item_services bis JOIN services s ON s.id=bis.service_id WHERE bis.budget_item_id=?');
                $sstmt->execute([(int)$it['id']]);
                $servicesCost = (float)($sstmt->fetch()['s'] ?? 0);
                $calc = calculateItemCost($params ?: ['energy_cost_kwh'=>0,'profit_margin_percent'=>0,'hourly_additional'=>0], $printerRow, $filaments, (int)$it['print_time_seconds'], $servicesCost, (int)$it['quantity']);
                $total = max(0.0, (float)$calc['total'] + (float)($it['addition'] ?? 0) - (float)($it['discount'] ?? 0));
                $pdo->prepare('UPDATE budget_items SET energy_cost=?, filament_cost=?, hourly_additional_cost=?, services_cost=?, item_total=? WHERE id=?')
                    ->execute([$calc['energy_cost'],$calc['filament_cost'],$calc['hourly_additional_cost'],$servicesCost,$total,(int)$it['id']]);
            }
            $sum = $pdo->prepare('SELECT SUM(item_total) AS s FROM budget_items WHERE budget_id=?');
            $sum->execute([$id]);
            $total = (float)($sum->fetch()['s'] ?? 0);
            $pdo->prepare('UPDATE budgets SET total=? WHERE id=?')->execute([$total,$id]);
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try { $pdo->prepare('DELETE FROM accounts_receivable WHERE order_id IN (SELECT id FROM orders WHERE budget_id=?)')->execute([$id]); } catch (Throwable $e) {}
            try { $pdo->prepare('DELETE FROM orders WHERE budget_id=?')->execute([$id]); } catch (Throwable $e) {}
            try { $pdo->prepare('DELETE FROM budget_item_filaments WHERE budget_item_id IN (SELECT id FROM budget_items WHERE budget_id=?)')->execute([$id]); } catch (Throwable $e) {}
            try { $pdo->prepare('DELETE FROM budget_item_services WHERE budget_item_id IN (SELECT id FROM budget_items WHERE budget_id=?)')->execute([$id]); } catch (Throwable $e) {}
            try { $pdo->prepare('DELETE FROM budget_items WHERE budget_id=?')->execute([$id]); } catch (Throwable $e) {}
            $pdo->prepare('DELETE FROM budgets WHERE id=?')->execute([$id]);
        }
    }
    header('Location: /public/budgets.php');
    exit;
}
$rows = $pdo->query('SELECT b.*, c.name AS client_name FROM budgets b JOIN clients c ON c.id=b.client_id ORDER BY b.id DESC')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Orçamentos</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Orçamentos</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<div class="toolbar"><button class="button" onclick="openCreateBudget()">Novo Orçamento</button><span class="spacer"></span><button id="print-btn" class="icon-button" title="Imprimir"><svg class="icon"><use href="/public/assets/icons.svg#print"></use></svg></button></div>';
echo '<template id="client_options">';
foreach ($clients as $c) { echo '<option value="' . (int)$c['id'] . '">' . htmlspecialchars($c['name']) . '</option>'; }
echo '</template>';
echo '<table><tr><th>Data</th><th>Cliente</th><th>Total (R$)</th><th>Aprovado</th><th>Ações</th></tr>';
foreach ($rows as $r) {
    $icon = $r['approved'] ? '🟢' : '🔴';
    echo '<tr>';
    echo '<td>' . htmlspecialchars(fmt_date($r['date'])) . '</td>';
    echo '<td>' . htmlspecialchars($r['client_name']) . '</td>';
    echo '<td>' . number_format((float)$r['total'],2,',','.') . '</td>';
    echo '<td style="text-align:center">' . ($r['approved']? '<span class="badge approved">Aprovado</span>' : '<span class="badge rejected">Pendente</span>') . '</td>';
    echo '<td class="actions">';
    echo '<button class="icon-button" title="Editar" onclick="openEditBudget(this)" data-id="' . (int)$r['id'] . '" data-client_id="' . (int)$r['client_id'] . '" data-approved="' . ((int)$r['approved']) . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
    echo '<a class="icon-button" title="Ver" href="/public/budget_items.php?budget_id=' . $r['id'] . '"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></a>';
    echo '<a class="icon-button" title="Imprimir" href="#" onclick="openPrintPopup(' . $r['id'] . ');return false;"><svg class="icon"><use href="/public/assets/icons.svg#print"></use></svg></a>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'Recalcular todos os itens deste orçamento?\')">';
    echo '<input type="hidden" name="action" value="recalc">';
    echo '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    echo '<button type="submit" class="icon-button" title="Recalcular"><svg class="icon"><use href="/public/assets/icons.svg#toolbox"></use></svg></button>';
    echo '</form>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'Excluir este orçamento e todos seus itens?\')">';
    echo '<input type="hidden" name="action" value="delete">';
    echo '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    echo '<button type="submit" class="icon-button danger" title="Excluir Orçamento"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button>';
    echo '</form>';
    echo '<form method="post" class="inline">';
    echo '<input type="hidden" name="action" value="approve">';
    echo '<input type="hidden" name="id" value="' . $r['id'] . '">';
    echo '<input type="hidden" name="approved" value="' . ($r['approved']?0:1) . '">';
    echo '<button type="submit" class="icon-button ' . ($r['approved']? 'danger' : 'success') . '" title="' . ($r['approved']? 'Desaprovar' : 'Aprovar') . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
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
function openPrintPopup(id){window.open('/public/budget_print.php?budget_id='+id,'printwin','width=900,height=700,menubar=no,toolbar=no,location=no,status=no');}
document.getElementById('print-btn').addEventListener('click',function(){window.print();});
function openCreateBudget(){
  var opts=document.getElementById('client_options').innerHTML;
  openModal('<form method="post"><input type="hidden" name="action" value="create">'
    + '<label>Cliente <select name="client_id">'+opts+'</select></label> '
    + '<button type="submit">Criar</button></form>');
}
function openEditBudget(btn){
  var d=btn.dataset;
  var opts=document.getElementById('client_options').innerHTML.replace('value="'+d.client_id+'"','value="'+d.client_id+'" selected');
  var approvedOpts='<option value="0"'+(d.approved==='0'?' selected':'')+'>Pendente</option><option value="1"'+(d.approved==='1'?' selected':'')+'>Aprovado</option>';
  openModal('<form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'+d.id+'">'
    + '<label>Cliente <select name="client_id">'+opts+'</select></label> '
    + '<label>Status <select name="approved">'+approvedOpts+'</select></label> '
    + '<button type="submit">Salvar</button></form>');
}
</script>
JS;