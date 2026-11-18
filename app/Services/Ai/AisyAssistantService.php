<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Services\Integrations\GoogleMapsKitService;
use App\Services\Integrations\MiiaClient;
use App\Services\Integrations\SellerCentralClient;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Odoo\OdooBusinessService;

class AisyAssistantService
{
    public function __construct(
        private readonly AiMemoryService $memory,
        private readonly OdooBusinessService $odoo,
        private readonly MiiaClient $miia,
        private readonly SellerCentralClient $sellerCentral,
        private readonly WhatsAppGatewayClient $whatsapp,
        private readonly GoogleMapsKitService $gmaps,
    ) {}

    public function handleOwnerInput(Business $business, array $input): array
    {
        $channel = $input['channel'] ?? 'admin_chat';
        $senderPhone = preg_replace('/\D+/', '', (string) ($input['sender_phone'] ?? ''));
        $ownerPhone = preg_replace('/\D+/', '', (string) $business->owner_phone);

        if ($channel === 'whatsapp' && $senderPhone !== '' && $ownerPhone !== '' && !str_ends_with($senderPhone, substr($ownerPhone, -10))) {
            $this->memory->recordEpisodicEvent(
                $business,
                'external_whatsapp_inquiry',
                "Mensaje de número externo ({$senderPhone}): " . mb_substr((string) ($input['text'] ?? ''), 0, 140),
                ['sender_phone' => $senderPhone]
            );

            return [
                'ok' => false,
                'tool_used' => 'unauthorized_sender',
                'reply' => 'Este asistente es exclusivo del dueño del negocio. Tu consulta fue registrada para que el propietario te responda.',
            ];
        }

        $text = trim((string) ($input['text'] ?? ''));
        if ($text === '' && !empty($input['audio_base64'])) {
            $text = $this->miia->transcribeAudio((string) $input['audio_base64'], (string) ($input['audio_mime'] ?? 'audio/ogg'));
        } elseif ($text === '' && !empty($input['audio_path'])) {
            $text = $this->miia->transcribeAudio((string) $input['audio_path'], (string) ($input['audio_mime'] ?? 'audio/ogg'));
        }

        if (!empty($input['location']) && is_array($input['location'])) {
            return $this->executeGoogleMapsTool($business, $input['location'], $input['image_url'] ?? null);
        }

        if ($text === '') {
            return [
                'ok' => true,
                'tool_used' => 'clarify',
                'reply' => 'No recibí texto ni audio claro. Dime qué necesitas (ej. "Cóbrale a Ana un corte de 280", "¿Cuánto vendí hoy?" o "Créame un anuncio").',
            ];
        }

        $toolResult = $this->routeAndExecute($business, $text, $input);

        $this->memory->recordEpisodicEvent(
            $business,
            'assistant_session',
            "Dueño: \"{$text}\" -> [{$toolResult['tool_used']}]",
            ['channel' => $channel, 'tool' => $toolResult['tool_used']]
        );

        if ($channel === 'whatsapp' && $ownerPhone !== '') {
            $this->whatsapp->sendText($business, $ownerPhone, $toolResult['reply']);
        }

        return $toolResult;
    }

    private function routeAndExecute(Business $business, string $text, array $input): array
    {
        $context = $this->memory->buildPromptContext($business);
        $tools = $this->toolDefinitions();

        $llm = $this->miia->chatWithTools([['role' => 'user', 'content' => $text]], $tools, $context);
        if ($llm['ok'] && !empty($llm['message']['tool_calls'][0])) {
            $call = $llm['message']['tool_calls'][0];
            $fn = $call['function']['name'] ?? '';
            $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true) ?: [];

            $dispatched = $this->dispatchTool($business, $fn, $args, $text, $input);
            if ($dispatched !== null) {
                return $dispatched;
            }
        }

        $lower = mb_strtolower($text);

        // 1. Toggle module in Admin
        if (preg_match('/\b(apaga|desactiva|prende|activa|habilita)\b/u', $lower)) {
            return $this->executeToggleFeatureFromText($business, $lower);
        }

        // 2. Marketing / Ad / Social Media -> goes to MIIA + Seller Central (not Admin)
        if (preg_match('/\b(anuncio|flyer|publica|redes|facebook|instagram|tiktok|campaña|imagen)\b/u', $lower)) {
            return $this->executeMarketingAdTool($business, $text, $input);
        }

        // 3. Google Maps Kit
        if (preg_match('/\b(google maps|maps|reseñas|ficha|ubicación)\b/u', $lower)) {
            return $this->executeGoogleMapsTool($business, $input['location'] ?? null, $input['image_url'] ?? null);
        }

