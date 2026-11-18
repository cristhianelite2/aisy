# Aisy — Plataforma SaaS Headless sobre Odoo con Asistente IA por WhatsApp

## 1. Propósito y Visión (Intent & Why)

**Aisy** (`aisy.ceballosleon.com`) es una plataforma ultra-sencilla para que dueños de pequeños locales (**Estéticas**, **Tiendas** y **Restaurantes/Comandas**) administren su negocio desde un único chat de WhatsApp (su Asistente IA personal) y un panel web minimalista con módulos encendibles/apagables, respaldado por **Odoo Community** como motor ERP headless invisible para los clientes.

El dueño nunca ve la interfaz compleja de Odoo. Toda la operación (registrar ventas por nota de voz, agendar citas, levantar comandas, crear promociones, generar anuncios con IA, publicar en redes sociales mediante Seller Central y optimizar su ficha de Google Maps) ocurre a través de las capas superiores de Aisy y su asistente de WhatsApp.

---

## 2. Objetivos, No-Objetivos y Anti-Objetivos

### Objetivos (Goals)
1. **Entorno Portable Autocontenido en `D:\Proyectos\aisy\.runtime\`**:
   - PostgreSQL 16 portable (binarios locales sin instalador de Windows ni Docker).
   - Odoo 17.0 Community portable corriendo sobre Python `.venv` local en el puerto `127.0.0.1:8069` con una sola base de datos (`aisy_erp`) y arquitectura **Multi-Company** (`res.company` por negocio).
   - Gateway de WhatsApp Web QR portable en Node.js (Baileys, API compatible con Evolution API) en el puerto `127.0.0.1:8085`.
2. **Aplicación Principal Aisy en Laravel 11 (`D:\Proyectos\aisy`)**:
   - Corriendo con PHP 8.2 (`D:\Apps\xampp\php\php.exe`) y MySQL (`127.0.0.1:3306`, base de datos `aisy`) en el puerto `127.0.0.1:8095`.
   - Publicada en `https://aisy.ceballosleon.com` y registrada en `tunnel-manager` (servicio local `aisy` + túnel Cloudflare `aisy`).
3. **Las 4 Capas de Aisy**:
   - **Capa 1 — Landing Page Pública (`aisy.ceballosleon.com/{slug}`)**: Personalizada con los colores, estilo, dirección, Schema.org `LocalBusiness` (JSON-LD para Google Maps/SEO), tracker de eventos para la memoria de la IA, y widget interactivo según la rama (Agendar Cita para Estéticas, Catálogo/Carrito para Tiendas, Menú Digital QR/Pedido para Comandas).
   - **Capa 2 — Panel Admin por Giro (`aisy.ceballosleon.com/{slug}/admin`)**: Interfaz limpia con **Feature Flags** encendibles/apagables en un clic, conectada bidireccionalmente a Odoo vía JSON-RPC, bitácora de actividad de la IA, botón de conexión a redes vía Seller Central y Kit Google Maps.
   - **Capa 3 — Motor Odoo Headless**: Una sola BD PostgreSQL (`aisy_erp`) con módulos `base`, `sale_management`, `point_of_sale`, `pos_restaurant`, `calendar`, `stock`, `loyalty`, `crm`. Cada negocio es un `res.company` aislado con su propio almacén, categoría raíz, diario/configuración POS y calendario.
   - **Capa 4 — Asistente IA con Criterio y Memoria en 3 Niveles**:
     - Conectado a **`ia.ceballosleon.com` (MIIA)** para chat/tool-calling (`/v1/chat/completions`), motor de decisiones (`/v1/decisions`), transcripción de notas de voz de WhatsApp (`/v1/audio/transcriptions`) y generación de imágenes promocionales (`/v1/images/generations`).
     - Conectado a **`sc.ceballosleon.com` (Seller Central)** mediante su API Maestra (`/api/v1/master/...`) para crear el proyecto del negocio y programar/publicar contenido en redes sociales (Opción B).
     - Enfoque **Dueño ↔ Asistente IA** por WhatsApp: recibe texto, audio o fotos del dueño; ejecuta acciones en Odoo o en MIIA/Seller Central según criterio; y recomienda enviar confirmaciones con 1 toque a los clientes finales cuando aplique.
