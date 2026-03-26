<?php
// BORRAR DESPUÉS DE USAR
$composerPhar = __DIR__ . '/composer.phar';

if (!file_exists($composerPhar)) {
    file_put_contents(
        $composerPhar,
        file_get_contents('https://getcomposer.org/composer-stable.phar')
    );
}

putenv('HOME=' . __DIR__);
putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');

$output = [];
$return = 0;
exec(
    'HOME=' . __DIR__ . ' COMPOSER_HOME=' . __DIR__ . '/.composer php ' . $composerPhar . ' dump-autoload --optimize --working-dir=' . __DIR__ . ' 2>&1',
    $output,
    $return
);

echo '<pre>';
echo 'Return code: ' . $return . "\n";
echo implode("\n", $output);
echo '</pre>';