<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\Integrations\GoogleMapsKitService;
use App\Services\Integrations\WhatsAppGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalIntegrationsTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertArrayHasKey('ok', $wa);
    }
}
