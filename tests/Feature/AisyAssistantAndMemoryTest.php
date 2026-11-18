<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\Ai\AisyAssistantService;
use App\Services\Ai\DeadTimeRecommendationService;
use App\Services\Odoo\OdooBusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AisyAssistantAndMemoryTest extends TestCase
{
    use RefreshDatabase;

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

        // 4. Dead-time recommendation engine generates actionable message
        $rec = app(DeadTimeRecommendationService::class)->analyzeAndRecommend($business->fresh(), false);
        $this->assertTrue($rec['recommended']);
        $this->assertNotEmpty($rec['message']);
    }
}
