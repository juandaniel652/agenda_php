<?php
require __DIR__ . '/vendor/autoload.php';

use App\Core\Response;

echo json_encode(['test' => 'autoload ok']);

$r = new Response();
echo json_encode(['class' => 'Response ok']);