        // 4. Promotion in Odoo + Landing
        if (preg_match('/\b(promoción|promocion|descuento|2x1|cupón|cupon|oferta)\b/u', $lower)) {
            return $this->executePromotionTool($business, $text);
        }

        // 5. Appointment (Estética)
        if (preg_match('/\b(agenda|agénd|cita|reserva)\b/u', $lower)) {
            return $this->executeAppointmentTool($business, $text);
        }

        // 6. Kitchen order (Comandas)
        if (preg_match('/\b(comanda|mesa|cocina|platillo)\b/u', $lower) || ($business->branch === 'comandas' && preg_match('/\b(orden|pide|prepara)\b/u', $lower))) {
            return $this->executeSaleOrComandaTool($business, $text, 'comanda', $input['channel'] ?? 'whatsapp');
        }

        // 7. POS / Voice Sale
        if (preg_match('/\b(cobra|cóbrale|cobrale|vende|venta|ticket|pagó|pago|efectivo|tarjeta)\b/u', $lower) || preg_match('/\d+\s*(pesos|\$)?/u', $lower)) {
            return $this->executeSaleOrComandaTool($business, $text, 'sale', $input['channel'] ?? 'whatsapp');
        }

        // 8. Query metrics / sales / summary
        if (preg_match('/\b(cuánto|cuanto|vendí|vendi|ventas|resumen|estadísticas|estadisticas|hoy|citas|reporte)\b/u', $lower)) {
            return $this->executeMetricsTool($business);
        }

        // Fallback: general AI reply with business context
        $metrics = $this->odoo->getBusinessMetrics($business);

