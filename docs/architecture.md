# Arquitectura MVC de Neva Medic

La aplicación usa Laravel 12, Eloquent, Blade, Tailwind CSS y Alpine.js.
El sitio público y la administración son páginas independientes. El diseño de la
landing V2 orienta los colores y la tipografía; no obliga a reunir el sistema en
una sola pantalla.

## Flujo de una solicitud

Ruta → middleware → controlador → modelo o servicio → vista Blade.
Los Form Requests autorizan y validan escrituras antes del controlador.
ProductService coordina la transacción entre producto, sectores, imágenes, colores y bitácora.
No se añade una capa de repositorios que duplique Eloquent.

## Organización

- app/Models: Product, Sector, ProductVariant, ProductImage, ProductDailyMetric,
  ActivityLog y User.
- app/Http/Controllers/Site: inicio, catálogo, ficha, personalización, contacto,
  catálogos y aviso de privacidad.
- app/Http/Controllers/Admin: resumen, productos, sectores, cuentas y reportes.
- app/Http/Controllers/Auth: autenticación Breeze existente.
- app/Http/Requests/Admin: validación y autorización de productos y sectores.
- app/Http/Middleware/EnsureAdministrator: exige cuenta administrativa activa.
- app/Http/Middleware/EnsureCatalogAccess: permite administradores y publicadores activos.
- app/Services/Catalog/ProductService: operaciones transaccionales del catálogo.
- resources/views/layouts: layouts público, administrativo y de autenticación.
- resources/views/site y resources/views/admin: vistas por área y recurso.
- resources/views/components/catalog y components/ui: piezas reutilizables.
- routes/web.php: rutas públicas y perfil; routes/admin.php: rutas privadas.
- config/neva.php: identidad y canal de contacto.
- database/migrations: esquema versionado, compatible con MySQL y SQLite.
- tests/Feature/CatalogArchitectureTest.php: flujo, permisos y aislamiento público.

## Relaciones y convenciones

Producto ↔ Sector es muchos a muchos mediante product_sector.
Producto tiene variantes, imágenes y métricas diarias. Una imagen puede asociarse
a una variante de color. La validación exige que los IDs de colores y fotografías
pertenezcan al producto editado. Cada color necesita una fotografía.
La moneda inicial es MXN y el precio es decimal, no un cálculo con flotantes.
Los productos se archivan con SoftDeletes. La disponibilidad es el único control
de visibilidad: is_available=true muestra el producto; false lo oculta de inicio,
listado, conteos y ficha pública. La columna heredada is_published se sincroniza
al guardar por compatibilidad, pero no decide la visibilidad ni aparece en el formulario.
Las fichas públicas resuelven por slug; administración
resuelve por id, para permitir cambiar slugs sin romper rutas de edición.
Las métricas tienen una fila única por producto y fecha, sin identificar visitantes.

## Estado implementado

- Navegación y vistas adaptables a móvil y escritorio.
- Buscador de categorías en el formulario, con selección mediante ratón o teclado.
- Tallas predefinidas en config/neva.php: el formulario selecciona existencias y
  products.available_sizes las guarda como una lista JSON. Se pueden marcar o
  desmarcar todas; la ficha solo muestra las seleccionadas. Son tallas generales
  del producto, no cantidades de inventario ni existencias por combinación de color.
  Los productos existentes comienzan sin tallas asignadas para no inventar existencias.
- Cuidados es opcional. El menú incluye iconos y el cierre de sesión está separado
  en la esquina superior derecha del contenido, tanto para admin como publicador.
- Listado público, filtros, paginación, destacados y ficha.
- Menú Productos por sectores activos y tipos de prenda, con filtro combinado.
  Los tipos y su agrupación inicial están en config/neva.php; el formulario de
  producto guarda product_type. Los productos anteriores conservan su contenido
  y pueden clasificarse desde edición. Los sectores nuevos tienen enlace propio;
  sus tipos se agregan en sector_types de la configuración.
- Registro público deshabilitado.
- Inicio de sesión para administradores y publicadores activos.
- El publicador accede únicamente a productos y su formulario. Las rutas de
  cuentas, sectores, estadísticas, actividad, resumen y perfil requieren administrador.
- Cuentas sin eliminación: solo el administrador desactiva o reactiva cuentas.
  No puede desactivarse a sí mismo. Se revocan sesiones en base de datos y el
  token de recordar sesión; los middleware también rechazan cuentas inactivas.
