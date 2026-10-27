# AA 25 - Desafio Halloween

Aplicación académica ejecutable en XAMPP: PHP 8.1 o superior, MySQL 8 o MariaDB 10.4, HTML, CSS y JavaScript. Sin Composer, npm, CDN ni compilación. Probada con PHP 8.2.12 y MariaDB 10.4.32 de XAMPP.

## Instalación en Windows / XAMPP

1. Descomprimí y copiá **la carpeta `halloween`** dentro de `C:\xampp\htdocs\`. Debe existir `C:\xampp\htdocs\halloween\index.html`.
2. En el panel de XAMPP iniciá **Apache** y **MySQL**.
3. Abrí `http://localhost/phpmyadmin`, entrá en **Importar**, seleccioná `database/halloween.sql` y ejecutá la importación. El archivo crea la base `halloween`, las tres tablas y tres disfraces de ejemplo. Importalo una sola vez en una instalación nueva.
4. Revisá `config/config.php`. Está preparado para XAMPP local: host `127.0.0.1`, puerto `3306`, usuario `root`, contraseña vacía. Si tu instalación usa otras credenciales, creá `config/config.local.php` devolviendo un array con los valores necesarios, por ejemplo `<?php return ['password' => 'tu-clave'];`. Ese archivo está excluido de Git para evitar publicar credenciales.
5. Abrí una terminal **CMD** y ejecutá:

   ```bat
   cd /d C:\xampp\htdocs\halloween
   C:\xampp\php\php.exe scripts\crear-admin.php
   ```

   Elegí un nombre de administrador nuevo y una contraseña. La contraseña se muestra mientras la escribís en esta consola local; el script la guarda únicamente como hash. Si ya existe ese usuario, elegí otro. El script solo crea cuentas: no promueve ni modifica usuarios existentes. No hay una contraseña administrativa predeterminada.
6. Visitá `http://localhost/halloween/`. Para administrar, ingresá en `login.html` con la cuenta creada en el paso anterior. Para votar, creá una cuenta normal en `registro.html`.

No abras `index.html` con doble clic: necesita servirse por HTTP con PHP. Si Apache usa otro puerto, agregalo a la URL (por ejemplo, `http://localhost:8080/halloween/`).

## Uso

- La galería pública muestra disfraces activos, fotos, descripción y votos, ordenados por mayor cantidad de votos.
- Cada cuenta puede votar **una vez por cada disfraz** y apoyar varios disfraces. La interfaz marca los ya votados; MySQL garantiza la unicidad. No hay retiro de votos.
- Solo un administrador puede crear, consultar individualmente, editar y eliminar disfraces. Los usuarios normales participan mediante sus votos.
- El administrador puede adjuntar una foto al crear o editar: JPG, PNG o WebP de hasta 2 MB y 6000 × 6000 píxeles. Si no adjunta foto al editar, se conserva la anterior. Las fotos se guardan dentro de MySQL, sin archivos ejecutables subidos al servidor.
- Eliminar es una baja lógica: `eliminado=1`. El disfraz y su foto dejan de estar disponibles públicamente; se mantiene el historial.
- Los tres ejemplos iniciales muestran un emoji cuando no tienen foto.

## Estructura

```text
halloween/
├── index.html              Galería pública
├── login.html              Inicio de sesión
├── registro.html           Registro
├── admin.php               Panel protegido en servidor
├── assets/app.js           Cliente fetch de la API
├── assets/style.css        Diseño adaptable
├── api/index.php           Rutas REST
├── api/bootstrap.php       Sesión, PDO, validación y utilidades
├── config/config.php       Credenciales locales
├── database/halloween.sql  Esquema y ejemplos
├── scripts/crear-admin.php Alta administrativa solo por CLI
├── PRUEBAS.md              Verificación realizada y prueba manual
└── README.md
```

Los archivos `.htaccess` evitan listar directorios y bloquean acceso HTTP a configuración, SQL y scripts bajo Apache. La aplicación no depende de `mod_rewrite` ni de rutas virtuales.

## API REST

Base: `http://localhost/halloween/api/index.php?ruta=`. Los identificadores se envían en la ruta, por ejemplo `?ruta=disfraces/1/votos`. Las respuestas son JSON salvo las imágenes.

