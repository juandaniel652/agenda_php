<?php
require_once '/home2/androsnet/public_html/api/vendor/autoload.php';

// Ver qué paths tiene registrado el autoloader para App\
$loader = require '/home2/androsnet/public_html/api/vendor/autoload.php';
$prefixes = $loader->getPrefixesPsr4();
echo '<pre>';
print_r($prefixes['App\\'] ?? 'NO ENCONTRADO');
echo '</pre>';

// Verificar si el archivo existe físicamente
$file = '/home2/androsnet/public_html/api/app/core/Response.php';
echo "Response.php existe: " . (file_exists($file) ? 'SI' : 'NO') . "<br>";

// Intentar cargarlo directamente
require_once $file;
use App\Core\Response;
echo "Response cargado: OK<br>";