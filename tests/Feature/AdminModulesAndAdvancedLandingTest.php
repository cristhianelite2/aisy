<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\Odoo\OdooBusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulesAndAdvancedLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_modules_are_usable_and_advanced_mode_customizes_landing(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Estética Mimosa',
            'branch' => 'estetica',
            'primary_color' => '#ec4899',
            'secondary_color' => '#8b5cf6',
        ]);
        app(OdooBusinessService::class)->provisionCompany($business);

        $this->assertFalse((bool) $business->advanced_mode);

        // 1. Admin renders interactive workspaces for enabled modules
        $this->actingAs($owner)
            ->get("/{$business->slug}/admin")
            ->assertOk()
            ->assertSee('Agendar Nueva Cita')
            ->assertSee('Cobro Rápido / POS')
            ->assertSee('Crear Promoción');

        // 2. Owner can use POS, Agenda, and Promociones directly from Admin
        $this->actingAs($owner)
            ->postJson("/{$business->slug}/admin/pos-sale", [
                'customer_name' => 'Lucía',
                'item_name' => 'Corte + Peinado',
                'qty' => 1,
                'price' => 320,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($owner)
            ->postJson("/{$business->slug}/admin/appointments", [
                'customer_name' => 'Elena',
                'service_name' => 'Tinte Completo',
                'scheduled_at' => now()->addDay()->setTime(15, 0)->format('Y-m-d\TH:i'),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($owner)
            ->postJson("/{$business->slug}/admin/promotions", [
                'title' => 'Promo Mimosa 25%',
                'discount_percent' => 25,
                'description' => 'Solo esta semana',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('business_operations', ['business_id' => $business->id, 'operation_type' => 'sale', 'total_amount' => 320]);
        $this->assertDatabaseHas('business_operations', ['business_id' => $business->id, 'operation_type' => 'appointment', 'customer_name' => 'Elena']);
        $this->assertDatabaseHas('business_operations', ['business_id' => $business->id, 'operation_type' => 'promotion', 'customer_name' => 'Promo Mimosa 25%']);

        // 3. Advanced mode unlocks full landing customization and reflects on /{slug}
        $this->actingAs($owner)
            ->postJson("/{$business->slug}/admin/landing-config", [
                'advanced_mode' => true,
                'primary_color' => '#14b8a6',
                'secondary_color' => '#0f172a',
                'landing_config' => [
                    'hero_headline' => 'Tu Spa Mimosa VIP',
                    'hero_subheadline' => 'Atención de lujo personalizada',
                    'show_gallery' => false,
                    'show_reviews' => true,
                    'business_hours' => 'Lun-Sáb 9am - 8pm',
                ],
            ])
            ->assertOk();

        $this->assertTrue((bool) $business->fresh()->advanced_mode);

        $this->get("/{$business->slug}")
            ->assertOk()
            ->assertSee('Tu Spa Mimosa VIP')
            ->assertSee('#14b8a6')
            ->assertSee('Lun-Sáb 9am - 8pm')
            ->assertDontSee('Conoce nuestro estilo');

        // 4. Comandas & Tienda branches expose all their toggleable module panels
        $comandas = Business::factory()->create(['user_id' => $owner->id, 'name' => 'Taquería Test', 'branch' => 'comandas']);
        $this->actingAs($owner)
            ->get("/{$comandas->slug}/admin")
            ->assertOk()
            ->assertSee('Menú Digital QR')
            ->assertSee('Pantalla de Cocina (KDS)')
            ->assertSee('Control de Mesas');

        $tienda = Business::factory()->create(['user_id' => $owner->id, 'name' => 'Tienda Test', 'branch' => 'tienda']);
        $this->actingAs($owner)
            ->get("/{$tienda->slug}/admin")
            ->assertOk()
            ->assertSee('Catálogo e Inventario')
            ->assertSee('Estadísticas e Insights IA')
            ->assertSee('Gestión de Envíos');
    }
}
