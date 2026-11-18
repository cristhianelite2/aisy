# Aisy Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and deploy the complete Aisy SaaS platform on top of a self-contained portable Odoo 17 + PostgreSQL 16 + Baileys WhatsApp Gateway runtime in `D:\Proyectos\aisy\.runtime\`, with a Laravel 11 application serving the 4 layers (Landing Page, Giro Admin with toggleable features, Headless Odoo Multi-Company bridge, and 3-tier AI Assistant connected to `ia.ceballosleon.com` and `sc.ceballosleon.com`), real-time SSE onboarding, dead-time proactive recommendations, and Cloudflare tunnel integration on `aisy.ceballosleon.com`.

**Architecture:** A single Laravel 11 codebase in `D:\Proyectos\aisy` talks via JSON-RPC/XML-RPC to a portable Odoo 17 Community instance (`127.0.0.1:8069`, single PostgreSQL 16 database `aisy_erp` on `127.0.0.1:5439`, isolated per business via `res.company`), to a portable Node.js Baileys WhatsApp QR microservice (`127.0.0.1:8085`), to MIIA (`ia.ceballosleon.com`) for LLM tool-calling, decision routing, Whisper audio transcription, and Imagesia flyer generation, and to Seller Central (`sc.ceballosleon.com`) via its Master API for multi-network social publishing.

**Tech Stack:** PHP 8.2 (`D:\Apps\xampp\php\php.exe`), Laravel 11, MySQL (`127.0.0.1:3306`, DB `aisy`), Tailwind CSS, Python 3.11 + Odoo 17.0 Community + PostgreSQL 16 Portable, Node.js 24 + `@whiskeysockets/baileys` + Express, Cloudflare Tunnel (`tunnel-manager`).

**Spec:** `d:\Proyectos\aisy\docs\superpowers\specs\2026-11-18-aisy-platform-design.md`

## Global Constraints

