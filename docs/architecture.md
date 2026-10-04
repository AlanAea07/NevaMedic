# Arquitectura MVC de Neva Medic

La aplicación usa Laravel 12, Eloquent, Blade, Tailwind CSS y Alpine.js.
El sitio público y la administración son páginas independientes. El diseño de la
landing V2 orienta los colores y la tipografía; no obliga a reunir el sistema en
una sola pantalla.

## Flujo de una solicitud

Ruta → middleware → controlador → modelo o servicio → vista Blade.
Los Form Requests autorizan y validan escrituras antes del controlador.
ProductService coordina la transacción entre producto, sectores y bitácora.
No se añade una capa de repositorios que duplique Eloquent.

## Organización

- app/Models: Product, Sector, ProductVariant, ProductImage, ProductDailyMetric,
  ActivityLog y User.
- app/Http/Controllers/Site: inicio, catálogo, ficha, personalización, contacto,
  catálogos y aviso de privacidad.
- app/Http/Controllers/Admin: resumen, productos, sectores y reportes.
- app/Http/Controllers/Auth: autenticación Breeze existente.
- app/Http/Requests/Admin: validación y autorización de productos y sectores.
- app/Http/Middleware/EnsureAdministrator: exige cuenta administrativa activa.
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
a una variante. Cuando se implemente su editor, la validación debe exigir que la
variante pertenezca al mismo producto de la imagen.
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
- Listado público, filtros, paginación, destacados y ficha.
- Menú Productos por sectores activos y tipos de prenda, con filtro combinado.
  Los tipos y su agrupación inicial están en config/neva.php; el formulario de
  producto guarda product_type. Los productos anteriores conservan su contenido
  y pueden clasificarse desde edición. Los sectores nuevos tienen enlace propio;
  sus tipos se agregan en sector_types de la configuración.
- Registro público deshabilitado.
- Inicio de sesión limitado a administradores activos.
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

## Extensiones pendientes

La imagen principal ya se administra desde el formulario de producto. Quedan
pendientes el editor de variantes, la galería de múltiples imágenes y su ordenación.
La página de catálogos todavía no genera PDF ni ofrece descargas simuladas.
La captura de vistas y clics sigue pendiente; debe ser agregada, atómica y con
limitación de abuso. El aviso de privacidad requiere contenido final del cliente.
La bitácora actual cubre productos; ampliar a sectores y accesos cuando se
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
para crearla. Nunca incluye credenciales por defecto y rechaza habilitar un segundo
administrador activo. No promueve automáticamente a usuarios preexistentes.

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
dependencias no usadas. Si se requieren varios roles, añadir Policies y un modelo
de permisos según ese nuevo alcance. Actualmente toda la administración comparte
la misma autorización y solo admite una cuenta administrativa provisionada.
