<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\Provisioning\BusinessProvisionerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingAndLayersTest extends TestCase
{
    use RefreshDatabase;

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
}
