# Papelera e historial de productos

## Estructura implementada

| Tabla | Campos | Propósito |
| --- | --- | --- |
| `products` | `deleted_at` nullable, indexado | Borrado suave. El vencimiento se calcula como `deleted_at + 14 días`; no se duplica en otra columna. |
| `activity_logs` | `id`, `user_id` nullable, `action`, `entity_type`, `entity_id`, `created_at` UTC | Identifica el evento y permite consultar el historial de una entidad mediante un índice compuesto. |
| `activity_logs` | `actor` JSON nullable | Copia de nombre, ID y rol del usuario en el momento de la acción. Las tareas automáticas usan `Sistema`. |
| `activity_logs` | `before`, `after` JSON nullable | Copias del estado, precio, tallas, sectores, colores y referencias a imágenes antes y después de la operación. |
| `activity_logs` | `metadata` JSON nullable | Ruta, método, nombres/tamaños/tipos de archivos subidos o motivo de eliminación automática. |
| `pending_image_deletions` | `id`, `path` único, `created_at` | Limpiezas de archivos confirmadas en base de datos, pendientes de ejecutar o reintentar. |

Se reutilizan `product_images`, `product_variants`, `product_sector` y sus claves foráneas existentes. El borrado suave conserva esas relaciones; la eliminación definitiva las elimina por cascada. El historial no tiene una clave foránea hacia el producto: debe sobrevivir a su eliminación. Su relación con el usuario usa `SET NULL`, conservando la copia `actor`.

El SKU fue retirado del esquema en una migración anterior del proyecto. Se guardan `id` y `slug` sin volver a introducirlo. Los registros históricos previos siguen accesibles, pero sus detalles desconocidos no se reconstruyen artificialmente.

## Flujo

1. Crear o editar: bloquear el producto existente, guardar sus relaciones y registrar las copias anterior/posterior en la misma transacción. Una falla del historial revierte la operación y limpia los archivos nuevos.
2. Eliminar: establecer `deleted_at` y registrar `deleted` dentro de una transacción. El catálogo y la edición normal excluyen el producto. Las imágenes se conservan.
3. Recuperar: abrir **Productos → Papelera**, buscar y pulsar **Restaurar producto**. Se bloquea la fila, se comprueba que no se cumplieron 14 días y se registra `restored`. Se recupera la disponibilidad anterior: un producto antes disponible vuelve al catálogo.
4. Vencer: `products:purge-trash` selecciona productos vencidos, vuelve a comprobar el plazo bajo bloqueo y registra `purged`, con actor Sistema. Guarda la limpieza pendiente y elimina la fila y sus relaciones en una única transacción.
5. Limpiar: después de confirmar la transacción se eliminan los archivos locales JPG/WebP. Nunca se borran archivos aún referenciados por otros productos, incluso en papelera. No se realizan peticiones de borrado a servidores externos. Los fallos quedan en `pending_image_deletions` para el siguiente intento y producen salida de error y un registro en el log de Laravel.

La restauración y la purga bloquean la misma fila para resolver operaciones concurrentes. El límite de recuperación es exactamente 14 días; el proceso automático revisa cada minuto, así que la eliminación física ocurre en la siguiente ejecución disponible. Un servidor apagado, un scheduler detenido o un error de disco retrasan la eliminación física, pero no amplían el plazo para restaurar.

## Historial y permisos

- Administradores y publicadores activos pueden eliminar y recuperar productos, conforme al permiso existente para gestionar el catálogo.
- Solo los administradores activos acceden a **Actividad → Ver detalle**.
- Los productos, sectores y activaciones de cuentas tienen eventos de dominio. El middleware añade un evento para las demás solicitudes web autenticadas exitosas, incluidas consultas, inicio y cierre de sesión, sin duplicar esos eventos de dominio.
- No se registran cuerpos completos de solicitudes, contraseñas, cookies ni tokens. Las solicitudes anónimas, los errores de validación y los accesos rechazados no forman parte de este historial administrativo. Las operaciones directas en SQL y las herramientas externas al flujo web tampoco se interceptan.
- Las fechas se almacenan en UTC y se muestran con segundos y zona de Ciudad de México. El historial conserva metadatos de archivos; no conserva los archivos binarios después de la eliminación definitiva.
- No hay rutas de edición o eliminación de eventos. Esto no sustituye un archivo de auditoría externo e inmutable frente a administradores de la base de datos.

## Instalación y operación

```sh
php artisan migrate --force
php artisan schedule:list
```

Laravel tiene programado `products:purge-trash` cada minuto, con `withoutOverlapping`. El servidor debe invocar `php artisan schedule:run` cada minuto. En Linux, configurar el cron con rutas reales:

```cron
* * * * * cd /ruta/NevaMedic && /ruta/php artisan schedule:run >> /ruta/NevaMedic/storage/logs/scheduler.log 2>&1
```

En Windows/XAMPP, configurar la acción del Programador de tareas con el lanzador oculto:

- Programa: `C:\Windows\System32\wscript.exe`.
- Argumentos: `//B //NoLogo "C:\xampp\htdocs\NevaMedic\scripts\run-scheduler-hidden.vbs"`.
- Iniciar en: `C:\xampp\htdocs\NevaMedic`.
- Repetición: cada minuto; no iniciar otra instancia si la anterior sigue ejecutándose.

El lanzador usa `WScript.Shell.Run` con estilo `0` para crear la consola de PHP oculta desde el inicio; los procesos hijos de Laravel la heredan. Espera a que termine PHP y devuelve su código de salida al Programador. `//B` evita diálogos de Windows Script Host. No usar `powershell.exe -WindowStyle Hidden` como acción: Windows puede abrir Terminal antes de que PowerShell oculte su consola. Tampoco basta `php-win.exe` directamente: el proceso hijo del scheduler puede crear otra consola. `scripts/run-scheduler.ps1` queda como ayudante manual desde una consola existente. La tarea local requiere que Windows esté encendido y que la cuenta configurada tenga una sesión iniciada; en producción se necesita una tarea de servicio o cron que no dependa de una sesión interactiva.

La tarea local se llama `NevaMedic-Laravel-Scheduler`, con repetición cada minuto y sin elevar privilegios. La ejecución oculta no muestra errores en pantalla: revisar `storage/logs/laravel.log` y `LastTaskResult` en el Programador de tareas. MySQL debe estar iniciado para que funcionen los bloqueos y la limpieza; si está apagado, Laravel devuelve un error de conexión. Esta configuración pertenece a Windows y no se transfiere al desplegar el repositorio en otro servidor.

Para una ejecución manual de mantenimiento (elimina definitivamente productos ya vencidos):

```sh
php artisan products:purge-trash
```

Los productos que ya estaban archivados antes de esta mejora usan su `deleted_at` original: si llevan 14 días o más, son elegibles en la primera ejecución. La purga no borra el historial. Vigilar errores de Laravel y el resultado del scheduler, y definir una política independiente para el crecimiento y respaldo del historial.

## Verificación

`php artisan test --filter=ProductTrashTest` cubre recuperación y conservación de relaciones, límite exacto de 14 días, autorización, conservación de copias históricas, limpieza de JPG/WebP y archivos compartidos, reintentos, rollback ante fallas de auditoría y omisión de secretos.
