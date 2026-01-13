<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/gcode_parser.php';
require_once __DIR__ . '/../src/auth.php';
require_login();
header('Content-Type: application/json');
if (!isset($_FILES['gcode'])) {
    $cl = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $pms = ini_get('post_max_size');
    $umf = ini_get('upload_max_filesize');
    $toBytes = static function ($s) {
        $s = trim((string)$s);
        if ($s === '') return 0;
        $unit = strtolower(substr($s, -1));
        $num = (float)$s;
        if ($unit === 'g') return (int)($num * 1024 * 1024 * 1024);
        if ($unit === 'm') return (int)($num * 1024 * 1024);
        if ($unit === 'k') return (int)($num * 1024);
        return (int)$num;
    };
    $pmsB = $toBytes($pms);
    $umfB = $toBytes($umf);
    if ($cl > 0 && ($pmsB > 0 && $cl > $pmsB)) {
        http_response_code(413);
        echo json_encode(['error' => 'Arquivo excede o limite de post_max_size do servidor']);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}
if ($_FILES['gcode']['error'] !== UPLOAD_ERR_OK) {
    $err = (int)$_FILES['gcode']['error'];
    $status = 400;
    $msg = 'Falha no upload';
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $status = 413; $msg = 'Arquivo excede o limite do servidor'; }
    elseif ($err === UPLOAD_ERR_PARTIAL) { $msg = 'Upload parcial, tente novamente'; }
    elseif ($err === UPLOAD_ERR_NO_FILE) { $msg = 'Nenhum arquivo enviado'; }
    elseif ($err === UPLOAD_ERR_NO_TMP_DIR) { $status = 500; $msg = 'Servidor sem diretório temporário'; }
    elseif ($err === UPLOAD_ERR_CANT_WRITE) { $status = 500; $msg = 'Servidor não conseguiu gravar o arquivo'; }
    elseif ($err === UPLOAD_ERR_EXTENSION) { $msg = 'Upload bloqueado por extensão'; }
    http_response_code($status);
    echo json_encode(['error' => $msg]);
    exit;
}
$dir = __DIR__ . '/../uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$origExt = strtolower(pathinfo($_FILES['gcode']['name'] ?? '', PATHINFO_EXTENSION));
if ($origExt === '') { $origExt = 'gcode'; }
$name = bin2hex(random_bytes(8)) . '.gcode';
$path = $dir . '/' . $name;
if ($origExt === 'gz') {
    $data = @file_get_contents($_FILES['gcode']['tmp_name']);
    $decoded = $data !== false ? @gzdecode($data) : false;
    if ($decoded === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Falha ao descompactar .gz']);
        exit;
    }
    if (@file_put_contents($path, $decoded) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao salvar arquivo descompactado']);
        exit;
    }
} else {
    if (!move_uploaded_file($_FILES['gcode']['tmp_name'], $path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao salvar arquivo']);
        exit;
    }
}
$parsed = parseGcode($path);
echo json_encode([
    'file' => $name,
    'is_multicolor' => $parsed['is_multicolor'],
    'filament_grams' => $parsed['filament_grams'],
    'print_time_seconds' => $parsed['print_time_seconds'],
    'thumbnail_base64' => $parsed['thumbnail_base64']
]);