| Método | ruta | Acceso | Función |
| --- | --- | --- | --- |
| GET | sesion | Público | Devuelve `usuario` y token `csrf` |
| POST | registro | Público + CSRF | Crear usuario normal: `nombre`, `clave` |
| POST | login | Público + CSRF | Autenticar: `nombre`, `clave` |
| POST | logout | CSRF | Cerrar sesión |
| GET | disfraces | Público | Listar activos, votos y estado `votado` |
| GET | disfraces/{id}/foto | Público | Obtener imagen activa |
| POST | disfraces/{id}/votos | Usuario + CSRF | Registrar voto |
| GET | disfraces/{id} | Administrador | Consultar detalle |
| POST | disfraces | Administrador + CSRF | Crear disfraz |
| PUT | disfraces/{id} | Administrador + CSRF | Actualizar disfraz |
| DELETE | disfraces/{id} | Administrador + CSRF | Baja lógica |

Antes de una mutación, solicitar `GET sesion`, conservar la cookie y enviar el token devuelto en la cabecera `X-CSRF-Token`. Un login correcto cambia el token: utilizar el nuevo valor de su respuesta. No se habilita CORS; frontend y API comparten origen.

Crear/editar acepta `nombre`, `descripcion` y opcionalmente el archivo `foto`. Para imágenes usar `multipart/form-data`: en una edición, enviar POST con `X-HTTP-Method-Override: PUT`, porque PHP no procesa automáticamente archivos multipart con PUT. También se admite PUT con JSON para nombre y descripción.

Estados principales: 200 correcto, 201 creado, 400 validación, 401 sin autenticación/credenciales incorrectas, 403 CSRF o permisos, 404 recurso no encontrado, 409 usuario o voto duplicado, 429 intentos de login por sesión, 500 error interno. Los errores devuelven `{"error":"mensaje"}`.

## Cambios necesarios sobre el SQL de la consigna

Se conservaron las tres tablas y las columnas solicitadas. Se agregaron claves primarias con `AUTO_INCREMENT`, índices únicos, claves foráneas e InnoDB. `usuarios.rol` distingue administración; el registro público siempre asigna `usuario`. La restricción `UNIQUE(id_usuario,id_disfraz)` impide votos duplicados. `foto_blob` usa **MEDIUMBLOB** en lugar de BLOB: BLOB admite aproximadamente 64 KB y no alcanza para fotos de 2 MB. `foto` conserva VARCHAR(20), con nombres aleatorios de hasta 20 caracteres. `votos` se actualiza en la misma transacción que el historial; no es editable desde el formulario.

## Seguridad aplicada y alcance

Consultas preparadas PDO sin emulación, hashes con `password_hash` / `password_verify`, regeneración del ID de sesión al ingresar, cookies HttpOnly/SameSite, token CSRF, permisos verificados en servidor, validación de archivos por contenido, cabeceras de seguridad y salida de textos mediante `textContent` para evitar interpretar HTML ingresado por usuarios. El voto bloquea la fila del disfraz durante la transacción y la base de datos aplica la restricción única.

El límite de login es de diez intentos cada cinco minutos **por sesión**. Es una protección básica para la entrega local; al publicarlo se necesita HTTPS, credenciales de base de datos con permisos mínimos y un límite persistente de intentos por cuenta/IP en lugar del actual. XAMPP se utiliza aquí como entorno de desarrollo local.

Referencias oficiales consultadas:

- [Hashes de contraseña en PHP](https://www.php.net/manual/en/book.password.php)
- [Regeneración de sesión](https://www.php.net/manual/en/function.session-regenerate-id.php)
- [Consultas preparadas PDO](https://www.php.net/manual/en/pdo.prepare.php)

## Problemas frecuentes

- **Error de conexión**: iniciá MySQL, importá el SQL y comprobá puerto/usuario/clave en `config/config.php`.
- **Imagen demasiado grande**: además del límite de la aplicación, PHP requiere `upload_max_filesize` de al menos `2M` y `post_max_size` mayor (por ejemplo `8M`). Reiniciá Apache después de cambiar `C:\xampp\php\php.ini`.
- **Usuario existente**: los nombres son únicos sin distinguir mayúsculas; usá otro nombre.
- **Sesión vencida**: recargá la página para obtener un nuevo token.
- **Acceso admin rechazado**: registrarse desde la web no da permisos de administración. Creá la cuenta mediante el script CLI.
- **No se cargan fotos/JSON**: verificá las extensiones `pdo_mysql`, `fileinfo` y `mbstring`, incluidas en el XAMPP usado para probar.
