<?php
require_once __DIR__ . '/../src/db.php';
$pdo = db();
$params = $pdo->query('SELECT * FROM parameters ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
echo '<pre>';
print_r($params);
echo '</pre>';