<?php

namespace App\Services\Provisioning;

use App\Models\Business;
use App\Services\Ai\AiMemoryService;
use App\Services\Integrations\GoogleMapsKitService;
use App\Services\Integrations\SellerCentralClient;
use App\Services\Integrations\WhatsAppGatewayClient;
use App\Services\Odoo\OdooBusinessService;

class BusinessProvisionerService
{
    public function __construct(
        private readonly OdooBusinessService $odoo,
        private readonly AiMemoryService $memory,
        private readonly SellerCentralClient $sellerCentral,
        private readonly GoogleMapsKitService $gmaps,
        private readonly WhatsAppGatewayClient $whatsapp,
    ) {}

    public function runAllSteps(Business $business, ?callable $onStep = null): Business
    {
        if ($business->provisioning_status === 'ready') {
            $steps = $business->provisioning_steps ?? [];
            if ($onStep) {
                foreach ($steps as $step) {
                    $onStep($step);
                }
            }

            return $business;
        }

        $business->provisioning_status = 'running';
        $business->save();

        $stepsLog = [];
        $emit = function (int $stepNum, string $title, string $detail, string $status = 'done') use ($business, &$stepsLog, $onStep): void {
            $entry = [
                'step' => $stepNum,
                'title' => $title,
                'detail' => $detail,
                'status' => $status,
                'at' => now()->toIso8601String(),
            ];
            $stepsLog[] = $entry;
            $business->provisioning_steps = $stepsLog;
            $business->save();

            if ($onStep) {
                $onStep($entry);
            }
        };

        // Step 1: Odoo Multi-Company + Catalog Seed
        $odooRes = $this->odoo->provisionCompany($business);
        $emit(1, 'Odoo Headless Multi-Company listo', "Compañía aislada #{$odooRes['company_id']} y {$odooRes['seeded_items']} ítems base creados.");

        // Step 2: 3-Tier AI Memory
        $this->memory->updateSemanticMemory($business, [
            'owner_name' => $business->owner_name,
            'business_name' => $business->name,
            'branch' => $business->branch,
            'primary_color' => $business->primary_color,
            'secondary_color' => $business->secondary_color,
            'style_preset' => $business->style_preset,
            'address' => $business->address,
        ]);
        $this->memory->recordEpisodicEvent($business, 'onboarding', "Negocio {$business->name} dado de alta por {$business->owner_name}.");
        $emit(2, 'Memoria de IA en 3 Niveles inicializada', 'ADN de marca, colores, estilo y bitácora episódica activados.');

        // Step 3: Seller Central + Google Maps Kit
        $scId = $this->sellerCentral->ensureProjectForBusiness($business);
        $kit = $this->gmaps->generateKit($business);
        $emit(3, 'Seller Central y Kit Google Maps conectados', "Proyecto SC #{$scId} vinculado y Schema.org {$kit['category']} generado.");

        // Step 4: Landing & Admin URLs
        $landingUrl = url("/{$business->slug}");
        $adminUrl = url("/{$business->slug}/admin");
        $emit(4, 'Landing Page y Panel Admin publicados', "Landing: {$landingUrl} · Admin: {$adminUrl}");

        // Step 5: WhatsApp QR Instance
        $waRes = $this->whatsapp->createInstance($business);
        $waDetail = ($waRes['offline'] ?? false)
            ? "Instancia '{$business->slug}' preparada (esperando servicio WhatsApp Gateway :8085)."
            : "Instancia '{$business->slug}' activa y lista para escanear QR.";
        $emit(5, 'Asistente WhatsApp aprovisionado', $waDetail);

        $business->provisioning_status = 'ready';
        $business->save();

        return $business->fresh();
    }
}
