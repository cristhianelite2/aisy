<?php

namespace App\Services\Ai;

use App\Models\AiEpisodicMemory;
use App\Models\Business;
use App\Models\PageEvent;
use App\Services\Odoo\OdooBusinessService;

class AiMemoryService
{
    public function __construct(private readonly OdooBusinessService $odoo) {}

    public function getSemanticMemory(Business $business): array
    {
        $stored = $business->ai_memory_semantic ?? [];

        return array_merge([
            'owner_name' => $business->owner_name,
            'business_name' => $business->name,
            'branch' => $business->branch,
            'primary_color' => $business->primary_color,
            'secondary_color' => $business->secondary_color,
            'style_preset' => $business->style_preset,
            'address' => $business->address,
            'tone' => 'cercano, práctico y orientado a aumentar ventas',
        ], $stored);
    }

    public function updateSemanticMemory(Business $business, array $patch): array
    {
        $merged = array_merge($this->getSemanticMemory($business), $patch);
        $business->ai_memory_semantic = $merged;
        $business->save();

        return $merged;
    }

    public function recordEpisodicEvent(Business $business, string $eventType, string $summary, array $payload = []): AiEpisodicMemory
    {
        return AiEpisodicMemory::create([
            'business_id' => $business->id,
            'event_type' => $eventType,
            'summary' => $summary,
            'payload' => $payload,
        ]);
    }

    public function recordPageEvent(Business $business, string $eventType, array $metadata = [], ?string $sessionId = null): PageEvent
    {
        return PageEvent::create([
            'business_id' => $business->id,
            'event_type' => $eventType,
            'session_id' => $sessionId,
            'metadata' => $metadata,
        ]);
    }

    public function buildPromptContext(Business $business): string
    {
        $semantic = $this->getSemanticMemory($business);
        $metrics = $this->odoo->getBusinessMetrics($business);

        $recentEpisodes = $business->episodicMemories()
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AiEpisodicMemory $m) => "- [{$m->event_type}] {$m->summary}")
            ->implode("\n");

        $pageViews = $business->pageEvents()->where('created_at', '>=', now()->subDays(7))->count();
        $enabledFeatures = collect($business->features ?? [])
            ->filter(fn ($v) => (bool) $v)
            ->keys()
            ->implode(', ');

        return <<<PROMPT
Eres el Asistente Ejecutivo de IA de "{$semantic['business_name']}" (giro: {$semantic['branch']}).
Dueño: {$semantic['owner_name']}. Dirección: {$semantic['address']}.
Colores de marca: {$semantic['primary_color']} y {$semantic['secondary_color']}. Estilo: {$semantic['style_preset']}.
Módulos activos en Admin: {$enabledFeatures}.
Métricas en vivo (Odoo): Ventas hoy \${$metrics['sales_today']} ({$metrics['orders_today']} órdenes), Ventas 7 días \${$metrics['sales_week']}, Citas próximas: {$metrics['upcoming_appointments']}, Comandas activas: {$metrics['active_comandas']}, Visitas landing 7d: {$pageViews}.
Memoria episódica reciente:
{$recentEpisodes}
Regla: Responde breve en español, ejecuta la herramienta correcta con criterio (Odoo para operación/ventas/citas/comandas, MIIA+SellerCentral para anuncios y redes, Google Maps Kit para ficha local) y ofrece confirmación de 1 toque para el cliente final cuando aplique.
PROMPT;
    }
}
