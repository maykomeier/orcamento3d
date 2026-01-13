<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$active = 'reports';
$start = $_GET['start_date'] ?? null; $end = $_GET['end_date'] ?? null;
if (!$start || !$end) {
  $start = (new DateTime('first day of this month'))->format('Y-m-d');
  $end = (new DateTime())->format('Y-m-d');
}
$view = $_GET['view'] ?? 'grouped';
// vendas (budgets)
$salesRows = [];
try {
  $q = $pdo->prepare('SELECT b.id, b.client_id, b.date, b.total, b.approved, c.name AS client_name FROM budgets b LEFT JOIN clients c ON c.id=b.client_id WHERE b.date BETWEEN ? AND ? ORDER BY b.date');
  $q->execute([$start,$end]);
  $salesRows = $q->fetchAll();
} catch (Throwable $e) { $salesRows = []; }
$salesTotal = 0.0; foreach ($salesRows as $sr) { $salesTotal += (float)($sr['total'] ?? 0); }
// receber
$recRows = []; $recTotal = 0.0;
try {
  $q = $pdo->prepare('SELECT * FROM accounts_receivable WHERE due_date BETWEEN ? AND ? ORDER BY due_date');
  $q->execute([$start,$end]);
  $recRows = $q->fetchAll();
} catch (Throwable $e) { $recRows = []; }
foreach ($recRows as $rr) { $recTotal += (float)$rr['amount']; }
// pagar
$payRows = []; $payTotal = 0.0;
try {
  $q = $pdo->prepare('SELECT ap.*, fc.name AS category_name FROM accounts_payable ap LEFT JOIN finance_categories fc ON fc.id=ap.category_id WHERE due_date BETWEEN ? AND ? ORDER BY due_date');
  $q->execute([$start,$end]);
  $payRows = $q->fetchAll();
} catch (Throwable $e) { $payRows = []; }
foreach ($payRows as $pr) { $payTotal += (float)$pr['amount']; }
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Relatórios</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($params['company_logo']) || !empty($params['company_name'])) {
  echo '<div class="brand">';
  if (!empty($params['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($params['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Relatórios</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<a href="/public/reports.php" class="active"><svg class="icon"><use href="/public/assets/icons.svg#list"></use></svg> Relatórios</a>';
echo '</aside>';
echo '<main class="content">';
echo '<div class="card"><h3>Período</h3><form method="get" class="inline">';
echo '<label>Início <input type="date" name="start_date" value="' . htmlspecialchars($start) . '" required></label> ';
echo '<label>Fim <input type="date" name="end_date" value="' . htmlspecialchars($end) . '" required></label> ';
echo '<button type="submit">Aplicar</button>';
echo '</form></div>';
echo '<div class="widgets-grid">';
echo '<div class="widget w-approved"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#money"></use></svg><h4>Vendas (Orçamentos)</h4></div><div class="value">R$ ' . number_format($salesTotal,2,',','.') . '</div></div>';
echo '<div class="widget w-money"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#check"></use></svg><h4>Receber</h4></div><div class="value">R$ ' . number_format($recTotal,2,',','.') . '</div></div>';
echo '<div class="widget w-pending"><div style="display:flex;align-items:center;gap:8px"><svg class="icon"><use href="/public/assets/icons.svg#toolbox"></use></svg><h4>Pagar</h4></div><div class="value">R$ ' . number_format($payTotal,2,',','.') . '</div></div>';
echo '</div>';
echo '<div class="card"><h3>Vendas por Orçamento</h3><table><tr><th>ID</th><th>Cliente</th><th>Data</th><th>Total</th><th>Status</th></tr>';
foreach ($salesRows as $sr) {
  echo '<tr><td>#' . (int)$sr['id'] . '</td><td>' . htmlspecialchars($sr['client_name'] ?? '') . '</td><td>' . htmlspecialchars(fmt_date($sr['date'])) . '</td><td>R$ ' . number_format((float)$sr['total'],2,',','.') . '</td><td>' . ($sr['approved']? '<span class="badge approved">Aprovado</span>' : '<span class="badge rejected">Pendente</span>') . '</td></tr>';
}
echo '</table></div>';
echo '<div class="card"><h3>Contas a Receber</h3><table><tr><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr>';
foreach ($recRows as $rr) {
  echo '<tr><td>' . htmlspecialchars($rr['description'] ?? '') . '</td><td>' . htmlspecialchars(fmt_date($rr['due_date'])) . '</td><td>R$ ' . number_format((float)$rr['amount'],2,',','.') . '</td><td>' . ($rr['received']? '<span class="badge approved">Recebido</span>' : '<span class="badge rejected">Pendente</span>') . '</td></tr>';
}
echo '</table></div>';
echo '<div class="card"><h3>Contas a Pagar</h3><table><tr><th>Categoria</th><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr>';
foreach ($payRows as $pr) {
  $catLabel = $pr['category_name'] ? $pr['category_name'] : ($pr['category']==='FILAMENTO'?'Filamento':($pr['category']==='ENERGIA'?'Energia':'Outros'));
  echo '<tr><td>' . htmlspecialchars($catLabel) . '</td><td>' . htmlspecialchars($pr['description']) . '</td><td>' . htmlspecialchars(fmt_date($pr['due_date'])) . '</td><td>R$ ' . number_format((float)$pr['amount'],2,',','.') . '</td><td>' . ($pr['paid']? '<span class="badge approved">Pago</span>' : '<span class="badge rejected">Pendente</span>') . '</td></tr>';
}
echo '</table></div>';
// Fluxo de Caixa 12 meses
$months = []; $now = new DateTime('first day of this month');
for ($i=11;$i>=0;$i--) { $m = (clone $now)->modify('-' . $i . ' months'); $key = $m->format('Y-m'); $months[$key] = ['label'=>$m->format('M/Y'),'in'=>0.0,'out'=>0.0]; }
$rec = []; try { $rec = $pdo->query("SELECT DATE_FORMAT(COALESCE(received_date,due_date),'%Y-%m') ym, COALESCE(SUM(amount),0) s FROM accounts_receivable GROUP BY ym")->fetchAll(); } catch (Throwable $e) { $rec = []; }
foreach ($rec as $row) { $ym = $row['ym']; if (isset($months[$ym])) { $months[$ym]['in'] += (float)$row['s']; } }
$pay = []; try { $pay = $pdo->query("SELECT DATE_FORMAT(COALESCE(paid_date,due_date),'%Y-%m') ym, COALESCE(SUM(amount),0) s FROM accounts_payable GROUP BY ym")->fetchAll(); } catch (Throwable $e) { $pay = []; }
foreach ($pay as $row) { $ym = $row['ym']; if (isset($months[$ym])) { $months[$ym]['out'] += (float)$row['s']; } }
$maxIn = 0.0; $maxOut = 0.0; $maxAbsBal = 0.0; foreach ($months as $m) { $maxIn = max($maxIn, (float)$m['in']); $maxOut = max($maxOut, (float)$m['out']); $maxAbsBal = max($maxAbsBal, abs((float)$m['in'] - (float)$m['out'])); }
$maxVal = $view === 'grouped' ? max($maxIn, $maxOut, $maxAbsBal, 1.0) : max($maxAbsBal, 1.0);
echo '<div class="card"><h3>Fluxo de Caixa (12 meses)</h3><div class="chart">';
echo '<div class="controls">';
$qs = 'start_date=' . urlencode($start) . '&end_date=' . urlencode($end);
echo '<a class="button' . ($view==='grouped'?'':'') . '" href="?' . $qs . '&view=grouped">Agrupado</a>';
echo '<a class="button' . ($view==='saldo'?'':'') . '" href="?' . $qs . '&view=saldo">Saldo</a>';
echo '</div>';
if ($view === 'grouped') {
  echo '<div class="legend"><span><span class="swatch" style="background:#6366f1"></span>Receber</span><span><span class="swatch" style="background:#f97316"></span>Pagar</span></div>';
  echo '<svg viewBox="0 0 ' . (12*56) . ' 220" preserveAspectRatio="none">';
  $x = 16; $w = 16; $gap = 14; $base = 190; $scale = 150/$maxVal; $idx = 0; $linePoints = [];
  foreach ($months as $k => $m) {
    $hin = round($m['in'] * $scale); $hout = round($m['out'] * $scale);
    $yin = $base - $hin; $yout = $base - $hout;
    echo '<rect class="bar-in" x="' . $x . '" y="' . $yin . '" width="' . $w . '" height="' . $hin . '" rx="3" />';
    echo '<rect class="bar-out" x="' . ($x + $w + 2) . '" y="' . $yout . '" width="' . $w . '" height="' . $hout . '" rx="3" />';
    $label = (new DateTimeImmutable($k . '-01'))->format('M/Y');
    $lx = $x + $w; $ly = 205;
    echo '<text x="' . $lx . '" y="' . $ly . '" text-anchor="middle" class="axis" transform="rotate(-30 ' . $lx . ' ' . $ly . ')">' . htmlspecialchars($label) . '</text>';
    $bal = (float)$m['in'] - (float)$m['out'];
    $linePoints[] = ($x + $w) . ',' . ($base - round($bal * $scale));
    $x += (2*$w + $gap + 2); $idx++;
  }
  echo '<polyline class="saldo-line" points="' . implode(' ', $linePoints) . '" />';
  echo '<line x1="0" y1="' . $base . '" x2="' . (12*56) . '" y2="' . $base . '" stroke="var(--border)" />';
  echo '</svg>';
} else {
  echo '<div class="legend"><span><span class="swatch" style="background:#10b981"></span>Saldo +</span><span><span class="swatch" style="background:#ef4444"></span>Saldo -</span></div>';
  echo '<svg viewBox="0 0 ' . (12*48) . ' 220" preserveAspectRatio="none">';
  $x = 16; $w = 18; $gap = 18; $base = 190; $scale = 150/$maxVal; $linePoints = [];
  foreach ($months as $k => $m) {
    $bal = (float)$m['in'] - (float)$m['out'];
    $h = round(abs($bal) * $scale);
    if ($bal >= 0) {
      echo '<rect class="bar-pos" x="' . $x . '" y="' . ($base - $h) . '" width="' . $w . '" height="' . $h . '" rx="3" />';
    } else {
      echo '<rect class="bar-neg" x="' . $x . '" y="' . $base . '" width="' . $w . '" height="' . $h . '" rx="3" />';
    }
    $label = (new DateTimeImmutable($k . '-01'))->format('M/Y');
    $lx = $x + ($w/2); $ly = 205;
    echo '<text x="' . $lx . '" y="' . $ly . '" text-anchor="middle" class="axis" transform="rotate(-30 ' . $lx . ' ' . $ly . ')">' . htmlspecialchars($label) . '</text>';
    $linePoints[] = ($x + ($w/2)) . ',' . ($base - round($bal * $scale));
    $x += ($w + $gap); 
  }
  echo '<polyline class="saldo-line" points="' . implode(' ', $linePoints) . '" />';
  echo '<line x1="0" y1="' . $base . '" x2="' . (12*48) . '" y2="' . $base . '" stroke="var(--border)" />';
  echo '</svg>';
}
echo '</div></div>';
echo '</main></div><script src="/public/assets/js/theme.js"></script></body></html>';
?>