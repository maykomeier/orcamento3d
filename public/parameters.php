<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
$pdo = db();
$mig1 = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='parameters' AND COLUMN_NAME='print_note1'");
try { $mig1->execute(); if ((int)($mig1->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE parameters ADD COLUMN print_note1 VARCHAR(255) DEFAULT NULL"); } } catch (Throwable $e) { }
$mig2 = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='parameters' AND COLUMN_NAME='print_note2'");
try { $mig2->execute(); if ((int)($mig2->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE parameters ADD COLUMN print_note2 VARCHAR(255) DEFAULT NULL"); } } catch (Throwable $e) { }
$mig3 = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='parameters' AND COLUMN_NAME='pix_key'");
try { $mig3->execute(); if ((int)($mig3->fetch()['c'] ?? 0) === 0) { $pdo->exec("ALTER TABLE parameters ADD COLUMN pix_key VARCHAR(255) DEFAULT NULL, ADD COLUMN pix_name VARCHAR(255) DEFAULT NULL, ADD COLUMN pix_city VARCHAR(255) DEFAULT NULL"); } } catch (Throwable $e) { }
$active = 'parameters';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $energy = (float)($_POST['energy_cost_kwh'] ?? 0);
    $margin = (float)($_POST['profit_margin_percent'] ?? 0);
    $hourly = (float)($_POST['hourly_additional'] ?? 0);
    $life = (int)($_POST['printer_life_hours'] ?? 0);
    $companyName = trim($_POST['company_name'] ?? '');
    $companyPhone = trim($_POST['company_phone'] ?? '');
    $companyEmail = trim($_POST['company_email'] ?? '');
    $printNote1 = trim($_POST['print_note1'] ?? '');
    $printNote2 = trim($_POST['print_note2'] ?? '');
    $pixKey = trim($_POST['pix_key'] ?? '');
    $pixName = trim($_POST['pix_name'] ?? '');
    $pixCity = trim($_POST['pix_city'] ?? '');
    
    // Database Configuration
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');

    if ($dbHost && $dbName && $dbUser) {
        try {
            // Test connection before saving
            $testDsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
            $testPdo = new PDO($testDsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            
            // If success, write config.php
            $configContent = "<?php\n";
            $configContent .= "define('DB_HOST', '" . addslashes($dbHost) . "');\n";
            $configContent .= "define('DB_NAME', '" . addslashes($dbName) . "');\n";
            $configContent .= "define('DB_USER', '" . addslashes($dbUser) . "');\n";
            $configContent .= "define('DB_PASS', '" . addslashes($dbPass) . "');\n";
            $configContent .= "define('DB_PORT', " . $dbPort . ");\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n";
            
            file_put_contents(__DIR__ . '/../src/config.php', $configContent);
            
        } catch (Throwable $e) {
            echo "<script>alert('Erro na configuração do banco de dados: " . addslashes($e->getMessage()) . ". As alterações do banco NÃO foram salvas.'); history.back();</script>";
            exit;
        }
    }

    $logoRelPath = null;
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/uploads/logos';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        if (!$ext) { $ext = 'png'; }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $path = $dir . '/' . $name;
        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $path)) {
            $logoRelPath = 'uploads/logos/' . $name;
        }
    }
    
    // If no new logo uploaded, try to keep the existing one
    if ($logoRelPath === null) {
        $lastParams = $pdo->query('SELECT company_logo FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
        if ($lastParams && !empty($lastParams['company_logo'])) {
            $logoRelPath = $lastParams['company_logo'];
        }
    }

    $pdo->prepare('INSERT INTO parameters (energy_cost_kwh, profit_margin_percent, hourly_additional, printer_life_hours, company_name, company_phone, company_email, company_logo, print_note1, print_note2, pix_key, pix_name, pix_city) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$energy,$margin,$hourly,$life,$companyName,$companyPhone,$companyEmail,$logoRelPath,$printNote1,$printNote2,$pixKey,$pixName,$pixCity]);
    header('Location: /public/parameters.php');
    exit;
}
$row = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Parâmetros</title><link rel="stylesheet" href="/public/assets/style.css"></head><body>';
echo '<header class="toolbar">';
if (!empty($row['company_logo']) || !empty($row['company_name'])) {
  echo '<div class="brand">';
  if (!empty($row['company_logo'])) { echo '<img src="/public/' . htmlspecialchars($row['company_logo']) . '" alt="logo">'; }
  echo '<span class="name">' . htmlspecialchars($row['company_name'] ?? '') . '</span>';
  echo '</div>';
}
echo '<h1>Parâmetros</h1><span class="spacer"></span><button id="theme-toggle" class="icon-button" title="Tema"><svg class="icon"><use id="theme-toggle-icon" href="/public/assets/icons.svg#moon"></use></svg></button><a href="/public/index.php">Menu</a></header>';
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
echo '<form method="post" enctype="multipart/form-data">';
echo '<label>Custo de energia (R$/kWh) <input type="number" step="0.0001" name="energy_cost_kwh" value="' . ($row['energy_cost_kwh'] ?? 0) . '"></label><br>';
echo '<label>Margem de lucro (%) <input type="number" step="0.01" name="profit_margin_percent" value="' . ($row['profit_margin_percent'] ?? 0) . '"></label><br>';
echo '<label>Valor adicional por hora (R$) <input type="number" step="0.01" name="hourly_additional" value="' . ($row['hourly_additional'] ?? 0) . '"></label><br>';
echo '<label>Vida útil da impressora (h) <input type="number" step="1" name="printer_life_hours" value="' . ($row['printer_life_hours'] ?? 0) . '"></label><br>';
echo '<hr>';
echo '<label>Nome da empresa <input name="company_name" value="' . htmlspecialchars($row['company_name'] ?? '') . '"></label><br>';
echo '<label>Telefone <input name="company_phone" value="' . htmlspecialchars($row['company_phone'] ?? '') . '"></label><br>';
echo '<label>E-mail <input type="email" name="company_email" value="' . htmlspecialchars($row['company_email'] ?? '') . '"></label><br>';
echo '<label>Logotipo <input type="file" name="company_logo" accept="image/*"></label> ';
if (!empty($row['company_logo'])) { echo '<span>(Atual)</span> <img src="/public/' . htmlspecialchars($row['company_logo']) . '" alt="logo" style="height:28px">'; }
echo '<hr>';
echo '<label>Frase de impressão (linha 1) <input name="print_note1" value="' . htmlspecialchars($row['print_note1'] ?? '') . '" placeholder="Prazo de entrega, 5 dias após a aprovação do orçamento."></label><br>';
echo '<label>Frase de impressão (linha 2) <input name="print_note2" value="' . htmlspecialchars($row['print_note2'] ?? '') . '" placeholder="Opcional"></label><br>';
echo '<hr><h3>Configuração PIX</h3>';
echo '<label>Chave PIX <input name="pix_key" value="' . htmlspecialchars($row['pix_key'] ?? '') . '" placeholder="CPF, Email, Telefone ou Aleatória"></label><br>';
echo '<label>Nome Beneficiário <input name="pix_name" value="' . htmlspecialchars($row['pix_name'] ?? '') . '" placeholder="Nome completo sem acentos (recomendado)"></label><br>';
echo '<label>Cidade Beneficiário <input name="pix_city" value="' . htmlspecialchars($row['pix_city'] ?? '') . '" placeholder="Cidade sem acentos (recomendado)"></label><br>';
echo '<hr><h3>Configuração do Banco de Dados</h3>';
echo '<label>Host <input name="db_host" value="' . (defined('DB_HOST') ? DB_HOST : '') . '"></label><br>';
echo '<label>Porta <input name="db_port" type="number" value="' . (defined('DB_PORT') ? DB_PORT : 3306) . '"></label><br>';
echo '<label>Banco <input name="db_name" value="' . (defined('DB_NAME') ? DB_NAME : '') . '"></label><br>';
echo '<label>Usuário <input name="db_user" value="' . (defined('DB_USER') ? DB_USER : '') . '"></label><br>';
echo '<label>Senha <input type="password" name="db_pass" value="' . (defined('DB_PASS') ? DB_PASS : '') . '"></label><br>';
echo '<div style="font-size:0.9em;color:#666;margin-bottom:10px">⚠️ Cuidado: Alterar estes dados pode fazer o sistema parar de funcionar se a conexão falhar.</div>';
echo '<button type="submit">Salvar</button>';
echo '</form>';

echo '<hr>';
echo '<h3>Backup do Sistema</h3>';
echo '<p>Clique no botão abaixo para baixar um backup completo do sistema (Banco de Dados + Imagens).</p>';
echo '<form action="/public/backup.php" method="get" target="_blank">';
echo '<button type="submit" style="background-color: #6c757d; color: white;">⬇️ Baixar Backup Completo (.zip)</button>';
echo '</form>';

echo '<p><a href="/public/index.php">Voltar</a></p>';
echo '</main></div><script src="/public/assets/js/theme.js"></script></body></html>';