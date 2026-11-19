# Changelog — Aisy

## [0.3.0] - 2026-11-18
### Added
- **Módulos Interactivos en Panel Admin (`/{slug}/admin`)**: Los módulos habilitados (`Agenda de Citas`, `Cobro Rápido / POS`, `Promociones y Lealtad`) ahora muestran sus formularios operativos directos dentro del panel (`POST /{slug}/admin/appointments`, `pos-sale`, `promotions`) sincronizados en tiempo real.
- **Modo Avanzado de Personalización de Sitio (`advanced_mode` + `landing_config`)**: Desactivado por defecto; al habilitarlo en `/{slug}/admin` permite editar colores, estilo, badge, título, subtítulo, horario, imagen hero y prender/apagar secciones de la landing (`Pilares`, `Galería`, `Reseñas`, `Mapa`, `Promociones`).

## [0.2.2] - 2026-11-18
### Added
- **Cuenta Maestra Multi-Company en Odoo + Consola `/maestro`**: Comando `php artisan aisy:sync-odoo` que crea/actualiza la cuenta maestra `maestro@aisy.ceballosleon.com` en Odoo (`:8069`) con acceso multi-compañía a todos los negocios, y vista `/maestro` en Laravel (exclusiva para `is_admin`) con tabla global de negocios y registro de actividades.

### Changed
- **Panel Admin 100% en Español y sin menciones visibles de Odoo**: Reemplazadas etiquetas en inglés (`Cita`, `Venta`, `Comanda`, `Promoción`, estados en español) y ocultas las referencias a Odoo manteniendo los IDs (`Ref. #...`).
- **Calendario Interactivo de Citas (Estéticas)**: Integrado FullCalendar en español en `/{slug}/admin` con vista semanal predeterminada y selector de vista **Diario / Semanal / Mensual**.

## [0.2.1] - 2026-11-18
### Added
- **Landing Pages Completas y Modernas por Giro (`/{slug}`)**: Rediseño integral con navbar glassmórfica, hero animado con gradientes dinámicos de marca (`primary_color`, `secondary_color`), SVG grid, pilares de valor, galería visual por rubro, promociones destacadas, catálogo interactivo, reseñas, mapa de ubicación y animaciones scroll-reveal.
- **Mapa Interactivo OpenStreetMap (Leaflet + Nominatim)**: Buscador de direcciones con autocompletado, pin arrastrable, autodetección de ubicación GPS (`navigator.geolocation` + geocodificación inversa) y guardado de `latitude`/`longitude` en el onboarding (`/nuevo`), además de mapa embebido y botón "Cómo llegar" en las Landings (`/{slug}`).

## [0.2.0] - 2026-11-18
### Added
- **Autenticación y Seguridad**: Login (`/login`, `/logout`), protección por propiedad de negocio (`user_id` + `is_admin`) en `/{slug}/admin*`, middleware `SecurityHeaders` (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-XSS-Protection`) y rate limiting (`throttle`) en login, onboarding, eventos y webhook de WhatsApp.
- **4 Seeders Demo (`AisyDemoSeeder`)**: Usuario demo `demo@aisy.ceballosleon.com` y 4 negocios en distintas fases (`estetica-aura-nueva`, `salon-lumiere`, `boutique-nube`, `taqueria-don-pepe`).
- **Autostart Tunnel Manager**: Registro de `aisy` en `autostart` local y túnel Cloudflare para mantener `https://aisy.ceballosleon.com` activo.

## [0.1.0] - 2026-11-18
### Added
- **Entorno Portable Autocontenido (`.runtime/`)**: PostgreSQL 16 (`127.0.0.1:5439`), Odoo 17.0 Community (`127.0.0.1:8069`, base única `aisy_erp` Multi-Company) y Gateway WhatsApp Web QR con Baileys (`127.0.0.1:8085`).
- **Capas Superiores en Laravel 12 (`127.0.0.1:8095` -> `aisy.ceballosleon.com`)**:
  - **Onboarding paso a paso (SSE)** en `/nuevo` con alta en 60 segundos y vinculación de QR WhatsApp.
  - **Capa 1 — Landing Page personalizada** (`/{slug}`) por giro (`estetica`, `tienda`, `comandas`) con Schema.org `LocalBusiness` y tracker de eventos hacia memoria IA.
  - **Capa 2 — Panel Admin por giro** (`/{slug}/admin`) con Feature Flags prendibles/apagables, consola del asistente, operaciones en vivo y bitácora.
  - **Capa 3 — Puente Odoo Headless** (`OdooClient` + `OdooBusinessService`) con aislamiento `company_id`, catálogo semilla y auto-creación de productos en ventas por voz.
  - **Capa 4 — Asistente IA con Memoria en 3 Niveles** conectado a `ia.ceballosleon.com` (MIIA: chat/tool-calling, Whisper, Imagesia) y `sc.ceballosleon.com` (Seller Central API Maestra) + Kit Google Maps + Motor Proactivo de Tiempos Muertos (`aisy:dead-times`).
