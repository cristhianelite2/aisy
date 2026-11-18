# Changelog — Aisy

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
