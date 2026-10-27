<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo "Crear administrador de Halloween (la contraseña será visible en esta consola).\nUsuario: ";
$name = trim(fgets(STDIN));
echo "Contraseña (8 a 72 bytes): "; $pass = rtrim(fgets(STDIN), "\r\n");
if (!preg_match('/^[\p{L}\p{N}_ .-]{3,50}$/u', $name) || strlen($pass)<8 || strlen($pass)>72) { fwrite(STDERR,"Datos inválidos.\n"); exit(1); }
try {
    $c = require __DIR__.'/../config/config.php';
    $db = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $q=$db->prepare("INSERT INTO usuarios(nombre,clave,rol) VALUES (?,?,'admin')");
    $q->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]);
    echo "Administrador creado. Iniciá sesión desde login.html.\n";
} catch (PDOException $e) { fwrite(STDERR,"No se creó la cuenta. Verificá la conexión y que el usuario no exista.\n"); exit(1); }
