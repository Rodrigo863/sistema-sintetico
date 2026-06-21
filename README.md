# Sistema de Reservas para Cancha Sintetica

Sistema base en PHP + MySQL para administrar alquileres de canchas sinteticas.

## Requisitos
- XAMPP, WAMP o cualquier servidor con PHP y MySQL
- PHP 8+
- MySQL 5.7+

## Pasos
1. Abre phpMyAdmin: `http://localhost/phpmyadmin`.
2. Ejecuta el contenido de `schema.sql` en la pestana SQL.
3. Ajusta las credenciales en `config.php` si tu usuario no es `root` o tu contrasena no esta vacia.
4. Coloca esta carpeta dentro de `htdocs`.
5. Abre el sistema:
   - `http://localhost/proyecto%20sintetico/`

## Acceso inicial
- Usuario: `administrador`
- Contrasena: `admin123`

El sistema crea este usuario automaticamente si no existe. Cambia la contrasena desde la seccion de configuracion despues del primer ingreso.

## Funcionalidades
- Registrar clientes.
- Registrar canchas.
- Crear reservas por fecha y horario.
- Evitar reservas duplicadas en la misma cancha y horario.
- Registrar senas y pagos.
- Ver saldos pendientes.
- Cambiar estado de reservas: reservado, confirmado, finalizado o cancelado.

## Base de datos
La base se llama `sistema_clientes` para mantener compatibilidad con la primera version del proyecto. Las tablas principales son:

- `clientes`
- `canchas`
- `reservas`
- `pagos`
- `usuarios`
