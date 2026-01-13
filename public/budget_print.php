<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/pix.php';
require_login();
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
if (!$params) { $params = []; }
$budgetId = (int)($_GET['budget_id'] ?? 0);
if ($budgetId <= 0) { http_response_code(400); echo 'Sem orçamento.'; exit; }
$budget = $pdo->prepare('SELECT b.*, c.name AS client_name FROM budgets b JOIN clients c ON c.id=b.client_id WHERE b.id=?');
$budget->execute([$budgetId]);
$b = $budget->fetch();
$items = $pdo->prepare('SELECT id, gcode_file, quantity, item_total, print_time_seconds, thumbnail_base64, note FROM budget_items WHERE budget_id=? ORDER BY id ASC');
$items->execute([$budgetId]);
$rows = $items->fetchAll();

// Calculate totals and prepare item data
$totalWeight = 0;
foreach ($rows as &$r) {
    $fs = $pdo->prepare('SELECT COUNT(*) as colors, SUM(grams) as weight FROM budget_item_filaments WHERE budget_item_id=?');
    $fs->execute([$r['id']]);
    $stats = $fs->fetch();
    $r['colors'] = (int)($stats['colors'] ?? 1);
    $r['weight'] = (float)($stats['weight'] ?? 0);
    $totalWeight += $r['weight'];
}
unset($r);

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Imprimir Orçamento</title>';
echo '<style>
@page { size: A4; margin: 10mm; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { background: #fff; color: #333; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 13px; margin: 0; padding: 0; }
.print-page { max-width: 100%; margin: 0 auto; }

/* Header */
.header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.header-table td { vertical-align: top; }
.company-info h1 { margin: 0 0 5px 0; font-size: 20px; color: #000; }
.company-logo { max-height: 60px; margin-bottom: 5px; display: block; }
.company-info p { margin: 2px 0; color: #666; font-size: 12px; }
.budget-meta { text-align: right; }
.budget-meta h2 { margin: 0; font-size: 24px; color: #ccc; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; }
.meta-number { font-size: 18px; color: #4a69bd; font-weight: bold; margin: 5px 0; }
.meta-date { color: #666; font-size: 12px; }

.divider { border: 0; border-top: 1px solid #eee; margin: 15px 0; }

/* Info */
.info-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
.info-table td { vertical-align: top; width: 50%; }
.client-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-right: 20px; }
.client-box .label { font-size: 10px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 5px; }
.client-box .client-name { font-size: 16px; font-weight: bold; color: #000; margin-bottom: 3px; }
.client-box div { color: #555; font-size: 12px; }
.payment-note-box { text-align: right; padding-left: 20px; }
.payment-method { margin-bottom: 5px; font-size: 12px; }
.payment-method .label { color: #999; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-right: 10px; }
.note-text { font-style: italic; color: #777; font-size: 12px; line-height: 1.3; }

/* Items */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
.items-table th { text-align: left; border-bottom: 2px solid #000; padding: 5px 0; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
.items-table td { border-bottom: 1px solid #eee; padding: 4px 0; vertical-align: middle; height: 160px; }
.col-thumb { width: 160px; }
.item-thumb { width: 150px; height: 150px; border-radius: 4px; border: 1px solid #eee; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fff; }
.item-thumb img { max-width: 100%; max-height: 100%; }
.item-details { padding-left: 10px; display: flex; align-items: center; gap: 10px; overflow: hidden; }
.item-name { font-weight: bold; font-size: 12px; white-space: nowrap; }
.item-tags { font-size: 10px; color: #999; text-transform: uppercase; white-space: nowrap; }
.tag { display: inline-block; margin-right: 5px; }
.qty-tag { color: #333; font-weight: bold; }
.col-right { text-align: right; white-space: nowrap; font-size: 12px; color: #555; }
.col-price { text-align: right; white-space: nowrap; font-size: 12px; font-weight: bold; color: #000; }

/* Footer */
.footer-table { width: 100%; border-collapse: collapse; margin-top: 20px; page-break-inside: avoid; }
.footer-table td { vertical-align: top; }
.pix-cell { width: 60%; padding-right: 20px; }
.pix-card { background: #ecfbf5; border: 1px solid #d1f2e3; border-radius: 8px; padding: 10px; display: flex; gap: 15px; }
.pix-qr { flex-shrink: 0; }
.pix-qr img { width: 80px; height: 80px; border-radius: 4px; mix-blend-mode: multiply; display: block; background: #eee; }
.pix-info { display: flex; flex-direction: column; justify-content: center; }
.pix-title { color: #00b894; font-weight: bold; font-size: 11px; text-transform: uppercase; margin-bottom: 3px; display: flex; align-items: center; gap: 5px; }
.pix-desc { font-size: 11px; color: #555; margin-bottom: 5px; line-height: 1.2; }
.pix-key-label { font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
.pix-key-value { font-family: monospace; font-size: 12px; color: #333; font-weight: bold; }
.totals-cell { width: 40%; text-align: right; }
.total-line { margin-bottom: 5px; color: #666; font-size: 13px; }
.total-final { margin-top: 10px; color: #4a69bd; font-size: 18px; font-weight: 800; }
.lbl { margin-right: 15px; }
.bottom-note { text-align: center; margin-top: 20px; color: #999; font-size: 10px; border-top: 1px solid #eee; padding-top: 10px; }

/* Screen only */
.toolbar { display: none; }
@media screen {
  body { background: #f0f0f0; padding: 20px; }
  .print-page { background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 4px; padding: 30px; margin-top: 50px; max-width: 210mm; }
  .toolbar { display: flex; position: fixed; top: 0; left: 0; right: 0; height: 50px; background: #fff; border-bottom: 1px solid #ddd; align-items: center; padding: 0 20px; z-index: 100; }
}
</style>';
echo '<script>
window.addEventListener("load", function() {
    var images = document.getElementsByTagName("img");
    var total = images.length;
    var loaded = 0;
    
    if (total === 0) {
        window.print();
    } else {
        var check = function() {
            loaded++;
            if (loaded >= total) window.print();
        };
        
        for (var i = 0; i < total; i++) {
            if (images[i].complete) {
                check();
            } else {
                images[i].addEventListener("load", check);
                images[i].addEventListener("error", check);
            }
        }
        // Force print after 3 seconds if events fail
        setTimeout(function(){ if (loaded < total) window.print(); }, 3000);
    }
});
</script></head><body>';

// HEADER
echo '<div class="print-page">';
echo '<table class="header-table"><tr>';
echo '<td><div class="company-info">';
if (!empty($params['company_name'])) {
    echo '<h1>' . htmlspecialchars($params['company_name']) . '</h1>';
} else {
    echo '<h1>Custo3D</h1>';
}
if (!empty($params['company_logo'])) {
    echo '<img src="/public/' . htmlspecialchars($params['company_logo']) . '" class="company-logo">';
}
if (!empty($params['company_phone']) || !empty($params['company_email'])) {
    echo '<p>' . htmlspecialchars($params['company_email'] ?? '') . ' | ' . htmlspecialchars($params['company_phone'] ?? '') . '</p>';
}
echo '</div></td>';
echo '<td><div class="budget-meta">';
echo '<h2>ORÇAMENTO</h2>';
echo '<div class="meta-number">#' . str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) . '</div>';
echo '<div class="meta-date">Data: ' . htmlspecialchars(fmt_date($b['date'])) . '</div>';
echo '</div></td>';
echo '</tr></table>';

echo '<hr class="divider">';

// INFO
echo '<table class="info-table"><tr>';
echo '<td><div class="client-box">';
echo '<div class="label">CLIENTE</div>';
echo '<div class="client-name">' . htmlspecialchars($b['client_name']) . '</div>';
if (!empty($b['client_email'])) echo '<div>' . htmlspecialchars($b['client_email']) . '</div>';
if (!empty($b['client_phone'])) echo '<div>' . htmlspecialchars($b['client_phone']) . '</div>';
echo '</div></td>';
echo '<td><div class="payment-note-box">';
echo '<div class="payment-method"><span class="label">PAGAMENTO</span> <strong>Pix</strong></div>';
$note1 = ($params['print_note1'] ?? '') !== '' ? $params['print_note1'] : 'Este orçamento tem validade de 15 dias corridos.';
echo '<div class="note-text">“' . htmlspecialchars($note1) . '”</div>';
echo '</div></td>';
echo '</tr></table>';

// ITEMS
echo '<table class="items-table">';
echo '<thead><tr><th colspan="2">Item / Preview</th><th class="col-right">Material</th><th class="col-right">Tempo</th><th class="col-right">Valor Unit.</th></tr></thead>';
echo '<tbody>';
foreach ($rows as $r) {
    $unitPrice = $r['quantity'] > 0 ? $r['item_total'] / $r['quantity'] : 0;
    $timeH = floor($r['print_time_seconds'] / 3600);
    $timeM = floor(($r['print_time_seconds'] % 3600) / 60);
    
    echo '<tr>';
    echo '<td class="col-thumb"><div class="item-thumb">';
    if ($r['thumbnail_base64']) {
        echo '<img src="data:image/png;base64,' . htmlspecialchars($r['thumbnail_base64']) . '">';
    } else {
        echo '<div style="background:#eee;width:100%;height:100%"></div>';
    }
    echo '</div></td>';
    
    echo '<td><div class="item-details">';
    echo '<div class="item-name">' . htmlspecialchars($r['gcode_file']) . '</div>';
    echo '<div class="item-tags"><span class="tag">FDM</span>';
    if ($r['colors'] > 1) echo ' <span class="tag">| MULTI (' . $r['colors'] . ')</span>';
    if ($r['quantity'] > 1) echo ' <span class="tag qty-tag">| Qtd: ' . $r['quantity'] . '</span>';
    echo '</div></div></td>';
    
    echo '<td class="col-right">' . number_format($r['weight'], 2, ',', '.') . 'g</td>';
    echo '<td class="col-right">' . $timeH . 'h ' . str_pad((string)$timeM, 2, '0', STR_PAD_LEFT) . 'm</td>';
    echo '<td class="col-price">R$ ' . number_format($unitPrice, 2, ',', '.') . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';

// FOOTER
echo '<table class="footer-table"><tr>';
echo '<td class="pix-cell">';
if (!empty($params['pix_key'])) {
    $pixPayload = pix_payload($params['pix_key'], $params['pix_name'] ?? 'Loja', $params['pix_city'] ?? 'BRASIL', (float)$b['total']);
    echo '<div class="pix-card">';
    echo '<div class="pix-qr"><img src="https://quickchart.io/qr?size=150&margin=1&text=' . urlencode($pixPayload) . '" alt="QR Code"></div>';
    echo '<div class="pix-info">';
    echo '<div class="pix-title">PAGAMENTO VIA PIX</div>';
    echo '<div class="pix-desc">Pagar exato <strong>R$ ' . number_format((float)$b['total'], 2, ',', '.') . '</strong></div>';
    echo '<div class="pix-key-label">CHAVE:</div>';
    echo '<div class="pix-key-value">' . htmlspecialchars($params['pix_key']) . '</div>';
    echo '</div></div>';
} else {
    echo '<!-- PIX not shown: pix_key is empty in parameters. -->';
    if (!isset($_GET['print'])) {
        echo '<div style="background:#fff3cd; color:#856404; padding:10px; border:1px solid #ffeeba; border-radius:4px; font-size:12px; margin-top:10px;" class="no-print">';
        echo '<strong>Atenção:</strong> QR Code Pix não exibido pois a Chave Pix não foi configurada. <a href="/public/parameters.php">Configurar agora</a>.';
        echo '</div>';
    }
}
echo '</td>';
echo '<td class="totals-cell">';
echo '<div class="total-line"><span class="lbl">Subtotal:</span> R$ ' . number_format((float)$b['total'], 2, ',', '.') . '</div>';
echo '<div class="total-line"><span class="lbl">Taxas:</span> R$ 0,00</div>';
echo '<div class="total-final"><span class="lbl">TOTAL:</span> R$ ' . number_format((float)$b['total'], 2, ',', '.') . '</div>';
echo '</td>';
echo '</tr></table>';

if (!empty($params['print_note2'])) {
    echo '<p class="bottom-note">' . htmlspecialchars($params['print_note2']) . '</p>';
}
echo '</div>'; // print-page
echo '<header class="toolbar no-print"><h1>Imprimir</h1><span class="spacer"></span><a href="/public/budgets.php" class="icon-button">Voltar</a><button onclick="window.print()" class="icon-button">Imprimir</button></header>';
echo '</body></html>';