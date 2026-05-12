<?php

// Database credentials are sourced from the Laravel .env file (or real
// environment variables). Never commit credentials to this file.

if (!function_exists('cw_env')) {
    function cw_env(string $key, ?string $default = null): ?string {
        static $loaded = false;
        static $vars = [];

        if (!$loaded) {
            $loaded = true;
            $envPath = __DIR__ . '/../.env';
            if (is_readable($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    if (strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === '') {
                        continue;
                    }
                    if (strlen($v) >= 2 && (
                        ($v[0] === '"' && substr($v, -1) === '"') ||
                        ($v[0] === "'" && substr($v, -1) === "'")
                    )) {
                        $v = substr($v, 1, -1);
                    }
                    $vars[$k] = $v;
                }
            }
        }

        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $vars[$key] ?? $default;
        }
        return $value;
    }
}

// Env
$cw_env = cw_env('APP_ENV', 'production');

// mySQL
$servername = cw_env('DB_HOST', 'localhost');
$username   = cw_env('DB_USERNAME', '');
$password   = cw_env('DB_PASSWORD', '');
$dbname     = cw_env('DB_DATABASE', '');
$port       = (int) cw_env('DB_PORT', '3306');


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// MySQL lib for standalone PHP files
require_once 'lib/php/meekrodb.2.3.class.php';

DB::$host = $servername;
DB::$port = $port;
DB::$dbName = $dbname;
DB::$user = $username;
DB::$password = $password;

DB::$connect_options = array(MYSQLI_OPT_CONNECT_TIMEOUT => 10);
