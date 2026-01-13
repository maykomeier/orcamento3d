<?php
declare(strict_types=1);
if (!file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.sample.php';
} else {
    require __DIR__ . '/config.php';
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ];
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (Throwable $e) {
        try {
            $fallback = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($fallback, DB_USER, DB_PASS, $opts);
        } catch (Throwable $e2) {
            http_response_code(500);
            echo 'Erro ao conectar ao banco MySQL. Verifique se o host, porta, usuário e senha estão corretos e se o servidor aceita conexões remotas.';
            exit;
        }
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY AUTO_INCREMENT, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $cnt = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
        if ($cnt === 0) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?,?)');
            $stmt->execute(['admin', $hash]);
        }
    } catch (Throwable $e) { }
    return $pdo;
}
function fmt_date($s): string {
    if (!$s) return '';
    try { $d = new DateTime((string)$s); return $d->format('d/m/Y'); } catch (Throwable $e) { return (string)$s; }
}
function fmt_datetime($s): string {
    if (!$s) return '';
    try { $d = new DateTime((string)$s); return $d->format('d/m/Y H:i'); } catch (Throwable $e) { return (string)$s; }
}