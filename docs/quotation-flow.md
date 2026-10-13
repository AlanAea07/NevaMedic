# Cotización y personalización

La ficha ofrece cotización directa por WhatsApp y personalización de la prenda. La selección de producto, color y talla se conserva al entrar en personalización. Desde el menú se puede iniciar una solicitud sin modelo y pedir asesoría.

El formulario permite un modelo y color por solicitud, cantidades por varias tallas o una cantidad aproximada opcional cuando todavía no se conocen las tallas. Bordados, acabados, logotipo y nombre de institución son opcionales. El resumen no calcula precios finales. Los archivos de logotipo se comparten después en WhatsApp.

El servidor valida que el producto siga disponible y que los colores y tallas pertenezcan al producto. El total de tallas conocidas se calcula en el servidor, no se confía en el resumen del navegador. Las solicitudes aceptan hasta 10,000 prendas y 700 caracteres de indicaciones. Los errores conservan los campos para corregirlos.

La salida es una redirección a `wa.me` con el texto codificado. No se usa la API de WhatsApp ni se envían mensajes automáticamente. El visitante revisa y envía el mensaje. No se crean pedidos ni se guardan cotizaciones en la base de datos.

Configurar `NEVA_WHATSAPP=526181827551` en el entorno de despliegue y ejecutar `php artisan config:clear` (o regenerar la caché de configuración). El número quedó aplicado al entorno local. Configurar `APP_URL` con el dominio público en producción; los enlaces de producto locales solo funcionan en este equipo.

Pruebas: `php artisan test --filter='CustomizationQuoteTest|ProductSizesTest'` y `node --test tests/js/*.test.js`.