        return [
            'ok' => true,
            'tool_used' => 'assistant_chat',
            'reply' => "Hola {$business->owner_name}, hoy {$business->name} lleva \${$metrics['sales_today']} en {$metrics['orders_today']} órdenes. Puedes pedirme cobrar una venta por voz, agendar una cita, crear un anuncio para redes o consultar tus métricas.",
        ];
    }

    private function dispatchTool(Business $business, string $fn, array $args, string $rawText, array $input): ?array
    {
        return match ($fn) {
            'odoo_register_sale' => $this->executeSaleOrComandaTool($business, $rawText, 'sale', $input['channel'] ?? 'whatsapp', $args),
            'odoo_create_kitchen_order' => $this->executeSaleOrComandaTool($business, $rawText, 'comanda', $input['channel'] ?? 'whatsapp', $args),
            'odoo_schedule_appointment' => $this->executeAppointmentTool($business, $rawText, $args),
            'odoo_query_metrics' => $this->executeMetricsTool($business),
            'odoo_create_promotion' => $this->executePromotionTool($business, $rawText, $args),
            'admin_toggle_feature' => $this->executeToggleFeature($business, (string) ($args['feature'] ?? ''), (bool) ($args['enabled'] ?? true)),
            'marketing_generate_and_publish_ad' => $this->executeMarketingAdTool($business, $args['offer_text'] ?? $rawText, $input),
            'google_maps_update_kit' => $this->executeGoogleMapsTool($business, $input['location'] ?? null, $input['image_url'] ?? null),
            default => null,
        };
    }

    private function executeSaleOrComandaTool(Business $business, string $text, string $opType, string $channel, array $args = []): array
    {
        $items = $args['items'] ?? $this->parseItemsFromText($text);
        $customerName = $args['customer_name'] ?? $this->parseCustomerName($text);
        $tableLabel = $args['table_label'] ?? $this->parseTableLabel($text);

        $op = $this->odoo->registerSaleOrOrder($business, [
            'operation_type' => $opType,
            'customer_name' => $customerName,
            'table_label' => $tableLabel,
            'items' => $items,
            'source' => $channel === 'whatsapp' ? 'whatsapp_audio' : 'admin',
            'notes' => $text,
        ]);

        $totalFormatted = number_format($op->total_amount, 2);
        $confirmMsg = "¡Hola {$op->customer_name}! Gracias por tu compra en {$business->name}. Total registrado: \${$totalFormatted}. Puedes ver nuestro catálogo y promos en " . url("/{$business->slug}");
        $confirmUrl = $this->whatsapp->buildClickToChatLink($op->customer_phone ?: $business->owner_phone, $confirmMsg);

        $label = $opType === 'comanda' ? "Comanda #{$op->id}" . ($tableLabel ? " ({$tableLabel})" : '') : "Venta #{$op->id}";

        return [
            'ok' => true,
            'tool_used' => $opType === 'comanda' ? 'odoo_create_kitchen_order' : 'odoo_register_sale',
            'operation_id' => $op->id,
            'total_amount' => $op->total_amount,
            'customer_confirmation_url' => $confirmUrl,
            'reply' => "✅ {$label} registrada en Odoo por \${$totalFormatted} para {$op->customer_name}. ¿Quieres enviarle su comprobante? Toca aquí: {$confirmUrl}",
        ];
    }

    private function executeAppointmentTool(Business $business, string $text, array $args = []): array
    {
        $customerName = $args['customer_name'] ?? $this->parseCustomerName($text);
        $serviceName = $args['service_name'] ?? ($business->catalogItems()->first()?->name ?? 'Cita de Servicio');

        $op = $this->odoo->scheduleAppointment($business, [
            'customer_name' => $customerName,
            'service_name' => $serviceName,
            'scheduled_at' => $args['scheduled_at'] ?? now()->addDay()->setTime(16, 0)->toIso8601String(),
            'source' => 'whatsapp_audio',
            'notes' => $text,
        ]);

        $dateLabel = $op->scheduled_at?->format('d/m H:i') ?? 'mañana';
        $confirmMsg = "¡Hola {$op->customer_name}! Tu cita en {$business->name} ({$serviceName}) quedó agendada para el {$dateLabel}. Te esperamos en {$business->address}.";
        $confirmUrl = $this->whatsapp->buildClickToChatLink($business->owner_phone, $confirmMsg);

        return [
            'ok' => true,
            'tool_used' => 'odoo_schedule_appointment',
            'operation_id' => $op->id,
            'customer_confirmation_url' => $confirmUrl,
            'reply' => "📅 Cita #{$op->id} agendada en Odoo para {$op->customer_name} ({$serviceName}) el {$dateLabel}. Confírmale en 1 toque: {$confirmUrl}",
        ];
    }

    private function executeMetricsTool(Business $business): array
    {
        $m = $this->odoo->getBusinessMetrics($business);

        return [
            'ok' => true,
            'tool_used' => 'odoo_query_metrics',
            'metrics' => $m,
            'reply' => "📊 Resumen de {$business->name} (Odoo en vivo):\n• Ventas hoy: \$" . number_format($m['sales_today'], 2) . " ({$m['orders_today']} órdenes)\n• Ventas 7 días: \$" . number_format($m['sales_week'], 2) . "\n• Citas próximas: {$m['upcoming_appointments']} | Comandas activas: {$m['active_comandas']}",
        ];
    }

    private function executePromotionTool(Business $business, string $text, array $args = []): array
    {
        $discount = (float) ($args['discount_percent'] ?? 20);
        if (preg_match('/(\d+)\s*%/u', $text, $m)) {
            $discount = (float) $m[1];
        }

        $title = $args['title'] ?? mb_substr($text, 0, 60);
        $op = $this->odoo->createPromotion($business, [
            'title' => $title,
            'discount_percent' => $discount,
            'description' => $text,
            'source' => 'ai',
        ]);

        return [
            'ok' => true,
            'tool_used' => 'odoo_create_promotion',
            'operation_id' => $op->id,
            'reply' => "🏷️ Promoción #{$op->id} creada en Odoo y activada en tu Landing (" . url("/{$business->slug}") . "): {$title}. ¿Quieres que también genere un anuncio visual y lo programe en Seller Central?",
        ];
    }

    private function executeMarketingAdTool(Business $business, string $text, array $input = []): array
    {
        $imagePrompt = "Promotional social media flyer for {$business->branch} business '{$business->name}', brand colors {$business->primary_color} and {$business->secondary_color}, offer: {$text}";
        $img = $this->miia->generateImage($imagePrompt, $business, $text);
        $mediaUrl = $img['url'] ?? '';

        $caption = "✨ {$business->name}: {$text}\n📍 {$business->address}\n👉 Reserva o pide aquí: " . url("/{$business->slug}");

        $scResult = $this->sellerCentral->scheduleSocialPost($business, [
            'title' => mb_substr($text, 0, 70),
            'caption' => $caption,
            'media_url' => str_starts_with($mediaUrl, 'http') ? $mediaUrl : url($mediaUrl),
            'platforms' => ['facebook', 'instagram', 'tiktok'],
        ]);

        return [
            'ok' => true,
            'tool_used' => 'marketing_generate_and_publish_ad',
            'media_url' => $mediaUrl,
            'sc_result' => $scResult,
            'reply' => "🎨 Anuncio generado con IA ({$img['provider']}) usando los colores de {$business->name} y enviado a Seller Central (Proyecto #{$scResult['sc_project_id']}).\nConecta o revisa tus redes aquí: {$scResult['connect_url']}",
        ];
    }

    private function executeGoogleMapsTool(Business $business, ?array $coords, ?string $facadePhoto): array
    {
        $kit = $this->gmaps->generateKit($business, $coords, $facadePhoto);

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'gmaps',
            'title' => 'Kit Google Maps actualizado',
            'details' => $kit['seo_title'],
        ]);

        return [
            'ok' => true,
            'tool_used' => 'google_maps_update_kit',
            'gmaps_kit' => $kit,
            'reply' => "📍 Kit Google Maps actualizado para {$business->name}.\n• Título SEO: {$kit['seo_title']}\n• Alta directa: {$kit['create_url']}\n• QR de reseñas 5★ listo en tu Admin: " . url("/{$business->slug}/admin"),
        ];
    }

    private function executeToggleFeatureFromText(Business $business, string $lower): array
    {
        $enable = !preg_match('/\b(apaga|desactiva)\b/u', $lower);
        $map = [
            'agenda' => 'agenda',
            'citas' => 'agenda',
            'pos' => $business->branch === 'tienda' ? 'pos' : 'ventas_pos',
            'envio' => 'envios',
            'envíos' => 'envios',
            'comanda' => 'comandas_kds',
            'cocina' => 'comandas_kds',
            'menu' => 'menu_qr',
            'menú' => 'menu_qr',
            'mesa' => 'mesas',
            'promo' => 'promociones',
            'anuncio' => 'anuncios_ia',
            'maps' => 'google_maps',
        ];

        foreach ($map as $keyword => $featureKey) {
            if (str_contains($lower, $keyword)) {
                return $this->executeToggleFeature($business, $featureKey, $enable);
            }
        }

        return [
            'ok' => true,
            'tool_used' => 'admin_toggle_feature',
            'reply' => 'Indícame qué módulo quieres encender o apagar (ej. agenda, envíos, menú QR, promociones).',
        ];
    }

    private function executeToggleFeature(Business $business, string $feature, bool $enabled): array
    {
        $business->toggleFeature($feature, $enabled);
        $stateLabel = $enabled ? 'ENCENDIDO' : 'APAGADO';

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'admin',
            'title' => "Módulo {$feature}: {$stateLabel}",
            'details' => 'Actualizado por el Asistente IA',
        ]);

        return [
            'ok' => true,
            'tool_used' => 'admin_toggle_feature',
            'feature' => $feature,
            'enabled' => $enabled,
            'reply' => "⚙️ Módulo `{$feature}` ahora está {$stateLabel} en tu panel y landing.",
        ];
    }

    private function parseItemsFromText(string $text): array
    {
        $items = [];
        // Match patterns like "un corte de 250" or "2 tacos de 45"
        if (preg_match_all('/(?:(\d+)\s+)?([a-záéíóúñ\s]{3,35}?)\s+(?:de|por|a)\s+\$?(\d+(?:\.\d{1,2})?)/iu', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $qty = $m[1] !== '' ? max(1, (int) $m[1]) : 1;
                $rawName = trim(preg_replace('/^(?:cóbrale|cobrale|vende|un|una|unos|unas|y|el|la|a\s+\w+)\s+/iu', '', trim($m[2])));
                $price = (float) $m[3];
                if ($rawName !== '' && $price > 0) {
                    $items[] = [
                        'name' => mb_convert_case($rawName, MB_CASE_TITLE, 'UTF-8'),
                        'qty' => $qty,
                        'price' => $price,
                    ];
                }
            }
        }

        if (empty($items)) {
            $price = 150.0;
            if (preg_match('/\$?(\d+(?:\.\d{1,2})?)/', $text, $pm)) {
                $price = (float) $pm[1];
            }
            $items[] = ['name' => 'Venta Rápida', 'qty' => 1, 'price' => $price];
        }

        return $items;
    }

    private function parseCustomerName(string $text): string
    {
        if (preg_match('/\b(?:a|para)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)?)/u', $text, $m)) {
            return trim($m[1]);
        }

        return 'Cliente Mostrador';
    }

    private function parseTableLabel(string $text): ?string
    {
        if (preg_match('/\b(mesa\s*\d+)\b/iu', $text, $m)) {
            return mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8');
        }

        return null;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odoo_register_sale',
                    'description' => 'Register a POS sale or service charge in Odoo from text or voice.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_name' => ['type' => 'string'],
                            'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odoo_schedule_appointment',
                    'description' => 'Schedule an appointment in Odoo calendar.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_name' => ['type' => 'string'],
                            'service_name' => ['type' => 'string'],
                            'scheduled_at' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'marketing_generate_and_publish_ad',
                    'description' => 'Generate a branded promotional ad via ia.ceballosleon.com and publish/schedule in sc.ceballosleon.com.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'offer_text' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odoo_query_metrics',
                    'description' => 'Query live sales, orders, and appointments metrics from Odoo.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
        ];
    }
}
