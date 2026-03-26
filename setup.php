<?php
// BORRAR DESPUÉS DE USAR
$composerPhar = __DIR__ . '/composer.phar';

// Descargar composer.phar
file_put_contents(
    $composerPhar,
    file_get_contents('https://getcomposer.org/composer-stable.phar')
);

// Ejecutar composer dump-autoload
$output = [];
$return = 0;
exec('php ' . $composerPhar . ' dump-autoload --optimize --working-dir=' . __DIR__ . ' 2>&1', $output, $return);

echo '<pre>';
echo 'Return code: ' . $return . "\n";
echo implode("\n", $output);
echo '</pre>';