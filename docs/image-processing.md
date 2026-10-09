# Imágenes de productos: WebP para web y JPEG para PDF

Implementado en Laravel 12, Intervention Image 3.11.9, integración Laravel 1.5.9,
driver GD y Dompdf 3.1.6. Se conserva MVC: el controlador existente delega
en ProductService y este utiliza ImageProcessor para principal, galería y colores.

## Datos y archivos

No se necesitan nuevas columnas ni migraciones de esquema. `product_images.path`
guarda `products/UUID`, sin extensión. `external_url` queda en NULL después de
convertir; las fuentes heredadas que fallen permanecen intactas para reintentar.
Nunca se almacenan binarios ni Base64 en la BD.

Por imagen se guardan exclusivamente `products/UUID.webp` y `products/UUID.jpg`
en el disco public. Ambos usan calidad 80 y ancho máximo 1000 px, manteniendo la
proporción y sin ampliar. GD usa fondo blanco al exportar JPEG con transparencia.
Se aceptan JPG/JPEG, PNG y WebP, máximo 10 MB; también se limita a 20 megapíxeles
para evitar agotar memoria al descomprimir archivos pequeños de dimensiones enormes.

El reemplazo limpia ambas versiones anteriores después de confirmar la BD,
si ninguna otra imagen utiliza esa ruta. Los archivos nuevos se eliminan si falla
la escritura de la segunda versión o la transacción. Los productos archivados
conservan sus imágenes porque el archivado es reversible.

Los originales importados no se conservan en el disco public una vez que dejan
de estar referenciados. Las ilustraciones fuente de database/demo-images son
recursos del seeder, no duplicados creados al generar PDFs.

## URLs y errores

RemoteImageDownloader acepta HTTP/HTTPS públicos, puertos 80/443 y sin credenciales.
Valida todas las IPs DNS, rechaza redes privadas/reservadas, fija la IP de conexión
con CURLOPT_RESOLVE y desactiva proxies de entorno. Revalida cada redirección
(máximo cuatro) para impedir SSRF y DNS rebinding.
Cada petición tiene timeout de 10 segundos y conexión de 5 segundos. No realiza
reintentos automáticos. Una cadena de redirecciones puede sumar varios timeouts.
Comprueba estado 2xx, Content-Type image/, tamaño durante la transferencia y
formato real antes de decodificar. La descarga usa un temporal eliminado al cerrar.
Un enlace de Drive que devuelva HTML, requiera login o responda 403 se rechaza;
un User-Agent no garantiza acceso a recursos privados.

El formulario muestra el error y no guarda cambios parciales. Los fallos de
procesamiento, migración, lectura PDF y limpieza se registran con Log::warning,
producto/imagen cuando corresponda y causa. Consultar storage/logs/laravel.log
o el canal configurado en Laravel.

## Dompdf

CatalogImage resuelve `public_path('storage/'.$image->path.'.jpg')` y comprueba
existencia, lectura, carpeta y formato JPEG. La plantilla existente recibe esa
ruta absoluta en `$photos`; si es NULL muestra el placeholder existente.
CatalogPdf permite public y storage/app/public mediante chroot, con red remota
desactivada. Generar el PDF no invoca el procesador ni realiza descargas.
El QR continúa embebido en el documento; tampoco se guarda en la BD.

## Instalación y ejecución

Desde la raíz del proyecto, con GD, cURL y MySQL activos:

```powershell
composer install
php artisan config:clear
php artisan storage:link
php artisan images:convert
npm run build
```

Si storage:link indica que el enlace ya existe, no es necesario recrearlo si
apunta correctamente a storage/app/public. Para esta instalación ya se verificó.
Si npm falla por la configuración local de NVM, la compilación equivalente es:

```powershell
node node_modules/vite/bin/vite.js build
```

El comando procesa por ID, en lotes de lectura de 50 registros. Cada fila se
confirma individualmente. No requiere cursor: al repetirlo omite las rutas base
que ya tienen las dos versiones. Si falta una versión, reconstruye el par usando
la restante. Si faltan ambas y ya no existe el original, informa el fallo y debe
subirse de nuevo la fotografía. Un fallo no detiene los otros registros; el
código de salida es 1 si hubo errores y 0 si todos se procesaron/omitieron.

```powershell
# Limitar una ejecución a 20 conversiones o intentos fallidos
php artisan images:convert --limit=20
# Procesar solo las imágenes de un producto
php artisan images:convert --product=12
# Reanudar después de una interrupción
php artisan images:convert
```

Se usa el mismo bloqueo del producto que en edición para no sobrescribir una
imagen recién reemplazada. La ruta se actualiza únicamente tras guardar ambos
archivos. Un cierre forzado del proceso justo después de escribir y antes de
confirmar puede dejar archivos huérfanos; la limpieza por excepciones no puede
ejecutarse cuando se apaga la computadora. El comando no elimina indiscriminadamente
archivos no referenciados porque podrían pertenecer a una operación en curso.

PHP local tiene upload_max_filesize=40M, post_max_size=40M y max_file_uploads=20.
El límite de la aplicación es 10 MB por foto, pero un formulario con varias fotos
tiene además el límite total de PHP. Para cargas grandes, dividirlas entre varias
ediciones o ajustar esos límites en el servidor de destino y reiniciar PHP.

## Validación realizada

- Formatos reales, tamaño, dimensiones, dos archivos, reemplazo y rollback.
- URLs privadas, IPv6, DNS mixto, redirecciones internas, HTML, 403 y conexión fallida.
- Conversión reanudable, rutas compartidas y reparación de una versión ausente.
- PDF con JPEG local, sin llamadas HTTP; archivo ausente produce placeholder.
- Seeder de demostración genera directamente los pares y sigue siendo idempotente.

```powershell
php artisan test
```

En la base local se convirtieron 33 imágenes con cero fallos. La segunda ejecución
omitió las 33 sin generar nuevas versiones. Se revisaron visualmente las seis
páginas del PDF industrial: las cuatro fotografías, portada y QR se muestran.
El PDF de prueba contiene URLs localhost; al publicar debe configurarse APP_URL
con el dominio público real antes de generar documentos para compartir.

Referencias: [Intervention v3](https://image.intervention.io/v3/getting-started/frameworks),
[scaleDown](https://image.intervention.io/v3/modifying-images/resizing),
[Dompdf](https://github.com/dompdf/dompdf#resource-reference-requirements).
