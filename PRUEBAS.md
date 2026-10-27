# Verificación

Fecha: 27 de octubre de 2026.

Se ejecutó el proyecto con PHP 8.2.12 y MariaDB 10.4.32 de la instalación de XAMPP. Para no modificar bases del usuario se usó una instancia aislada de MariaDB en el puerto 3308 y el servidor integrado PHP en el 8088. El ZIP usa el puerto normal 3306. Los datos, usuarios y registros generados por las pruebas no forman parte de la entrega.

## Resultado automatizado

**34 comprobaciones HTTP y SQL correctas**, además de sintaxis PHP de todos los archivos y sintaxis JavaScript:

- Galería pública con ejemplos; protección frente a votos anónimos y peticiones sin CSRF.
- Registro válido, rechazo de usuario duplicado sin distinguir mayúsculas y contraseña corta.
- Login correcto/incorrecto, almacenamiento de hash y cierre de sesión efectivo.
- Rechazo del CRUD y del panel admin para usuarios normales; el parámetro `rol` del registro no otorga privilegios.
- Voto válido, rechazo del duplicado, voto a otro disfraz y coincidencia entre contadores e historial.
- Creación del administrador por CLI e ingreso al panel.
- Validación, alta con foto PNG, lectura íntegra del BLOB, detalle individual, edición JSON y edición multipart con reemplazo de foto.
- Conservación de la foto cuando se edita sin adjuntar otra y rechazo de un archivo PHP con tipo declarado como imagen.
- Baja lógica, ocultamiento del disfraz y su foto, y rechazo de votos sobre un disfraz eliminado.

Se revisó también la carga de la galería y el ingreso al panel en navegador. Además se inició Apache de XAMPP con una configuración aislada en el puerto 8089: la portada y la API devolvieron 200, los accesos HTTP a configuración/SQL/scripts/README devolvieron 403 y el panel sin sesión redirigió a login con 302.

Una prueba adicional sobre Apache envió **ocho votos concurrentes desde ocho sesiones del mismo usuario** al mismo disfraz: exactamente uno devolvió 201 y los otros siete devolvieron 409. Esto comprueba que la protección no depende de deshabilitar el botón en la interfaz.

## Recorrido manual de evaluación

1. Seguí README.md en una instalación nueva.
2. Abrí la galería sin sesión; verificá los tres ejemplos y contadores en cero.
3. Creá una cuenta, ingresá, votá y recargá: el botón debe mostrar “Votado”.
4. Probá otro disfraz: se admite una vez. Intentar repetir el primer voto por API debe devolver 409.
5. Con una cuenta normal, abrí `admin.php`: debe devolver 403.
6. Ingresá con la cuenta creada por CLI. Creá un disfraz con imagen, editalo y eliminálo. Verificá cada cambio en la galería.
7. Cerrá sesión; no debe ser posible votar ni acceder al panel.
8. Reducí el ancho del navegador: la galería debe pasar de tres a dos y una columna; los formularios quedan en una columna en pantallas pequeñas.
