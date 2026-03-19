<?php
$host = '10.5.213.111';
$port = 5432;
$dbname = 'db_evolution1';
$user = 'mdavid';
$password = '3205560180m';
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
}
catch (PDOException $e) {
    die("ERROR DB: " . $e->getMessage());
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Define BASE_PATH for filesystem includes (absolute path to /Front)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__));
}

// Define BASE_URL for absolute paths
if (!defined('BASE_URL')) {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    // Look for /src/Php/ to determine base URL
    $pos = strpos($script_name, '/src/Php/');
    if ($pos !== false) {
        $base_url = substr($script_name, 0, $pos);
    }
    else {
        // If not in src/Php, we might be at index.php in the root
        $base_url = rtrim(dirname($script_name), '/\\');
        if ($base_url === '\\')
            $base_url = '';
    }
    define('BASE_URL', $base_url);
}

// Define $base_path for absolute internal filesystem paths
// Auto-detect project root: __DIR__ = src/Php/config → up 3 levels → project root
$base_path = realpath(__DIR__ . '/../../..');
