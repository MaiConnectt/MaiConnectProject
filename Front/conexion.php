<?php
$host = 'localhost';
$port = 5432;
$dbname = 'MaiConnect';
$user = 'postgres';
$password = '3205560180';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("ERROR DB: " . $e->getMessage());
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Define BASE_PATH for filesystem includes (absolute path to /Front)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__));
}

// Define BASE_URL for absolute paths
if (!defined('BASE_URL')) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $pos = strpos($script_name, '/Front');
    if ($pos !== false) {
        $base_url = substr($script_name, 0, $pos);
    } else {
        // Fallback: assume we are at root or structure is different
        $base_url = '';
    }
    define('BASE_URL', $base_url);
}
