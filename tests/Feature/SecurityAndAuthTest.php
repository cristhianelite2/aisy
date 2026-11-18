<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\AisyDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_requires_authentication_and_enforces_ownership_and_security_headers(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Estética Segura',
            'branch' => 'estetica',
        ]);

        // 1. Guest is redirected to /login
        $guestResp = $this->get("/{$business->slug}/admin");
        $guestResp->assertRedirect('/login');
        $guestResp->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $guestResp->assertHeader('X-Content-Type-Options', 'nosniff');

        // 2. Another user gets 403 Forbidden
        $this->actingAs($other)
            ->get("/{$business->slug}/admin")
            ->assertForbidden();

        // 3. Owner gets 200 OK
        $this->actingAs($owner)
            ->get("/{$business->slug}/admin")
            ->assertOk()
            ->assertSee('Estética Segura');
    }

    public function test_demo_seeder_creates_four_businesses_across_branches_and_phases(): void
    {
        $this->seed(AisyDemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'demo@aisy.ceballosleon.com']);
        $this->assertDatabaseHas('businesses', ['slug' => 'estetica-aura-nueva', 'branch' => 'estetica']);
        $this->assertDatabaseHas('businesses', ['slug' => 'salon-lumiere', 'branch' => 'estetica']);
        $this->assertDatabaseHas('businesses', ['slug' => 'boutique-nube', 'branch' => 'tienda']);
        $this->assertDatabaseHas('businesses', ['slug' => 'taqueria-don-pepe', 'branch' => 'comandas']);

        $lumiere = Business::where('slug', 'salon-lumiere')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $lumiere->operations()->where('operation_type', 'appointment')->count());

        $nube = Business::where('slug', 'boutique-nube')->firstOrFail();
        $this->assertGreaterThan(0, $nube->operations()->where('operation_type', 'sale')->sum('total_amount'));

        $pepe = Business::where('slug', 'taqueria-don-pepe')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $pepe->operations()->where('operation_type', 'comanda')->count());
    }
}
