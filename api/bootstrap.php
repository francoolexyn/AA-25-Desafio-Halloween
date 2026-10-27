<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_name('halloween_session');
session_set_cookie_params([
    'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' blob:; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = require __DIR__ . '/../config/config.php';
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4", $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function fail(string $message, int $status = 400): never { respond(['error' => $message], $status); }
function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $q = db()->prepare('SELECT id, nombre, rol FROM usuarios WHERE id = ?');
    $q->execute([$_SESSION['uid']]);
    return $q->fetch() ?: null;
}
function require_user(bool $admin = false): array {
    $u = current_user();
    if (!$u) fail('Iniciá sesión para continuar.', 401);
    if ($admin && $u['rol'] !== 'admin') fail('Se requiere una cuenta administradora.', 403);
    return $u;
}
function csrf(): void {
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Sesión vencida. Recargá la página.', 403);
}
function fields(array $data): array {
    $name = trim(is_string($data['nombre'] ?? null) ? $data['nombre'] : '');
    $description = trim(is_string($data['descripcion'] ?? null) ? $data['descripcion'] : '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 50) fail('El nombre debe tener entre 2 y 50 caracteres.');
    if (mb_strlen($description) < 5 || mb_strlen($description) > 3000) fail('La descripción debe tener entre 5 y 3000 caracteres.');
    return [$name, $description];
}
function photo(): ?array {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES['foto'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('No se pudo cargar la imagen. Revisá el límite de PHP.');
    if ($f['size'] > 2 * 1024 * 1024) fail('La imagen debe pesar como máximo 2 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensions = @getimagesize($f['tmp_name']);
    if (!isset($types[$mime]) || !$dimensions || $dimensions[0] > 6000 || $dimensions[1] > 6000) fail('Usá una imagen JPG, PNG o WebP de hasta 6000 × 6000 píxeles.');
    return [bin2hex(random_bytes(8)) . '.' . $types[$mime], file_get_contents($f['tmp_name'])];
}
