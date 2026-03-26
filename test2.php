<?php

$autoload = __DIR__ . '/vendor/autoload.php';

echo "ruta autoload: " . realpath($autoload) . "<br>";
echo "existe autoload: " . (file_exists($autoload) ? 'SI' : 'NO') . "<br>";

require $autoload;

echo "autoload cargado OK";