<?php

require __DIR__ . '/vendor/autoload.php';

use App\Core\Response;

$response = new Response([
    "status" => "ok",
    "mensaje" => "API funcionando"
]);

$response->send();