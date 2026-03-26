<?php
require_once __DIR__ . '/vendor/autoload.php';

if (class_exists('App\Core\Response')) {
    echo "✅ Clase Response encontrada";
} else {
    echo "❌ Clase Response NO encontrada. Revisa vendor/composer/autoload_psr4.php";
    echo "<pre>";
    print_r(require __DIR__ . '/vendor/composer/autoload_psr4.php');
    echo "</pre>";
}