- CRUD de productos con archivado, sectores múltiples y vista previa privada.
- Creación y edición de sectores; desactivar conserva sus relaciones.
- Bitácora transaccional de modificaciones de productos.
- Imagen principal desde archivo WebP (máximo 2 MB) o enlace HTTPS externo.
  Los archivos se guardan en storage/app/public/products; product_images guarda
  la ruta o external_url. Los enlaces de Drive se adaptan para previsualización
  y requieren acceso público. El servidor no descarga las imágenes externas.
  El formulario muestra una tarjeta en vivo y permite conservar o reemplazar
  la imagen; el reemplazo elimina el archivo anterior después de guardar.
- Listado de métricas existentes, sin generar cifras de muestra.
- Miniaturas junto a los productos en administración.
- Galería adicional y colores editables en el mismo formulario. Desactivar
  has_variants conserva los colores; quitar una fila al guardar sí elimina ese
  color y su foto. Al seleccionar un color cambia la imagen de la ficha.
- Contacto incluye mapa general de Torreón, sin señalar un establecimiento.

## Correspondencia del formulario con la base de datos

- products: name, slug, product_type, short_description, description, material,
  care_instructions, reference_price, is_available, is_featured, has_variants, available_sizes.
  SKU se eliminó mediante migración. currency se fija en MXN.
- Sectores: IDs relacionados en product_sector, no texto duplicado en products.
- Fotos: product_images.path o external_url, is_primary para portada y
  product_variant_id para la imagen vinculada al color. Nunca se guardan binarios.
- Colores: product_variants.color y color_hex. size y design son columnas
  heredadas sin uso en el formulario de variantes; se guardan vacías.
- Cuentas: users.role admite admin y publisher; is_active controla acceso.
  is_admin se conserva para compatibilidad con cuentas y pruebas anteriores.
  Los campos de rol y actividad no son asignables desde el formulario de perfil.
- image_source, gallery_files, gallery_urls y remove_images son instrucciones
  de formulario que el servicio traduce a registros y archivos; no requieren
  columnas con esos nombres.

## Extensiones pendientes

La ordenación manual de la galería sigue pendiente.
La página de catálogos todavía no genera PDF ni ofrece descargas simuladas.
La captura de vistas y clics sigue pendiente; debe ser agregada, atómica y con
limitación de abuso. El aviso de privacidad requiere contenido final del cliente.
La bitácora actual cubre productos y activación de cuentas; ampliar a sectores y accesos cuando se
implemente esa parte del alcance.
El contacto usa NEVA_WHATSAPP solo cuando tiene formato internacional válido.
Sin configuración muestra un estado pendiente y no enlaza a un número inventado.

## Preparación local

Con MySQL de XAMPP encendido y la conexión .env configurada:

    php artisan migrate
    php artisan db:seed --class=SectorSeeder
    php artisan neva:admin correo-del-administrador@dominio.com
    npm run build
    php artisan serve

El comando neva:admin habilita una cuenta existente o solicita nombre y contraseña
para crearla. Se permiten varios administradores. Para crear ambos roles:

    php artisan neva:account correo@dominio.com admin --name="Nombre"
    php artisan neva:account correo@dominio.com publisher --name="Nombre"

Solicita contraseña oculta. --generate-password genera una contraseña y la muestra
una sola vez. Las cuentas existentes no cambian sin --reset-password.

La precarga opcional se ejecuta con php artisan db:seed --class=DemoProductSeeder.
Crea diez productos marcados Demo y veinte ilustraciones WebP originales, con
precios de ejemplo. No reemplaza ni duplica productos existentes. Los archivos
fuente están en database/demo-images y el generador en scripts/generate_demo_assets.py.

En cada instalación ejecutar php artisan storage:link para servir fotografías
públicas. No almacenar archivos subidos directamente bajo public.

Para probar:

    php artisan config:clear
    php artisan test

Las pruebas usan SQLite en memoria configurado en phpunit.xml. No deben apuntar a
la base de desarrollo. Nunca usar migrate:fresh sobre datos del cliente.

## Crecimiento

Agregar controladores y Form Requests por recurso (variantes, imágenes, PDFs).
Extraer servicios cuando exista una operación de negocio que coordine modelos,
archivos o trabajos de cola. Incorporar Jobs para catálogos pesados o
procesamiento de imágenes cuando haga falta, sin introducir hoy colas vacías ni
dependencias no usadas. Los dos roles actuales se autorizan en middleware y
Form Requests. Añadir Policies si se requiere que cada publicador solo modifique
sus propios productos; actualmente comparten el catálogo.
