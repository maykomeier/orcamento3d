<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_login();

// Increase time limit for large backups
set_time_limit(300);
ini_set('memory_limit', '512M');

$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'custo3d';
$dbPort = defined('DB_PORT') ? DB_PORT : 3306;

$date = date('Y-m-d_H-i-s');
$backupName = 'backup_custo3d_' . $date;
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('backup_', true);

if (!mkdir($tempDir, 0777, true)) {
    die('Failed to create temporary directory.');
}

try {
    // 1. Dump Database
    $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
    
    // We will use a PHP implementation to avoid dependency on mysqldump executable availability
    $pdo = db();
    $output = "-- Backup Custo3D - $date\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    
    // Add USE database command
    $output .= "CREATE DATABASE IF NOT EXISTS `" . $dbName . "`;\n";
    $output .= "USE `" . $dbName . "`;\n";
    $output .= "SET NAMES 'utf8mb4';\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Structure
        $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $output .= "\n\nDROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";

        // Data
        $rows = $pdo->query("SELECT * FROM `$table`");
        while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
            $output .= "INSERT INTO `$table` (";
            $keys = array_keys($r);
            $output .= "`" . implode("`, `", $keys) . "`) VALUES (";
            
            $values = array_values($r);
            $quotedValues = array_map(function($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string)$v);
            }, $values);
            
            $output .= implode(", ", $quotedValues);
            $output .= ");\n";
        }
    }
    
    $output .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($sqlFile, $output);

    // 2. Create ZIP
    $zipFile = $tempDir . DIRECTORY_SEPARATOR . $backupName . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Cannot create zip file");
    }

    // Add SQL dump
    $zip->addFile($sqlFile, 'database.sql');

    // Add Public Uploads (Logos, etc) - mapped to public_uploads/
    $publicUploadDir = __DIR__ . '/uploads';
    if (is_dir($publicUploadDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($publicUploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'public_uploads/' . substr($filePath, strlen(realpath($publicUploadDir)) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    // Add Root Uploads (G-codes) - mapped to gcode_uploads/
    $rootUploadDir = __DIR__ . '/../uploads';
    if (is_dir($rootUploadDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootUploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'gcode_uploads/' . substr($filePath, strlen(realpath($rootUploadDir)) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    $zip->close();

    // 3. Serve File
    if (file_exists($zipFile)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
        header('Content-Length: ' . filesize($zipFile));
        header('Pragma: no-cache');
        readfile($zipFile);
        
        // Cleanup
        unlink($sqlFile);
        unlink($zipFile);
        rmdir($tempDir);
        exit;
    } else {
        throw new Exception("Backup file was not created.");
    }

} catch (Throwable $e) {
    // Cleanup on error
    if (file_exists($sqlFile)) unlink($sqlFile);
    if (file_exists($zipFile)) unlink($zipFile);
    if (is_dir($tempDir)) rmdir($tempDir);
    
    http_response_code(500);
    echo "Erro ao gerar backup: " . $e->getMessage();
}
