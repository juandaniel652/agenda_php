<?php
// Simular exactamente lo que hace index.php
$autoload = '/home2/androsnet/public_html/api/vendor/autoload.php';
require $autoload;

// Ver qué paths tiene registrados DESPUÉS de cargar
$loader = include $autoload;
$prefixes = $loader->getPrefixesPsr4();

echo '<pre>';
echo "PSR4 registrados:\n";
print_r($prefixes);
echo '</pre>';