- Zero Docker and zero Windows system installers: PostgreSQL 16, Odoo 17.0, and the WhatsApp QR Gateway MUST reside inside `D:\Proyectos\aisy\.runtime\` and be excluded from Git via `.gitignore` (while keeping `.runtime/scripts/` versioned).
- Single Odoo database `aisy_erp` using Multi-Company (`res.company` per business) with modules `base`, `sale_management`, `point_of_sale`, `pos_restaurant`, `calendar`, `stock`, `loyalty`, `crm`.
- Three supported business branches (`branch`): `estetica`, `tienda`, `comandas`, each with dynamic toggleable feature flags (`features` JSON) and tailored landing page + admin capabilities.
- Three-tier AI Memory per business: Level 1 Semantic (ADN/colors/rules), Level 2 Episodic (landing page visitor events + WhatsApp sessions + promotions history), Level 3 Live Operational Tool Calling (Odoo ERP actions, MIIA content/audio/image generation, Seller Central publishing, Google Maps kit).
- Owner-to-Assistant WhatsApp model: the business owner chats (text, voice notes, photos) with their assistant; the assistant executes actions in Odoo/MIIA/Seller Central and generates 1-tap WhatsApp confirmation links/messages for end customers when applicable.
- Git & Vault workflow: develop on `test` branch, maintain `CHANGELOG.md`, promote to `main` and push to `github.com/cristhianelite2/aisy` when finished, and document in `D:\Proyectos\obsidian-vault\Proyectos\aisy\` (`main` branch).

## Review Focus

1. **Slug collisions or special characters in business names**: Registering two businesses named `"Estética Lupita!"` must generate unique URL-safe slugs (`estetica-lupita`, `estetica-lupita-2`) instead of failing with a 500 duplicate key error.
2. **Odoo or external service temporarily unreachable during provisioning or chat**: `OdooClient`, `MiiaClient`, `SellerCentralClient`, and `WhatsAppGatewayClient` must degrade gracefully and return structured state/fallback data so SSE provisioning and local admin operations never crash or hang indefinitely.
3. **Voice note POS / Order parsing with unknown products**: When the owner dictates a sale or kitchen order for a product not yet in Odoo's catalog for that `company_id`, `OdooBusinessService` must auto-create the product under that company's catalog before creating the `pos.order` / `sale.order`.
4. **Cross-tenant data leakage in Multi-Company Odoo queries**: Every Odoo query and write (`product.template`, `sale.order`, `pos.order`, `calendar.event`, `loyalty.program`) must explicitly filter by and stamp `company_id` for the active `Business`.
5. **WhatsApp webhook authentication and owner phone verification**: Incoming messages to `/api/webhooks/whatsapp/{slug}` from numbers other than the business owner's `owner_phone` must not be able to execute administrative write tools (`admin_toggle_feature`, `marketing_generate_and_publish_ad`); non-owner messages are logged and trigger an owner notification suggestion.

---

### Task 1: Portable Runtime Setup (`.runtime/` — PostgreSQL 16, Odoo 17 Community, WhatsApp QR Gateway)

**Files:**
- Create: `.runtime/scripts/install-runtime.ps1`
- Create: `.runtime/scripts/start-runtime.ps1`
- Create: `.runtime/scripts/stop-runtime.ps1`
- Create: `.runtime/scripts/status-runtime.ps1`
- Create: `.runtime/wa-gateway/package.json`
- Create: `.runtime/wa-gateway/server.js`

**Interfaces:**
- Produces:
  - Portable PostgreSQL 16 listening on `127.0.0.1:5439`, user `odoo`, password `odoo`, database `aisy_erp`.
  - Portable Odoo 17.0 listening on `127.0.0.1:8069` with XML-RPC/JSON-RPC endpoints `/xmlrpc/2/common`, `/xmlrpc/2/object`, `/jsonrpc` and database `aisy_erp` initialized with `base`, `sale_management`, `point_of_sale`, `pos_restaurant`, `calendar`, `stock`, `loyalty`, `crm`.
  - Portable WhatsApp Gateway listening on `127.0.0.1:8085` with endpoints:
    - `POST /instance/create` (`{ instanceName, webhookUrl }`)
    - `GET /instance/connect/:instanceName` (returns `{ status, qrcode, pairingCode }`)
    - `GET /instance/status/:instanceName` (returns `{ instanceName, state, connectedPhone }`)
    - `POST /message/sendText/:instanceName` (`{ number, text }`)
    - `POST /message/sendMedia/:instanceName` (`{ number, mediaUrl, caption }`)

- [ ] **Step 1: Write `.runtime/scripts/status-runtime.ps1` and test that runtime check fails before installation**

Run: `powershell -NoProfile -ExecutionPolicy Bypass -File d:\Proyectos\aisy\.runtime\scripts\status-runtime.ps1 -Json`
Expected: JSON output with `postgres_up: false`, `odoo_up: false`, `wa_gateway_up: false` and non-zero exit code when `-RequireAll` is passed.

- [ ] **Step 2: Implement `.runtime/scripts/install-runtime.ps1`, `.runtime/wa-gateway/package.json`, `.runtime/wa-gateway/server.js`, `.runtime/scripts/start-runtime.ps1`, and `.runtime/scripts/stop-runtime.ps1`**

Download portable PostgreSQL 16 binaries into `.runtime/pgsql`, initialize `.runtime/pgsql-data` on port `5439`, download Odoo 17.0 source into `.runtime/odoo`, create Python 3.11 venv `.runtime/odoo-venv` with Odoo dependencies, initialize database `aisy_erp` with required modules, and create the Express + `@whiskeysockets/baileys` multi-instance WhatsApp QR gateway on port `8085`.

- [ ] **Step 3: Execute installation and start the portable runtime**

Run: `powershell -NoProfile -ExecutionPolicy Bypass -File d:\Proyectos\aisy\.runtime\scripts\install-runtime.ps1`
Run: `powershell -NoProfile -ExecutionPolicy Bypass -File d:\Proyectos\aisy\.runtime\scripts\start-runtime.ps1`
Run: `powershell -NoProfile -ExecutionPolicy Bypass -File d:\Proyectos\aisy\.runtime\scripts\status-runtime.ps1 -RequireAll`
Expected: `postgres_up: true (:5439)`, `odoo_up: true (:8069)`, `wa_gateway_up: true (:8085)`, exit code `0`.

---

### Task 2: Laravel 11 Application Scaffolding & Database Schema (3-Tier Memory, Businesses, Feature Flags)

**Files:**
- Create: Laravel 11 project in `d:\Proyectos\aisy`
- Create: `database/migrations/2026_11_18_000001_create_businesses_table.php`
- Create: `database/migrations/2026_11_18_000002_create_ai_episodic_memories_table.php`
- Create: `database/migrations/2026_11_18_000003_create_page_events_table.php`
- Create: `database/migrations/2026_11_18_000004_create_business_activities_table.php`
- Create: `database/migrations/2026_11_18_000005_create_business_catalog_items_table.php`
- Create: `database/migrations/2026_11_18_000006_create_business_operations_table.php`
- Create: `app/Models/Business.php`
- Create: `app/Models/AiEpisodicMemory.php`
- Create: `app/Models/PageEvent.php`
- Create: `app/Models/BusinessActivity.php`
- Create: `app/Models/BusinessCatalogItem.php`
- Create: `app/Models/BusinessOperation.php`
- Test: `tests/Feature/BusinessModelTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Business`:
    - Attributes: `id`, `name`, `slug`, `branch` (`estetica`|`tienda`|`comandas`), `owner_name`, `owner_phone`, `address`, `latitude`, `longitude`, `primary_color`, `secondary_color`, `style_preset`, `features` (array), `ai_memory_semantic` (array), `odoo_company_id` (int), `odoo_pos_config_id` (int), `odoo_warehouse_id` (int), `sc_project_id` (int), `wa_instance_name` (string), `wa_connected` (bool), `gmaps_place_url` (string), `gmaps_kit` (array), `provisioning_status` (string), `provisioning_steps` (array).
    - Static helper: `Business::generateUniqueSlug(string $name): string`
    - Default branch features: `Business::defaultFeaturesForBranch(string $branch): array`
    - Method: `$business->isFeatureEnabled(string $feature): bool`
    - Method: `$business->toggleFeature(string $feature, ?bool $enabled = null): array`

- [ ] **Step 1: Scaffold Laravel 11 project, configure `.env` for MySQL `aisy` and `.gitignore` for `.runtime/` binaries, and write `tests/Feature/BusinessModelTest.php`**

```php
public function test_generates_unique_slug_and_default_branch_features(): void
{
    $b1 = Business::create([
        'name' => 'Estética Lupita!',
        'slug' => Business::generateUniqueSlug('Estética Lupita!'),
        'branch' => 'estetica',
        'owner_name' => 'Lupita Pérez',
        'owner_phone' => '5215512345678',
        'features' => Business::defaultFeaturesForBranch('estetica'),
    ]);
    $b2 = Business::create([
        'name' => 'Estética Lupita!',
        'slug' => Business::generateUniqueSlug('Estética Lupita!'),
        'branch' => 'estetica',
        'owner_name' => 'Lupita Dos',
        'owner_phone' => '5215587654321',
        'features' => Business::defaultFeaturesForBranch('estetica'),
    ]);

    $this->assertSame('estetica-lupita', $b1->slug);
    $this->assertSame('estetica-lupita-2', $b2->slug);
    $this->assertTrue($b1->isFeatureEnabled('agenda'));
    $b1->toggleFeature('agenda', false);
    $this->assertFalse($b1->fresh()->isFeatureEnabled('agenda'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=BusinessModelTest`
Expected: FAIL because migrations and `Business` model do not exist yet.

- [ ] **Step 3: Implement migrations and Eloquent models (`Business`, `AiEpisodicMemory`, `PageEvent`, `BusinessActivity`, `BusinessCatalogItem`, `BusinessOperation`)**

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=BusinessModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: scaffold Laravel 11 app, portable runtime scripts, and core business/memory models"
```

---

### Task 3: Headless Odoo Multi-Company Bridge (`OdooClient` & `OdooBusinessService`)

**Files:**
- Create: `app/Services/Odoo/OdooClient.php`
- Create: `app/Services/Odoo/OdooBusinessService.php`
- Create: `config/aisy.php`
- Test: `tests/Feature/OdooBusinessServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Business`
- Produces:
  - `App\Services\Odoo\OdooClient`:
    - `authenticate(): int`
    - `executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed`
    - `searchRead(string $model, array $domain = [], array $fields = [], int $limit = 100, string $order = 'id desc'): array`
    - `create(string $model, array $values): int`
    - `write(string $model, array $ids, array $values): bool`
  - `App\Services\Odoo\OdooBusinessService`:
    - `provisionCompany(Business $business): array` (creates `res.company` in Odoo, seeds branch-specific products/services in `product.template` stamped with `company_id`, creates local mirror records in `business_catalog_items`, and returns `['company_id' => int, 'seeded_items' => int]`)
    - `syncCatalogItem(Business $business, array $data): BusinessCatalogItem` (finds or creates product/service in Odoo under `company_id` and updates local mirror)
    - `registerSaleOrOrder(Business $business, array $payload): BusinessOperation` (auto-creates unknown items in Odoo for that `company_id`, creates `sale.order` in Odoo stamped with `company_id`, records local `BusinessOperation` + `BusinessActivity`)
    - `scheduleAppointment(Business $business, array $payload): BusinessOperation` (creates `calendar.event` in Odoo with `company_id` / description metadata, records local operation + generates WhatsApp 1-tap customer confirmation link)
    - `createPromotion(Business $business, array $payload): BusinessOperation` (creates `loyalty.program` or discount product in Odoo for `company_id` and records active promotion)
    - `getBusinessMetrics(Business $business): array` (aggregates sales today, week, order count, upcoming appointments, active comandas, and top items filtered strictly by `company_id`)

- [ ] **Step 1: Write `tests/Feature/OdooBusinessServiceTest.php` covering multi-company isolation, auto-creating unknown products during voice/POS sales, and appointment/kitchen order creation**

```php
public function test_provisions_isolated_company_and_auto_creates_unknown_products_on_sale(): void
{
    $service = app(OdooBusinessService::class);
    $b1 = Business::factory()->create(['name' => 'Estética Uno', 'branch' => 'estetica']);
    $b2 = Business::factory()->create(['name' => 'Tienda Dos', 'branch' => 'tienda']);

    $res1 = $service->provisionCompany($b1);
    $res2 = $service->provisionCompany($b2);

    $this->assertNotEquals($res1['company_id'], $res2['company_id']);

    $sale = $service->registerSaleOrOrder($b1->fresh(), [
        'customer_name' => 'María',
        'customer_phone' => '5215511112222',
        'items' => [
            ['name' => 'Tinte Especial Nuevo', 'qty' => 1, 'price' => 450],
            ['name' => 'Shampoo Keratina', 'qty' => 1, 'price' => 180],
        ],
        'source' => 'whatsapp_audio',
    ]);

    $this->assertEquals(630, $sale->total_amount);
    $this->assertDatabaseHas('business_catalog_items', [
        'business_id' => $b1->id,
        'name' => 'Tinte Especial Nuevo',
    ]);
    $this->assertDatabaseMissing('business_catalog_items', [
        'business_id' => $b2->id,
        'name' => 'Tinte Especial Nuevo',
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=OdooBusinessServiceTest`
Expected: FAIL because `OdooBusinessService` does not exist.

- [ ] **Step 3: Implement `config/aisy.php`, `OdooClient`, and `OdooBusinessService` connected to the portable Odoo 17 instance (`127.0.0.1:8069`)**

- [ ] **Step 4: Run test to verify it passes against live portable Odoo**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=OdooBusinessServiceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/aisy.php app/Services/Odoo tests/Feature/OdooBusinessServiceTest.php database/factories
git commit -m "feat: implement headless Odoo 17 multi-company JSON-RPC bridge and branch operations"
```

---

### Task 4: External Ecosystem Bridges (`MiiaClient`, `SellerCentralClient`, `WhatsAppGatewayClient`, `GoogleMapsKitService`)

**Files:**
- Create: `app/Services/Integrations/MiiaClient.php`
- Create: `app/Services/Integrations/SellerCentralClient.php`
- Create: `app/Services/Integrations/WhatsAppGatewayClient.php`
- Create: `app/Services/Integrations/GoogleMapsKitService.php`
- Test: `tests/Feature/ExternalIntegrationsTest.php`

**Interfaces:**
- Consumes: `App\Models\Business`
- Produces:
  - `App\Services\Integrations\MiiaClient`:
    - `chatWithTools(array $messages, array $tools = [], ?string $systemPrompt = null): array` (calls `ia.ceballosleon.com/v1/chat/completions`, auto-discovers key from `.env` or local `D:\Proyectos\miia` SQLite/MySQL if unset, with deterministic local heuristic parser fallback if offline)
    - `decide(string $prompt, array $options): string` (calls `ia.ceballosleon.com/v1/decisions`)
    - `transcribeAudio(string $filePathOrBase64, string $mimeType = 'audio/ogg'): string` (calls `ia.ceballosleon.com/v1/audio/transcriptions`)
    - `generateImage(string $prompt, array $options = []): array` (calls `ia.ceballosleon.com/v1/images/generations` and fallback SVG/canvas promotional banner generator styled with the business colors)
  - `App\Services\Integrations\SellerCentralClient`:
    - `ensureProjectForBusiness(Business $business): ?int` (creates or links project in `sc.ceballosleon.com` via Master API `/api/v1/master/projects`)
    - `scheduleSocialPost(Business $business, array $postData): array` (publishes/schedules post with image + caption to Facebook, Instagram, TikTok, and WhatsApp groups via Seller Central Master API `/api/v1/master/projects/{id}/api/posts`)
    - `getConnectSocialUrl(Business $business): string` (returns direct URL to connect social sessions in Seller Central for that project)
  - `App\Services\Integrations\WhatsAppGatewayClient`:
    - `createInstance(Business $business): array`
    - `getQrCode(Business $business): array`
    - `getStatus(Business $business): array`
    - `sendText(Business $business, string $phone, string $text): array`
    - `sendMedia(Business $business, string $phone, string $mediaUrl, string $caption = ''): array`
    - `buildClickToChatLink(string $phone, string $message): string`
  - `App\Services\Integrations\GoogleMapsKitService`:
    - `generateKit(Business $business, ?array $coords = null, ?string $facadePhotoUrl = null): array` (builds SEO title, category, description, Schema.org `LocalBusiness` JSON-LD, direct `business.google.com/create` deep-link guide, and 5-star review QR SVG/URL)

- [ ] **Step 1: Write `tests/Feature/ExternalIntegrationsTest.php` verifying MIIA, Seller Central, WhatsApp Gateway, and Google Maps Kit generation**

```php
public function test_generates_google_maps_kit_and_handles_external_clients_gracefully(): void
{
    $business = Business::factory()->create([
        'name' => 'Tacos El Fuego',
        'branch' => 'comandas',
        'primary_color' => '#ef4444',
        'secondary_color' => '#f59e0b',
        'address' => 'Av. Reforma 123, CDMX',
    ]);

    $kit = app(GoogleMapsKitService::class)->generateKit($business, ['lat' => 19.4326, 'lng' => -99.1332]);
    $this->assertArrayHasKey('json_ld', $kit);
    $this->assertArrayHasKey('create_url', $kit);
    $this->assertArrayHasKey('review_qr_svg', $kit);
    $this->assertStringContainsString('Restaurant', json_encode($kit['json_ld']));

    $wa = app(WhatsAppGatewayClient::class)->createInstance($business);
    $this->assertTrue($wa['ok']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=ExternalIntegrationsTest`
Expected: FAIL because service classes do not exist.

- [ ] **Step 3: Implement `MiiaClient`, `SellerCentralClient`, `WhatsAppGatewayClient`, and `GoogleMapsKitService`**

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=ExternalIntegrationsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Integrations tests/Feature/ExternalIntegrationsTest.php
git commit -m "feat: add MIIA, Seller Central, WhatsApp QR Gateway, and Google Maps Kit integrations"
```

---

### Task 5: 3-Tier AI Memory, Intelligent Assistant Router & Proactive Dead-Time Engine

**Files:**
- Create: `app/Services/Ai/AiMemoryService.php`
- Create: `app/Services/Ai/AisyAssistantService.php`
- Create: `app/Services/Ai/DeadTimeRecommendationService.php`
- Create: `app/Console/Commands/AnalyzeDeadTimesCommand.php`
- Create: `app/Http/Controllers/Api/WhatsAppWebhookController.php`
- Test: `tests/Feature/AisyAssistantAndMemoryTest.php`

**Interfaces:**
- Consumes: `Business`, `OdooBusinessService`, `MiiaClient`, `SellerCentralClient`, `WhatsAppGatewayClient`, `GoogleMapsKitService`
- Produces:
  - `App\Services\Ai\AiMemoryService`:
    - `getSemanticMemory(Business $business): array`
    - `updateSemanticMemory(Business $business, array $patch): array`
    - `recordEpisodicEvent(Business $business, string $type, string $summary, array $payload = []): AiEpisodicMemory`
    - `recordPageEvent(Business $business, string $eventType, array $metadata = [], ?string $sessionId = null): PageEvent`
    - `buildPromptContext(Business $business): string` (combines Level 1 Semantic ADN + Level 2 recent episodic memories & visitor page analytics + Level 3 live Odoo metrics summary)
  - `App\Services\Ai\AisyAssistantService`:
    - `handleOwnerInput(Business $business, array $input): array`
      - `$input`: `['text' => ?string, 'audio_path' => ?string, 'audio_base64' => ?string, 'image_url' => ?string, 'location' => ?array, 'sender_phone' => string, 'channel' => 'whatsapp'|'admin_chat']`
      - Validates owner authorization when `channel === 'whatsapp'` (rejects unauthorized write commands from non-owner numbers and logs a visitor inquiry instead).
      - Transcribes audio via `MiiaClient::transcribeAudio()` if audio is provided.
      - Uses judgment (MIIA decision + tool-calling + deterministic intent router) to route to:
        1. `odoo_register_sale` (voice/text POS sale -> Odoo `sale.order` + 1-tap customer receipt link)
        2. `odoo_schedule_appointment` (Estética appointment -> Odoo `calendar.event` + 1-tap customer confirmation link)
        3. `odoo_create_kitchen_order` (Comandas table/order -> Odoo order + KDS board update)
        4. `odoo_query_metrics` (Sales today, top products, dead hours, appointments)
        5. `odoo_create_promotion` (Creates promo in Odoo + activates banner on Landing Page)
        6. `admin_toggle_feature` (Turns admin modules on/off)
        7. `marketing_generate_and_publish_ad` (Calls `ia.ceballosleon.com` for copy & promotional image with business colors + schedules in `sc.ceballosleon.com` Planly)
        8. `google_maps_update_kit` (Updates coordinates/facade photo and returns Google Maps onboarding kit + 5-star review QR)
  - `App\Services\Ai\DeadTimeRecommendationService`:
    - `analyzeAndRecommend(Business $business, bool $sendWhatsApp = true): array` (detects low sales/empty appointment slots or high landing page views without conversions, crafts a proactive recommendation asking for a photo or confirmation to launch an ad/promo, and sends it to the owner's WhatsApp)

- [ ] **Step 1: Write `tests/Feature/AisyAssistantAndMemoryTest.php` testing 3-tier memory persistence, voice POS routing, ad generation routing to MIIA/Seller Central, unauthorized non-owner WhatsApp protection, and dead-time recommendations**

```php
public function test_assistant_routes_pos_voice_sale_to_odoo_and_ad_request_to_miia_and_sc(): void
{
    $business = Business::factory()->create([
        'name' => 'Barbería Royal',
        'branch' => 'estetica',
        'owner_name' => 'Carlos',
        'owner_phone' => '5215512345678',
        'primary_color' => '#1e293b',
        'secondary_color' => '#d97706',
    ]);
    app(OdooBusinessService::class)->provisionCompany($business);

    $assistant = app(AisyAssistantService::class);

    // 1. Operational POS command
    $saleReply = $assistant->handleOwnerInput($business->fresh(), [
        'text' => 'Cóbrale a Juan un corte de 250 y una cera de 150 en efectivo',
        'sender_phone' => '5215512345678',
        'channel' => 'whatsapp',
    ]);
    $this->assertEquals('odoo_register_sale', $saleReply['tool_used']);
    $this->assertStringContainsString('400', $saleReply['reply']);
    $this->assertNotEmpty($saleReply['customer_confirmation_url']);

    // 2. Marketing ad request -> goes to MIIA + Seller Central, not just admin
    $adReply = $assistant->handleOwnerInput($business->fresh(), [
        'text' => 'Créame un anuncio de 2x1 en corte y barba para publicar en mis redes sociales',
        'sender_phone' => '5215512345678',
        'channel' => 'whatsapp',
    ]);
    $this->assertEquals('marketing_generate_and_publish_ad', $adReply['tool_used']);
    $this->assertNotEmpty($adReply['media_url']);

    // 3. Non-owner phone cannot execute admin write tools
    $strangerReply = $assistant->handleOwnerInput($business->fresh(), [
        'text' => 'Apaga el módulo de agenda',
        'sender_phone' => '5215599999999',
        'channel' => 'whatsapp',
    ]);
    $this->assertEquals('unauthorized_sender', $strangerReply['tool_used']);
    $this->assertTrue($business->fresh()->isFeatureEnabled('agenda'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=AisyAssistantAndMemoryTest`
Expected: FAIL because assistant and memory services do not exist.

- [ ] **Step 3: Implement `AiMemoryService`, `AisyAssistantService`, `DeadTimeRecommendationService`, `AnalyzeDeadTimesCommand`, and `WhatsAppWebhookController`**

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=AisyAssistantAndMemoryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai app/Console/Commands app/Http/Controllers/Api tests/Feature/AisyAssistantAndMemoryTest.php routes/api.php
git commit -m "feat: implement 3-tier AI memory, criteria-based assistant router, and dead-time proactive engine"
```

---

### Task 6: Onboarding Flow with Real-Time SSE Provisioning (`/nuevo`), Custom Landing Page (`/{slug}`), and Giro Admin (`/{slug}/admin`)

**Files:**
- Create: `app/Services/Provisioning/BusinessProvisionerService.php`
- Create: `app/Http/Controllers/OnboardingController.php`
- Create: `app/Http/Controllers/BusinessLandingController.php`
- Create: `app/Http/Controllers/BusinessAdminController.php`
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/onboarding/index.blade.php`
- Create: `resources/views/onboarding/progress.blade.php`
- Create: `resources/views/landing/show.blade.php`
- Create: `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/OnboardingAndLayersTest.php`

**Interfaces:**
- Consumes: `Business`, `OdooBusinessService`, `MiiaClient`, `SellerCentralClient`, `WhatsAppGatewayClient`, `GoogleMapsKitService`, `AiMemoryService`, `AisyAssistantService`, `DeadTimeRecommendationService`
- Produces:
  - Routes:
    - `GET /` & `GET /nuevo`: Super-simple onboarding form (`owner_name`, `owner_phone`, `name`, `branch`, `primary_color`, `secondary_color`, `style_preset`, `address`) + live directory of created businesses.
    - `POST /nuevo`: Creates `Business` in `pending` state and redirects to `GET /nuevo/{slug}/progreso`.
    - `GET /nuevo/{slug}/progreso`: Step-by-step live UI connected to SSE endpoint.
    - `GET /nuevo/{slug}/stream`: Server-Sent Events (`text/event-stream`) executing and streaming the 6 provisioning steps in real time:
      1. Creando compañía aislada y almacén en **Odoo Headless** (`res.company`)
      2. Inicializando **Memoria de IA en 3 Niveles** (ADN, colores, estilo y reglas)
      3. Sembrando catálogo inicial del giro (**Estética / Tienda / Comandas**) en Odoo
      4. Vinculando proyecto en **Seller Central (`sc.ceballosleon.com`)** y **Kit Google Maps SEO**
      5. Generando **Landing Page (`/{slug}`)** y **Panel Admin (`/{slug}/admin`)**
      6. Activando instancia y código **QR de WhatsApp** para el Asistente del Dueño
    - `GET /{slug}`: Personalized Landing Page (Layer 1) with dynamic CSS variables for `primary_color` and `secondary_color`, Schema.org `LocalBusiness` JSON-LD, visitor event tracker (`POST /{slug}/event`), active promotions banner, and branch-specific interactive experience:
      - `estetica`: Interactive service menu & appointment booking form (`POST /{slug}/action`).
      - `tienda`: Product storefront with cart & checkout order (`POST /{slug}/action`).
      - `comandas`: Digital QR menu with table/delivery selector & live order placement (`POST /{slug}/action`).
    - `GET /{slug}/admin`: Personalized Giro Admin (Layer 2) with:
      - 1-click Feature Flag toggles (`POST /{slug}/admin/features/toggle`) to turn branch modules on/off instantly.
      - Integrated Assistant Console (text + microphone voice note recording in browser + quick demo prompts) sharing the exact same `AisyAssistantService` brain as WhatsApp.
      - WhatsApp QR pairing card & live connection status + 1-click proactive "Detectar Tiempos Muertos Ahora" trigger.
      - Branch-specific workspace tabs (Agenda & Servicios for `estetica`; POS, Inventario, Estadísticas & Envíos for `tienda`; Comandas KDS en vivo, Mesas & Menú QR for `comandas`).
      - Marketing & Redes tab connected to `ia.ceballosleon.com` (flyer generator) and `sc.ceballosleon.com` (Seller Central social publisher) + Google Maps SEO & 5-Star Review QR card.
      - Activity & 3-Tier AI Memory Inspector (Semantic ADN, Episodic visitor/session logs, and Odoo sync IDs).

- [ ] **Step 1: Write `tests/Feature/OnboardingAndLayersTest.php` covering onboarding creation, SSE provisioning stream, landing page rendering + visitor tracking into Level 2 memory, and admin feature flag toggling**

```php
public function test_onboarding_provisions_all_layers_and_landing_tracks_visitor_memory(): void
{
    $response = $this->post('/nuevo', [
        'owner_name' => 'Sofía Méndez',
        'owner_phone' => '5215544332211',
        'name' => 'Salón Glam Sofía',
        'branch' => 'estetica',
        'primary_color' => '#ec4899',
        'secondary_color' => '#8b5cf6',
        'style_preset' => 'moderno',
        'address' => 'Av. Insurgentes Sur 800, CDMX',
    ]);

    $business = Business::where('slug', 'salon-glam-sofia')->firstOrFail();
    $response->assertRedirect("/nuevo/{$business->slug}/progreso");

    // Run provisioner steps
    app(BusinessProvisionerService::class)->runAllSteps($business);
    $business->refresh();

    $this->assertEquals('ready', $business->provisioning_status);
    $this->assertNotNull($business->odoo_company_id);

    // Layer 1: Landing Page loads with custom colors and JSON-LD
    $landing = $this->get("/{$business->slug}");
    $landing->assertOk();
    $landing->assertSee('Salón Glam Sofía');
    $landing->assertSee('#ec4899');
    $landing->assertSee('application/ld+json', false);

    // Visitor action on landing page is recorded in AI Episodic/Page Memory
    $this->postJson("/{$business->slug}/event", [
        'event_type' => 'click_service',
        'metadata' => ['service' => 'Corte + Brushing'],
    ])->assertOk();

    $this->assertDatabaseHas('page_events', [
        'business_id' => $business->id,
        'event_type' => 'click_service',
    ]);

    // Layer 2: Admin loads and toggles features
    $this->get("/{$business->slug}/admin")->assertOk();
    $this->postJson("/{$business->slug}/admin/features/toggle", [
        'feature' => 'promociones',
        'enabled' => false,
    ])->assertOk();

    $this->assertFalse($business->fresh()->isFeatureEnabled('promociones'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=OnboardingAndLayersTest`
Expected: FAIL because controllers, views, and provisioner service do not exist.

- [ ] **Step 3: Implement `BusinessProvisionerService`, `OnboardingController`, `BusinessLandingController`, `BusinessAdminController`, routes, and Blade views**

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\Apps\xampp\php\php.exe artisan test --filter=OnboardingAndLayersTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Provisioning app/Http/Controllers resources/views routes/web.php tests/Feature/OnboardingAndLayersTest.php
git commit -m "feat: build SSE step-by-step onboarding, custom branch landing pages, and modular admin panel"
```

---

### Task 7: Domain Binding (`aisy.ceballosleon.com` via `tunnel-manager`), Documentation, Vault & GitHub Release

**Files:**
- Create: `start-aisy.cmd`
- Create: `CHANGELOG.md`
- Modify: `D:\Proyectos\tunnel-manager\tunnel-manager.ps1`
- Modify: `D:\Proyectos\tunnel-manager\tunnel-manager-web.py`
- Create: `D:\Proyectos\obsidian-vault\Proyectos\aisy\aisy.md`
- Create: `D:\Proyectos\obsidian-vault\Proyectos\aisy\Bitácora.md`
- Modify: `D:\Proyectos\obsidian-vault\Proyectos\Proyectos.md`

**Interfaces:**
- Produces:
  - Local service `aisy` registered in `tunnel-manager` on port `8095` (auto-starting `.runtime` PostgreSQL `:5439`, Odoo `:8069`, WhatsApp Gateway `:8085`, and Laravel `127.0.0.1:8095`).
  - Cloudflare tunnel `aisy` routing `https://aisy.ceballosleon.com` -> `http://127.0.0.1:8095`.
  - GitHub repository `https://github.com/cristhianelite2/aisy` with `test` and `main` branches pushed.
  - Obsidian vault documentation in `D:\Proyectos\obsidian-vault\Proyectos\aisy\` committed and pushed to `main`.

- [ ] **Step 1: Create `start-aisy.cmd` and register `aisy` in `tunnel-manager`, then create and start the `aisy.ceballosleon.com` Cloudflare tunnel**

Run: `D:\Proyectos\tunnel-manager\tunnel-manager.cmd start-local aisy`
Run: `D:\Proyectos\tunnel-manager\tunnel-manager.cmd start aisy`
Expected: `http://127.0.0.1:8095` and `https://aisy.ceballosleon.com` return HTTP 200.

- [ ] **Step 2: Run full test suite and `git diff --check`**

Run: `D:\Apps\xampp\php\php.exe artisan test`
Run: `git diff --check`
Expected: All tests PASS, zero diff formatting errors.

- [ ] **Step 3: Write `CHANGELOG.md`, commit to `test`, merge `test` -> `main`, create GitHub repo `cristhianelite2/aisy` and push both branches, and register `aisy` in `D:\Proyectos\obsidian-vault`**

Run: `gh repo create cristhianelite2/aisy --public --source=. --push` (or push `test` and `main` to `origin`)
Expected: Both `test` and `main` pushed to GitHub; `obsidian-vault` updated and pushed to `main`.
