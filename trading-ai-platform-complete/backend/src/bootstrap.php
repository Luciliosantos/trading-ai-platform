<?php
declare(strict_types=1);

spl_autoload_register(function(string $class) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

require __DIR__ . '/Support/env.php';
