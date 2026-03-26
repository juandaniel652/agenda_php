<?php
$autoload = __DIR__ . '/vendor/autoload.php';
echo "existe autoload: " . (file_exists($autoload) ? 'SI' : 'NO') . "<br>";

$classLoader = __DIR__ . '/vendor/composer/ClassLoader.php';
echo "existe ClassLoader: " . (file_exists($classLoader) ? 'SI' : 'NO') . "<br>";

$response = __DIR__ . '/app/core/Response.php';
echo "existe Response.php: " . (file_exists($response) ? 'SI' : 'NO') . "<br>";

// Intentar cargar solo el autoload
try {
    require $autoload;
    echo "autoload cargado: OK<br>";
} catch (Throwable $e) {
    echo "error: " . $e->getMessage() . "<br>";
}