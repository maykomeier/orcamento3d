<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/cost.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$dcol = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='budget_items' AND COLUMN_NAME='discount'");
try { $dcol->execute(); if ((int)($dcol->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE budget_items ADD COLUMN discount DECIMAL(12,2) NOT NULL DEFAULT 0"); } } catch (Throwable $e) { }
$acol = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='budget_items' AND COLUMN_NAME='addition'");
try { $acol->execute(); if ((int)($acol->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE budget_items ADD COLUMN addition DECIMAL(12,2) NOT NULL DEFAULT 0"); } } catch (Throwable $e) { }
$active = 'budgets';
$budgetId = (int)($_GET['budget_id'] ?? 0);
if ($budgetId <= 0) { http_response_code(400); echo 'Sem orçamento.'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_item') {
        $printerId = (int)($_POST['printer_id'] ?? 0);
        $gfile = trim($_POST['gcode_file'] ?? '');
        $seconds = (int)($_POST['print_time_seconds'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $serviceIds = $_POST['service_ids'] ?? [];
        $servicesCost = 0.0;
        $selectedServices = [];
        if (is_array($serviceIds) && count($serviceIds) > 0) {
            $in = implode(',', array_fill(0, count($serviceIds), '?'));
            $stmt = $pdo->prepare('SELECT id, description, price FROM services WHERE id IN (' . $in . ')');
            $stmt->execute(array_map('intval', $serviceIds));
            $svcs = $stmt->fetchAll();
            foreach ($svcs as $sv) { $servicesCost += (float)$sv['price']; $selectedServices[] = (int)$sv['id']; }
        }
        $quantity = (int)($_POST['quantity'] ?? 1);
        $params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch() ?: ['energy_cost_kwh'=>0,'profit_margin_percent'=>0,'hourly_additional'=>0];
        $printer = $pdo->prepare('SELECT * FROM printers WHERE id=?');
        $printer->execute([$printerId]);
        $printerRow = $printer->fetch();
        $filamentIds = $_POST['filament_id'] ?? [];
        $filamentGrams = $_POST['filament_grams'] ?? [];
        $filaments = [];
        for ($i=0;$i<count($filamentIds);$i++) {
            $fid = (int)$filamentIds[$i];
            $grams = (float)$filamentGrams[$i];
            $f = $pdo->prepare('SELECT id, price_per_kg FROM filaments WHERE id=?');
            $f->execute([$fid]);
            $fr = $f->fetch();
            if ($fr) { $filaments[] = ['id'=>$fid,'grams'=>$grams,'price_per_kg'=>(float)$fr['price_per_kg']]; }
        }
        $calc = calculateItemCost($params, $printerRow, $filaments, $seconds, $servicesCost, $quantity);
        $thumb = trim($_POST['thumbnail_base64'] ?? '');
        $pdo->prepare('INSERT INTO budget_items (budget_id, printer_id, gcode_file, print_time_seconds, energy_cost, filament_cost, hourly_additional_cost, services_cost, quantity, item_total, note, thumbnail_base64) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$budgetId,$printerId,$gfile,$seconds,$calc['energy_cost'],$calc['filament_cost'],$calc['hourly_additional_cost'],$servicesCost,$calc['quantity'],$calc['total'],$note,$thumb]);
        $itemId = (int)$pdo->lastInsertId();
        if (count($selectedServices) > 0) {
            $is = $pdo->prepare('INSERT INTO budget_item_services (budget_item_id, service_id) VALUES (?,?)');
            foreach ($selectedServices as $sid) { $is->execute([$itemId,$sid]); }
        }
        for ($i=0;$i<count($filaments);$i++) {
            $pdo->prepare('INSERT INTO budget_item_filaments (budget_item_id, filament_id, grams) VALUES (?,?,?)')->execute([$itemId,$filaments[$i]['id'],$filaments[$i]['grams']]);
        }
        $sum = $pdo->prepare('SELECT SUM(item_total) AS s FROM budget_items WHERE budget_id=?');
        $sum->execute([$budgetId]);
        $total = (float)($sum->fetch()['s'] ?? 0);
        $pdo->prepare('UPDATE budgets SET total=? WHERE id=?')->execute([$total,$budgetId]);
        header('Location: /public/budget_items.php?budget_id=' . $budgetId);
        exit;
    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare('DELETE FROM budget_items WHERE id=?')->execute([$itemId]);
        $sum = $pdo->prepare('SELECT SUM(item_total) AS s FROM budget_items WHERE budget_id=?');
        $sum->execute([$budgetId]);
        $total = (float)($sum->fetch()['s'] ?? 0);
        $pdo->prepare('UPDATE budgets SET total=? WHERE id=?')->execute([$total,$budgetId]);
        header('Location: /public/budget_items.php?budget_id=' . $budgetId);
        exit;
    } elseif ($action === 'create_order') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $prod = $_POST['production_date'] ?? NULL;
        $del = $_POST['delivery_date'] ?? NULL;
        $due = $_POST['due_date'] ?? NULL;
        $it = $pdo->prepare('SELECT item_total FROM budget_items WHERE id=? AND budget_id=?');
        $it->execute([$itemId,$budgetId]);
        $row = $it->fetch();
        if ($row && $due) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INT PRIMARY KEY AUTO_INCREMENT, budget_id INT NOT NULL, budget_item_id INT NOT NULL, production_date DATE, delivery_date DATE, due_date DATE, amount DECIMAL(12,2) NOT NULL, status ENUM('open','produced','delivered','paid') DEFAULT 'open', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE, FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS accounts_receivable (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT DEFAULT NULL, description VARCHAR(160) NOT NULL, due_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, received TINYINT(1) NOT NULL DEFAULT 0, received_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE)");
            $pdo->prepare('INSERT INTO orders (budget_id, budget_item_id, production_date, delivery_date, due_date, amount) VALUES (?,?,?,?,?,?)')->execute([$budgetId,$itemId,$prod,$del,$due,(float)$row['item_total']]);
            $orderId = (int)$pdo->lastInsertId();
            $desc = 'Pedido Orçamento #' . $budgetId . ' Item #' . $itemId;
            $pdo->prepare('INSERT INTO accounts_receivable (order_id, description, due_date, amount) VALUES (?,?,?,?)')->execute([$orderId,$desc,$due,(float)$row['item_total']]);
        }
        header('Location: /public/budget_items.php?budget_id=' . $budgetId);
        exit;
    } elseif ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $note = trim($_POST['note'] ?? '');
        $discount = (float)str_replace(',', '.', (string)($_POST['discount'] ?? '0'));
        $addition = (float)str_replace(',', '.', (string)($_POST['addition'] ?? '0'));
        if ($itemId > 0 && $quantity > 0) {
            $cur = $pdo->prepare('SELECT energy_cost, filament_cost, hourly_additional_cost, services_cost FROM budget_items WHERE id=? AND budget_id=?');
            $cur->execute([$itemId,$budgetId]);
            $cr = $cur->fetch();
            if ($cr) {
                $paramsRow = $pdo->query('SELECT profit_margin_percent FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
                $margin = (float)($paramsRow['profit_margin_percent'] ?? 0);
                $subtotal = (float)$cr['energy_cost'] + (float)$cr['filament_cost'] + (float)$cr['hourly_additional_cost'] + (float)$cr['services_cost'];
                $totalUnit = $subtotal * (1.0 + ($margin / 100.0));
                $total = max(0.0, $totalUnit * $quantity + $addition - $discount);
                $pdo->prepare('UPDATE budget_items SET quantity=?, addition=?, discount=?, item_total=?, note=? WHERE id=?')
                    ->execute([$quantity,$addition,$discount,$total,$note,$itemId]);
                $sum = $pdo->prepare('SELECT SUM(item_total) AS s FROM budget_items WHERE budget_id=?');
                $sum->execute([$budgetId]);
                $t = (float)($sum->fetch()['s'] ?? 0);
                $pdo->prepare('UPDATE budgets SET total=? WHERE id=?')->execute([$t,$budgetId]);
            }
        }
        header('Location: /public/budget_items.php?budget_id=' . $budgetId);
        exit;
    }
}
$budget = $pdo->prepare('SELECT b.*, c.name AS client_name FROM budgets b JOIN clients c ON c.id=b.client_id WHERE b.id=?');
$budget->execute([$budgetId]);
$b = $budget->fetch();
$items = $pdo->prepare('SELECT * FROM budget_items WHERE budget_id=? ORDER BY id DESC');
$items->execute([$budgetId]);
$itemsRows = $items->fetchAll();
$printers = $pdo->query('SELECT id,name FROM printers ORDER BY name')->fetchAll();
$filamentsAll = $pdo->query('SELECT id, description, price_per_kg FROM filaments ORDER BY description')->fetchAll();
$servicesAll = $pdo->query('SELECT id, description, price FROM services ORDER BY description')->fetchAll();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Itens do Orçamento</title><link rel="stylesheet" href="/public/assets/style.css"><link rel="stylesheet" href="/public/assets/print.css" media="print"><script>
async function uploadGcode(){
  const f=document.getElementById("gcode").files[0];
  const btn=document.getElementById("add_btn");
  const prog=document.getElementById("upload_progress");
  if(!f) return;
  if(btn) btn.disabled=true; if(prog){prog.value=0; prog.style.display="block";}
  const fd=new FormData(); fd.append("gcode",f);
  const xhr=new XMLHttpRequest();
  xhr.open("POST","/public/upload_item.php");
  xhr.responseType="json";
  xhr.upload.onprogress=function(e){ if(e.lengthComputable && prog){ prog.value=Math.round(e.loaded*100/e.total); } };
  xhr.onerror=function(){ alert("Falha no upload do G-code"); if(btn) btn.disabled=false; };
  xhr.onload=function(){
    if(prog){ prog.value=100; }
    if(xhr.status===200){
      const j=xhr.response || {};
      document.getElementById("gcode_file").value=j.file||"";
      document.getElementById("print_time_seconds").value=j.print_time_seconds||0;
      document.getElementById("thumbnail_base64").value=j.thumbnail_base64||"";
      const grams=j.filament_grams||[];
      const container=document.getElementById("filaments_container");
      container.innerHTML="";
      for(let i=0;i<Math.max(grams.length,1);i++){
        const row=document.createElement("div");
        row.innerHTML=`<select name="filament_id[]">${document.getElementById("filament_options").innerHTML}</select> <input type="number" step="0.001" name="filament_grams[]" value="${grams[i]||0}"> g`;
        container.appendChild(row);
      }
    } else {
      var msg = (xhr.response && xhr.response.error) ? xhr.response.error : ("Erro ao processar G-code (status " + xhr.status + ")");
      alert(msg);
    }
    if(btn) btn.disabled=false;
  };
  xhr.send(fd);
}
</script></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Itens do Orçamento</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/budgets.php">Orçamentos</a></header>';
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
echo '<p>Cliente: ' . htmlspecialchars($b['client_name']) . ' | Orçamento #' . $b['id'] . ' | Total: R$ ' . number_format((float)$b['total'],2,',','.') . '</p>';
echo '<div class="toolbar"><button class="button" onclick="openCreateItem()">Novo Item</button></div>';
echo '<template id="printer_options">';
foreach ($printers as $p) { echo '<option value="' . (int)$p['id'] . '">' . htmlspecialchars($p['name']) . '</option>'; }
echo '</template>';
echo '<template id="filament_options">';
foreach ($filamentsAll as $f) { echo '<option value="' . (int)$f['id'] . '">' . htmlspecialchars($f['description']) . ' (R$ ' . number_format((float)$f['price_per_kg'],2,',','.') . '/kg)</option>'; }
echo '</template>';
echo '<template id="service_options">';
foreach ($servicesAll as $s) { echo '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['description']) . ' (R$ ' . number_format((float)$s['price'],2,',','.') . ')</option>'; }
echo '</template>';
echo '<h3>Itens</h3>';
echo '<table><tr><th>ID</th><th>Thumb</th><th>Arquivo</th><th>Tempo (s)</th><th>Qtd</th><th>Energia</th><th>Filamento</th><th>Adicional</th><th>Serviços</th><th>Itens de Serviço</th><th>Total</th><th>Ações</th></tr>';
foreach ($itemsRows as $r) {
    echo '<tr>';
    echo '<td>' . $r['id'] . '</td>';
    echo '<td>' . ($r['thumbnail_base64']? '<img src="data:image/png;base64,' . htmlspecialchars($r['thumbnail_base64']) . '" width="120">' : '') . '</td>';
    echo '<td>' . htmlspecialchars($r['gcode_file']) . '</td>';
    echo '<td>' . (int)$r['print_time_seconds'] . '</td>';
    echo '<td>' . (int)$r['quantity'] . '</td>';
    echo '<td>R$ ' . number_format((float)$r['energy_cost'],2,',','.') . '</td>';
    echo '<td>R$ ' . number_format((float)$r['filament_cost'],2,',','.') . '</td>';
    echo '<td>R$ ' . number_format((float)$r['hourly_additional_cost'],2,',','.') . '</td>';
    echo '<td>R$ ' . number_format((float)$r['services_cost'],2,',','.') . '</td>';
    $svcNames = [];
    $q = $pdo->prepare('SELECT s.description FROM budget_item_services bis JOIN services s ON s.id=bis.service_id WHERE bis.budget_item_id=? ORDER BY s.description');
    $q->execute([$r['id']]);
    foreach ($q->fetchAll() as $sr) { $svcNames[] = $sr['description']; }
    echo '<td>' . htmlspecialchars(implode(', ', $svcNames)) . '</td>';
    echo '<td>R$ ' . number_format((float)$r['item_total'],2,',','.') . '</td>';
    echo '<td class="actions">';
    echo '<details><summary class="icon-button" title="Ver"><svg class="icon"><use href="/public/assets/icons.svg#eye"></use></svg></summary><div>';
    echo '<div>Energia: R$ ' . number_format((float)$r['energy_cost'],2,',','.') . '</div><div>Filamento: R$ ' . number_format((float)$r['filament_cost'],2,',','.') . '</div><div>Adicional: R$ ' . number_format((float)$r['hourly_additional_cost'],2,',','.') . '</div><div>Serviços: R$ ' . number_format((float)$r['services_cost'],2,',','.') . '</div>';
    if (!empty($r['note'])) { echo '<div>Observações: ' . htmlspecialchars($r['note']) . '</div>'; }
    echo '</div></details>';
    echo '<button class="icon-button" title="Custos" onclick="openCostModal(this)" data-energy="' . (float)$r['energy_cost'] . '" data-filament="' . (float)$r['filament_cost'] . '" data-additional="' . (float)$r['hourly_additional_cost'] . '" data-services="' . (float)$r['services_cost'] . '" data-quantity="' . (int)$r['quantity'] . '" data-addition="' . (float)($r['addition'] ?? 0) . '" data-discount="' . (float)($r['discount'] ?? 0) . '"><svg class="icon"><use href="/public/assets/icons.svg#money"></use></svg></button>';
    $order = $pdo->prepare('SELECT id, production_date, delivery_date, due_date FROM orders WHERE budget_item_id=?');
    try { $order->execute([$r['id']]); } catch (Throwable $e) { }
    $ord = $order->fetch();
    if ($ord) {
        echo '<span class="badge approved">Pedido #' . (int)$ord['id'] . '</span> ';
    } else {
        echo '<button class="icon-button" title="Gerar Pedido" onclick="openOrderModal(' . (int)$r['id'] . ')"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg></button>';
    }
    echo '<button class="icon-button" title="Editar" onclick="openEditItem(this)" data-id="' . (int)$r['id'] . '" data-quantity="' . (int)$r['quantity'] . '" data-note="' . htmlspecialchars($r['note'] ?? '') . '" data-addition="' . (float)($r['addition'] ?? 0) . '" data-discount="' . (float)($r['discount'] ?? 0) . '"><svg class="icon"><use href="/public/assets/icons.svg#edit"></use></svg></button>';
    echo '<form method="post" action="/public/budget_items.php?budget_id=' . $budgetId . '" class="inline" onsubmit="return confirm(\'Excluir item?\')">';
    echo '<input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="' . $r['id'] . '">';
    echo '<button type="submit" class="icon-button danger" title="Excluir"><svg class="icon"><use href="/public/assets/icons.svg#trash"></use></svg></button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>';
echo '<p><a href="/public/budgets.php">Voltar</a></p>';
echo '</main></div><div id="modal" class="modal"><div class="modal-content"><div id="modal-body"></div><div class="modal-actions"><button class="button" onclick="closeModal()">Fechar</button></div></div></div><script src="/public/assets/js/theme.js"></script>';
echo <<<'JS'
<script>
function openModal(html){document.getElementById('modal-body').innerHTML=html;document.getElementById('modal').classList.add('open');}
function closeModal(){document.getElementById('modal').classList.remove('open');}
function fmt(v){return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
function openCostModal(btn){var d=btn.dataset;var energy=Number(d.energy||0),filament=Number(d.filament||0),additional=Number(d.additional||0),services=Number(d.services||0);var qty=Number(d.quantity||1),addition=Number(d.addition||0),discount=Number(d.discount||0);var unit=energy+filament+additional+services;var total=Math.max(0,unit*qty+addition-discount);openModal('<div>'+'<div>Energia: R$ '+fmt(energy)+'</div>'+'<div>Filamento: R$ '+fmt(filament)+'</div>'+'<div>Adicional: R$ '+fmt(additional)+'</div>'+'<div>Serviços: R$ '+fmt(services)+'</div>'+'<hr>'+'<div>Subtotal por unidade: R$ '+fmt(unit)+'</div>'+'<div>Quantidade: '+qty+'</div>'+'<div>Acréscimo: R$ '+fmt(addition)+'</div>'+'<div>Desconto: R$ '+fmt(discount)+'</div>'+'<div><strong>Total: R$ '+fmt(total)+'</strong></div>'+'</div>');}
function openCreateItem(){
  var pOpts=document.getElementById('printer_options').innerHTML;
  var sOpts=document.getElementById('service_options').innerHTML;
  openModal('<form method="post"><input type="hidden" name="action" value="add_item">'
    + '<input type="hidden" id="gcode_file" name="gcode_file">'
    + '<label>Tempo de impressão (s) <input type="number" id="print_time_seconds" name="print_time_seconds" value="0"></label><br>'
    + '<input type="hidden" id="thumbnail_base64" name="thumbnail_base64" value="">'
    + '<label>G-code <input type="file" id="gcode" accept=".gcode,.gco,.gcode.gz" onchange="uploadGcode()"></label><br>'
    + '<label><progress id="upload_progress" max="100" value="0" style="width:100%;display:none"></progress></label>'
    + '<label>Impressora <select name="printer_id">'+pOpts+'</select></label><br>'
    + '<div id="filaments_container"></div>'
    + '<label>Serviços <select name="service_ids[]" multiple size="4">'+sOpts+'</select></label><br>'
    + '<label>Observações <input name="note" placeholder="Observações do item"></label><br>'
    + '<label>Quantidade <input type="number" step="1" min="1" name="quantity" value="1"></label><br>'
    + '<button type="submit" id="add_btn" disabled>Adicionar</button></form>');
}
function openEditItem(btn){
  var d=btn.dataset;
  openModal('<form method="post"><input type="hidden" name="action" value="update_item"><input type="hidden" name="item_id" value="'+d.id+'">'
    + '<label>Quantidade <input type="number" step="1" min="1" name="quantity" value="'+(d.quantity||'1')+'"></label><br>'
    + '<label>Observações <input name="note" value="'+(d.note||'')+'"></label><br>'
    + '<label>Acréscimo (R$) <input type="number" step="0.01" min="0" name="addition" value="'+(d.addition||'0')+'"></label><br>'
    + '<label>Desconto (R$) <input type="number" step="0.01" min="0" name="discount" value="'+(d.discount||'0')+'"></label><br>'
    + '<button type="submit">Salvar</button></form>');
}
function openOrderModal(itemId){
  openModal('<form method="post"><input type="hidden" name="action" value="create_order"><input type="hidden" name="item_id" value="'+itemId+'">'
    + '<label>Produção <input type="date" name="production_date"></label> '
    + '<label>Entrega <input type="date" name="delivery_date"></label> '
    + '<label>Vencimento <input type="date" name="due_date" required></label> '
    + '<button type="submit">Criar Pedido</button></form>');
}
</script>
JS;