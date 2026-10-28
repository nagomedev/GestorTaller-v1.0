Documentación rápida.

# Debug Suite - GestorTaller

Conjunto de diagnósticos para revisar conexión, sesión, roles, permisos, clientes, vehículos, órdenes y CSRF.

## Cómo usar

1. Arranca Apache + MySQL (XAMPP/WAMP/LAMP)
2. Abre: `http://localhost/GestorTaller/debug/`
3. Recorre los tests en orden (1 → 6)
4. Usa con sesión iniciada para ver el simulador de permisos

## Archivos

| Archivo             | Descripción                        |
| ------------------- | ---------------------------------- |
| `index.php`         | Menú principal                     |
| `test_conexion.php` | Conexión, BD, tablas, conteos      |
| `test_usuarios.php` | Usuarios, hashes, sesión           |
| `test_permisos.php` | ROLES/MODULOS/PERMISOS + `puede()` |
| `test_clientes.php` | Clientes y vehículos               |
| `test_ordenes.php`  | Órdenes, estados, asignaciones     |
| `test_csrf.php`     | Helpers CSRF                       |

## Seguridad

**IMPORTANTE:** Elimina completamente la carpeta `debug/` en cualquier entorno público/producción. 3) Aplicar los archivos
Voy a crearlos todos de golpe con write.
Ahora ejecuto las creaciones.
Te dejo el resultado una vez creados. Estos tests te van a servir para ver rápido si falla conexión, si los roles están bien, si las tablas existen o si hay algún desajuste con permisos/órdenes sin liarte con el código.