4. **Onboarding en 1 Minuto con Progreso SSE en Tiempo Real**:
   - Formulario básico en `aisy.ceballosleon.com/nuevo` (Nombre completo, WhatsApp, nombre del negocio, colores favoritos, dirección, rubro).
   - Pantalla paso a paso vía Server-Sent Events (SSE) que aprovisiona la compañía en Odoo, el proyecto en Seller Central, la memoria IA inicial, el catálogo semilla, las rutas `{slug}` y muestra el QR de WhatsApp.
5. **Motor Proactivo de "Tiempos Muertos"**:
   - Comando programado (`php artisan aisy:dead-times`) que analiza ventas/citas en Odoo, detecta baja demanda y envía recomendaciones proactivas al WhatsApp del dueño para pedirle fotos, armar anuncios y publicarlos en Seller Central.

### No-Objetivos (Non-Goals)
- No se expondrá el backend nativo de Odoo (`:8069`) a los dueños de negocio.
- No se creará una base de datos PostgreSQL separada por cada negocio; todo vive en una única BD de Odoo con aislamiento por `company_id`.
- El bot de WhatsApp no chatea libremente con clientes finales desconocidos; es el **Asistente Ejecutivo del Dueño** y genera enlaces/confirmaciones listas para los clientes finales.

### Anti-Objetivos (Anti-Goals)
- **Cero Docker y Cero Instaladores Globales de Windows** para Odoo o PostgreSQL: todo el runtime externo vive en `D:\Proyectos\aisy\.runtime\`.
- **Cero Fricción Empresarial en el Onboarding**: el dueño nunca debe configurar claves de API, cuentas de Meta Developers ni parámetros contables de Odoo.

---

## 3. Especificación Técnica Detallada (Decisiones del Builder)

### 3.1 Estructura del Runtime Portable (`D:\Proyectos\aisy\.runtime\`)
- `.runtime/pgsql/`: Binarios de PostgreSQL 16 Windows x64 (`pg_ctl.exe`, `initdb.exe`, `psql.exe`), data dir en `.runtime/pgsql-data/` escuchando en `127.0.0.1:5439` (puerto `5439` para evitar cualquier colisión con instalaciones previas), usuario `odoo`, base de datos `aisy_erp`.
- `.runtime/odoo/`: Código fuente de Odoo 17.0 Community + `.runtime/odoo-venv/` (Python 3.11 virtual environment) + `.runtime/odoo.conf` escuchando en `127.0.0.1:8069`.
- `.runtime/wa-gateway/`: Microservicio Node.js con `@whiskeysockets/baileys`, `express` y `qrcode` escuchando en `127.0.0.1:8085`, guardando sesiones multi-instancia en `.runtime/wa-gateway/sessions/{slug}/` y disparando webhooks a `http://127.0.0.1:8095/api/webhooks/whatsapp/{slug}`.
- `.runtime/scripts/start-runtime.ps1` y `stop-runtime.ps1`: Scripts que levantan y detienen limpiamente PostgreSQL (`:5439`), Odoo (`:8069`) y WhatsApp Gateway (`:8085`).

### 3.2 Las 3 Ramas de Negocio y sus Feature Flags
Cada negocio tiene un rubro (`branch`: `estetica` | `tienda` | `comandas`) y un JSON de `features` (módulos que se pueden prender y apagar en caliente desde `/{slug}/admin` o pidiéndoselo al Asistente IA por WhatsApp):

| Rama (`branch`) | Módulos Prendibles/Apagables (`features`) | Mapeo en Odoo (`company_id`) |
| :--- | :--- | :--- |
| **`estetica`** | `agenda` (Citas y horarios), `ventas_pos` (Cobro rápido por voz/toque), `promociones` (Cupones y lealtad), `anuncios_ia` (Flyers + Seller Central), `google_maps` (Kit SEO + QR Reseñas) | `calendar.event`, `product.template` (`type=service`), `sale.order` / `pos.order`, `loyalty.program` |
| **`tienda`** | `pos` (Punto de venta e inventario), `catalogo_web` (Tienda en la landing), `estadisticas` (Métricas y cortes por IA), `envios` (Zonas y seguimiento de entregas), `promociones`, `anuncios_ia`, `google_maps` | `product.template` (`type=product`), `stock.quant`, `pos.order`, `sale.order` (con estado de envío), `loyalty.program` |
| **`comandas`** | `menu_qr` (Menú digital en landing), `comandas_kds` (Tablero de cocina en vivo), `mesas` (Control de mesas y cuentas), `pos_voz` (Comanda dictada por audio), `promociones`, `anuncios_ia`, `google_maps` | `pos.order`, `pos.order.line`, `restaurant.floor` / `restaurant.table` (o metadatos de mesa/estado de preparación sincronizados con Odoo) |

