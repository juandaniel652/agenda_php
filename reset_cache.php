<?php

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache limpiado";
} else {
    echo "OPcache no activo";
}