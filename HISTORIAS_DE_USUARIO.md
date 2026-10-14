# Historias de usuario - API Laravel

## HU-01: Inicio de sesión

Como bibliotecario autenticado, quiero iniciar sesión con mi usuario y contraseña para acceder a las operaciones permitidas.

Criterios de aceptación:

- El endpoint valida usuario y contraseña.
- Devuelve un token JWT cuando las credenciales son correctas.
- Rechaza credenciales inválidas con un error 401.

## HU-02: Administración de bibliotecarios

Como administrador, quiero registrar y consultar bibliotecarios para controlar el acceso al sistema.

Criterios de aceptación:

- Solo el rol administrador puede crear bibliotecarios.
- La API valida campos obligatorios y roles válidos.
- El registro queda persistido en la base de datos.

## HU-03: Gestión del catálogo

Como bibliotecario, quiero gestionar libros para mantener actualizado el inventario de la biblioteca.

Criterios de aceptación:

- Se pueden crear, editar y consultar libros.
- Cada libro incluye título, autor, categoría y ejemplares.
- La baja lógica no elimina el registro físico.

## HU-04: Gestión de socios

Como bibliotecario, quiero administrar los socios para registrar usuarios que pueden pedir libros.

Criterios de aceptación:

- Se puede crear, consultar y actualizar un socio.
- Se valida el documento y el estado del socio.
- No se permiten duplicados de identificación.

## HU-05: Préstamos y devoluciones

Como bibliotecario, quiero registrar préstamos y devoluciones para controlar la circulación de ejemplares.

Criterios de aceptación:

- Un préstamo solo se autoriza si hay ejemplares disponibles.
- La devolución actualiza el estado de cada ítem.
- Se detecta una devolución duplicada o préstamos vencidos.

## HU-06: Reporte de libros más prestados

Como administrador, quiero consultar el reporte de libros más prestados para tomar decisiones de compra y promoción.

Criterios de aceptación:

- El servicio agrupa préstamos por libro en un rango de fechas.
- Mantiene el título histórico aunque el libro cambie de nombre.
- Devuelve los resultados ordenados por frecuencia descendente.

## HU-07: Alertas de vencidos

Como bibliotecario, quiero ver los libros vencidos para gestionar recordatorios y sanciones.

Criterios de aceptación:

- La API identifica préstamos cuya fecha de devolución ya pasó.
- El listado incluye datos del miembro y del libro.
- La información se ordena por fecha de vencimiento.