### 3.3 Arquitectura de Memoria de IA en 3 Niveles (MySQL `aisy`)
1. **Nivel 1 — Memoria Semántica (`ai_business_memories` / JSON en `businesses.ai_memory_semantic`)**:
   - ADN del negocio: nombre del dueño, nombre del local, giro, colores primarios/secundarios, estilo visual, dirección, horarios, reglas personalizadas, ID de compañía en Odoo (`odoo_company_id`), ID de proyecto en Seller Central (`sc_project_id`).
2. **Nivel 2 — Memoria Episódica (`ai_episodic_memories` + `page_events`)**:
   - Bitácora cronológica de:
     - Acciones de los visitantes dentro de la landing `/{slug}` (visitas, clics en servicios/productos, intentos de reserva, pedidos, cupones vistos).
     - Sesiones de conversación de WhatsApp, promociones creadas, anuncios enviados a Seller Central y recomendaciones aceptadas/rechazadas.
3. **Nivel 3 — Memoria Operativa en Vivo (Router IA con Tool Calling a Odoo + MIIA + Seller Central)**:
   - El servicio `AisyAssistantService` recibe el mensaje (o transcribe el audio vía MIIA `/v1/audio/transcriptions`), inyecta Nivel 1 + resumen de Nivel 2, y dispone de herramientas ejecutables (`tools`):
     - `odoo_register_sale` (crea venta/ticket POS en Odoo a partir de audio o texto).
     - `odoo_schedule_appointment` (agenda cita en `calendar.event` y devuelve link de confirmación por WhatsApp para el cliente).
     - `odoo_create_kitchen_order` (crea comanda de restaurante con mesa y platillos).
     - `odoo_query_metrics` (consulta ventas del día/semana, productos top, citas pendientes o stock en Odoo).
     - `odoo_create_promotion` (crea promoción/descuento en Odoo y la activa en la landing).
     - `admin_toggle_feature` (prende o apaga un módulo del admin del negocio).
     - `marketing_generate_and_publish_ad` (genera copy + flyer con MIIA `/v1/images/generations` usando los colores del negocio y lo programa/publica en Seller Central `sc.ceballosleon.com` vía API Maestra).
     - `google_maps_update_kit` (actualiza coordenadas/fotos de fachada y genera el Kit de Google Business Profile + QR de 5 estrellas).

### 3.4 Integración con `ia.ceballosleon.com` (MIIA) y `sc.ceballosleon.com` (Seller Central)
- **MIIAClient**:
  - Configurable vía `.env` (`MIIA_BASE_URL=https://ia.ceballosleon.com`, `MIIA_API_KEY=...`) con fallback local o directo si el servicio requiere key.
  - Soporta:
    - `POST /v1/chat/completions` (con `tools` y `tool_choice`).
    - `POST /v1/decisions` (para clasificar intención rápida: `operacion_odoo`, `marketing_ia`, `config_admin`, `consulta_metricas`).
    - `POST /v1/audio/transcriptions` (para notas de voz `.ogg` / `.mp3` recibidas desde WhatsApp o grabadas en el navegador del Admin).
    - `POST /v1/images/generations` (para flyers y anuncios con los colores del negocio).
- **SellerCentralClient**:
  - Configurable vía `.env` (`SC_BASE_URL=https://sc.ceballosleon.com`, `SC_MASTER_KEY=...` o lectura automática de `master_api_settings` de la base de datos local de Seller Central si comparten MySQL local).
  - Al crear un negocio en Aisy, sincroniza/crea su proyecto en Seller Central y permite programar posts en Planly (`/api/v1/master/projects/{id}/api/...`) y abrir el conector de sesiones sociales.

### 3.5 Integración con `tunnel-manager` y Dominio `aisy.ceballosleon.com`
- Se registra el servicio local `aisy` (`127.0.0.1:8095`) en `D:\Proyectos\tunnel-manager\tunnel-manager.ps1` (el cual arranca automáticamente el runtime portable `.runtime` + `artisan serve --port=8095`).
- Se crea y arranca el túnel Cloudflare `aisy` apuntando `aisy.ceballosleon.com` → `http://127.0.0.1:8095`.
- Se documenta el proyecto en el vault de Obsidian (`D:\Proyectos\obsidian-vault\Proyectos\aisy\aisy.md` y `Bitácora.md` + `Proyectos.md`) y se sube el repositorio a GitHub (`cristhianelite2/aisy`, ramas `test` y `main`).
