<?php
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/pix.php';

$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

echo "<h1>Debug PIX</h1>";
echo "<h2>Parameters in DB:</h2>";
echo "<pre>";
print_r($params);
echo "</pre>";

if (!empty($params['pix_key'])) {
    $payload = pix_payload($params['pix_key'], $params['pix_name'] ?? 'Test', $params['pix_city'] ?? 'City', 10.00);
    echo "<h2>Generated Payload:</h2>";
    echo "<pre>" . htmlspecialchars($payload) . "</pre>";
    echo "<h2>QR Code URL:</h2>";
    $url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($payload);
    echo "<a href='$url' target='_blank'>$url</a>";
    echo "<br><br><img src='$url' alt='QR Test' style='border: 1px solid red;'>";
} else {
    echo "<h2>PIX KEY IS EMPTY!</h2>";
}