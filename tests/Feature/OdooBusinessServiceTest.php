<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\Odoo\OdooBusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdooBusinessServiceTest extends TestCase
{
    use RefreshDatabase;

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
}
