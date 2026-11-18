<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Odoo\OdooBusinessService;

class DeadTimeRecommendationService
{
    public function __construct(
        private readonly OdooBusinessService $odoo,
        private readonly AiMemoryService $memory,
        private readonly WhatsAppGatewayClient $whatsapp,
    ) {}

    public function analyzeAndRecommend(Business $business, bool $sendWhatsApp = true): array
    {
        $metrics = $this->odoo->getBusinessMetrics($business);
        $pageViews = $business->pageEvents()->where('created_at', '>=', now()->subDays(7))->count();

        $isDeadTime = $metrics['orders_today'] === 0 || ($business->branch === 'estetica' && $metrics['upcoming_appointments'] < 2);
        if (!$isDeadTime) {
            return [
                'recommended' => false,
                'reason' => 'healthy_demand',
                'metrics' => $metrics,
            ];
        }

        $suggestion = match ($business->branch) {
            'estetica' => "Hola {$business->owner_name}, noté que hoy tienes espacios libres en la agenda de {$business->name}. ¿Lanzamos un 20% en corte o color para reactivar clientes? Mándame una foto de tu local o responde \"Crea el anuncio\" y lo publico en tus redes.",
            'comandas' => "Hola {$business->owner_name}, la hora está tranquila en {$business->name} ({$metrics['orders_today']} comandas hoy). Mándame una foto de tu platillo estrella y te armo un combo promocional + post automático en redes.",
            default => "Hola {$business->owner_name}, detecté tiempo muerto en {$business->name} (visitas web: {$pageViews}, ventas hoy: {$metrics['orders_today']}). ¿Creamos una promo relámpago de envío gratis o 15% hoy?",
        };

        $this->memory->recordEpisodicEvent(
            $business,
            'dead_time_recommendation',
            $suggestion,
            ['metrics' => $metrics, 'page_views_7d' => $pageViews]
        );

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'whatsapp',
            'title' => 'Recomendación proactiva por tiempo muerto',
            'details' => $suggestion,
        ]);

        if ($sendWhatsApp && $business->owner_phone) {
            $this->whatsapp->sendText($business, $business->owner_phone, $suggestion);
        }

        return [
            'recommended' => true,
            'message' => $suggestion,
            'metrics' => $metrics,
        ];
    }
}
