<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '') === 'PUT') $method = 'PUT';
    $route = trim($_GET['ruta'] ?? '', '/');
    $parts = explode('/', $route);
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : 0;
    if ($method === 'GET' && $route === 'sesion') {
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        respond(['usuario' => current_user(), 'csrf' => $_SESSION['csrf']]);
    }
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) fail('Método no permitido.', 405);
    if ($method !== 'GET') csrf();
    $input = $_POST;
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        try { $input = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { fail('JSON inválido.'); }
        if (!is_array($input)) fail('Se esperaba un objeto JSON.');
    }
    if ($method === 'POST' && in_array($route, ['registro', 'login'], true)) {
        $name = trim(is_string($input['nombre'] ?? null) ? $input['nombre'] : '');
        $pass = is_string($input['clave'] ?? null) ? $input['clave'] : '';
        if (!preg_match('/^[\p{L}\p{N}_ .-]{3,50}$/u', $name)) fail('El usuario debe tener entre 3 y 50 caracteres: letras, números, espacios, guion o punto.');
        if (strlen($pass) < 8 || strlen($pass) > 72) fail('La contraseña debe tener entre 8 y 72 bytes.');
        if ($route === 'registro') {
            try {
                $q = db()->prepare('INSERT INTO usuarios (nombre, clave) VALUES (?, ?)');
                $q->execute([$name, password_hash($pass, PASSWORD_DEFAULT)]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') fail('Ese nombre de usuario ya está registrado.', 409);
                throw $e;
            }
            respond(['mensaje' => 'Cuenta creada. Ya podés iniciar sesión.'], 201);
        }
        // Límite por sesión para el entorno académico local; ver README para producción.
        $attempts = array_values(array_filter($_SESSION['attempts'] ?? [], fn($t) => $t > time() - 300));
        if (count($attempts) >= 10) fail('Demasiados intentos. Esperá cinco minutos.', 429);
        $attempts[] = time(); $_SESSION['attempts'] = $attempts;
        $q = db()->prepare('SELECT id, clave FROM usuarios WHERE nombre = ?');
        $q->execute([$name]); $u = $q->fetch();
        if (!$u || !password_verify($pass, $u['clave'])) fail('Usuario o contraseña incorrectos.', 401);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id']; unset($_SESSION['attempts']);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        respond(['usuario' => current_user(), 'csrf' => $_SESSION['csrf']]);
    }
    if ($route === 'logout' && $method === 'POST') {
        $_SESSION = []; session_destroy();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        respond(['mensaje' => 'Sesión cerrada.']);
    }
    if ($route === 'disfraces' && $method === 'GET') {
        $u = current_user();
        $q = db()->prepare('SELECT d.id, d.nombre, d.descripcion, d.votos, d.foto, EXISTS(SELECT 1 FROM votos v WHERE v.id_disfraz=d.id AND v.id_usuario=?) AS votado FROM disfraces d WHERE d.eliminado=0 ORDER BY d.votos DESC, d.id ASC');
        $q->execute([$u['id'] ?? 0]);
        respond(['disfraces' => $q->fetchAll()]);
    }
    if (count($parts) === 3 && $parts[0] === 'disfraces' && $id && $parts[2] === 'foto' && $method === 'GET') {
        $q = db()->prepare('SELECT foto, foto_blob FROM disfraces WHERE id=? AND eliminado=0');
        $q->execute([$id]); $d = $q->fetch();
        if (!$d || !$d['foto']) fail('Imagen no encontrada.', 404);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($d['foto_blob']);
        header('Content-Type: ' . $mime); echo $d['foto_blob']; exit;
    }
    if (count($parts) === 3 && $parts[0] === 'disfraces' && $id && $parts[2] === 'votos' && $method === 'POST') {
        $u = require_user(); $pdo = db(); $pdo->beginTransaction();
        try {
            // Serializa votos y eliminación del mismo disfraz; UNIQUE impide duplicados.
            $q = $pdo->prepare('SELECT id FROM disfraces WHERE id=? AND eliminado=0 FOR UPDATE');
            $q->execute([$id]);
            if (!$q->fetch()) { $pdo->rollBack(); fail('Disfraz no encontrado.', 404); }
            $q = $pdo->prepare('INSERT INTO votos (id_usuario,id_disfraz) VALUES (?,?)');
            $q->execute([$u['id'], $id]);
            $q = $pdo->prepare('UPDATE disfraces SET votos=votos+1 WHERE id=?'); $q->execute([$id]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') fail('Ya votaste por este disfraz.', 409);
            throw $e;
        }
        respond(['mensaje' => '¡Voto registrado!'], 201);
    }
    if ($parts[0] === 'disfraces' && (($route === 'disfraces' && $method === 'POST') || (count($parts) === 2 && $id && in_array($method, ['GET','PUT','DELETE'], true)))) {
        require_user(true);
        if ($method === 'POST') {
            [$name, $description] = fields($input); $photo = photo() ?? ['', ''];
            $q = db()->prepare('INSERT INTO disfraces (nombre,descripcion,foto,foto_blob) VALUES (?,?,?,?)');
            $q->execute([$name,$description,$photo[0],$photo[1]]);
            respond(['id' => (int)db()->lastInsertId(), 'mensaje' => 'Disfraz creado.'], 201);
        }
        $q = db()->prepare('SELECT id,nombre,descripcion,votos,foto FROM disfraces WHERE id=? AND eliminado=0');
        $q->execute([$id]); $d = $q->fetch();
        if (!$d) fail('Disfraz no encontrado.', 404);
        if ($method === 'GET') respond(['disfraz' => $d]);
        if ($method === 'DELETE') {
            $q = db()->prepare('UPDATE disfraces SET eliminado=1 WHERE id=?'); $q->execute([$id]);
            respond(['mensaje' => 'Disfraz eliminado.']);
        }
        [$name,$description] = fields($input); $photo = photo();
        if ($photo) {
            $q = db()->prepare('UPDATE disfraces SET nombre=?,descripcion=?,foto=?,foto_blob=? WHERE id=? AND eliminado=0');
            $q->execute([$name,$description,$photo[0],$photo[1],$id]);
        } else {
            $q = db()->prepare('UPDATE disfraces SET nombre=?,descripcion=? WHERE id=? AND eliminado=0'); $q->execute([$name,$description,$id]);
        }
        respond(['mensaje' => 'Disfraz actualizado.']);
    }
    fail('Recurso o método no encontrado.', 404);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log((string)$e);
    fail('No se pudo completar la operación. Revisá MySQL, la importación SQL y config/config.php.', 500);
}
