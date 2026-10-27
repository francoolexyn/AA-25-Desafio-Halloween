<?php
declare(strict_types=1);
// Valores por defecto de XAMPP. Cambiar si MySQL usa otras credenciales.
$defaults = [
    'host' => getenv('HALLOWEEN_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('HALLOWEEN_DB_PORT') ?: '3306',
    'database' => getenv('HALLOWEEN_DB_NAME') ?: 'halloween',
    'username' => getenv('HALLOWEEN_DB_USER') ?: 'root',
    'password' => getenv('HALLOWEEN_DB_PASS') ?: '',
];
$local = __DIR__ . '/config.local.php';
return is_file($local) ? array_replace($defaults, require $local) : $defaults;
