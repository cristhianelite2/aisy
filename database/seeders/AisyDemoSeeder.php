<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use App\Services\Ai\AiMemoryService;
use App\Services\Odoo\OdooBusinessService;
use App\Services\Provisioning\BusinessProvisionerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AisyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@aisy.ceballosleon.com'],
            [
                'name' => 'Cristhian Ceballos (Demo)',
                'password' => Hash::make('Aisy2026!'),
                'is_admin' => true,
            ]
        );

        $provisioner = app(BusinessProvisionerService::class);
        $odoo = app(OdooBusinessService::class);
        $memory = app(AiMemoryService::class);

        // 1. Estética Nueva (recién creada, 0 ventas, 0 citas)
        $aura = Business::firstOrCreate(
            ['slug' => 'estetica-aura-nueva'],
            [
                'user_id' => $demoUser->id,
                'name' => 'Estética Aura Nueva',
                'branch' => 'estetica',
                'owner_name' => 'Valeria Gómez',
                'owner_phone' => '5215510001001',
                'address' => 'Calle Roma Norte 45, CDMX',
                'primary_color' => '#ec4899',
                'secondary_color' => '#8b5cf6',
                'style_preset' => 'moderno',
                'features' => Business::defaultFeaturesForBranch('estetica'),
                'wa_instance_name' => 'estetica-aura-nueva',
            ]
        );
        $provisioner->runAllSteps($aura);

        // 2. Estética con Reservas y Promociones (agenda activa + promo + eventos)
        $lumiere = Business::firstOrCreate(
            ['slug' => 'salon-lumiere'],
            [
                'user_id' => $demoUser->id,
                'name' => 'Salón Lumière',
                'branch' => 'estetica',
                'owner_name' => 'Camila Torres',
                'owner_phone' => '5215510002002',
                'address' => 'Av. Presidente Masaryk 210, Polanco',
                'primary_color' => '#be185d',
                'secondary_color' => '#f59e0b',
                'style_preset' => 'elegante',
                'features' => Business::defaultFeaturesForBranch('estetica'),
                'wa_instance_name' => 'salon-lumiere',
            ]
        );
        $provisioner->runAllSteps($lumiere);
        if ($lumiere->operations()->count() === 0) {
            $odoo->scheduleAppointment($lumiere, [
                'customer_name' => 'Sofía Herrera',
                'customer_phone' => '5215590001111',
                'service_name' => 'Tinte Completo y Matiz',
                'scheduled_at' => now()->addDays(1)->setTime(11, 30)->toIso8601String(),
                'source' => 'landing',
            ]);
            $odoo->scheduleAppointment($lumiere, [
                'customer_name' => 'Andrea Ruiz',
                'customer_phone' => '5215590002222',
                'service_name' => 'Manicure Gelish',
                'scheduled_at' => now()->addDays(1)->setTime(16, 0)->toIso8601String(),
                'source' => 'whatsapp_audio',
            ]);
            $odoo->createPromotion($lumiere, [
                'title' => '20% en Balayage y Matiz',
                'discount_percent' => 20,
                'description' => 'Válido martes y miércoles reservando desde nuestra landing.',
                'source' => 'ai',
            ]);
            $memory->recordPageEvent($lumiere, 'landing_view', ['source' => 'instagram_bio']);
            $memory->recordPageEvent($lumiere, 'click_item', ['item' => 'Tinte Completo y Matiz']);
        }

        // 3. Tienda con Ventas, Inventario y Envíos
        $nube = Business::firstOrCreate(
            ['slug' => 'boutique-nube'],
            [
                'user_id' => $demoUser->id,
                'name' => 'Boutique Nube',
                'branch' => 'tienda',
                'owner_name' => 'Mariana Castillo',
                'owner_phone' => '5215510003003',
                'address' => 'Av. Chapultepec 320, Guadalajara',
                'primary_color' => '#0ea5e9',
                'secondary_color' => '#1e293b',
                'style_preset' => 'moderno',
                'features' => Business::defaultFeaturesForBranch('tienda'),
                'wa_instance_name' => 'boutique-nube',
            ]
        );
        $provisioner->runAllSteps($nube);
        if ($nube->operations()->count() === 0) {
            $odoo->registerSaleOrOrder($nube, [
                'operation_type' => 'sale',
                'customer_name' => 'Luis Mendoza',
                'items' => [
                    ['name' => 'Producto Estrella', 'qty' => 2, 'price' => 199],
                    ['name' => 'Envío Local Express', 'qty' => 1, 'price' => 45],
                ],
                'source' => 'landing',
            ]);
            $odoo->registerSaleOrOrder($nube, [
                'operation_type' => 'sale',
                'customer_name' => 'Fernanda Gil',
                'items' => [
                    ['name' => 'Combo Ahorro', 'qty' => 1, 'price' => 349],
                ],
                'source' => 'whatsapp_audio',
            ]);
        }

        // 4. Restaurante / Comandas en Hora Pico
        $pepe = Business::firstOrCreate(
            ['slug' => 'taqueria-don-pepe'],
            [
                'user_id' => $demoUser->id,
                'name' => 'Taquería Don Pepe',
                'branch' => 'comandas',
                'owner_name' => 'José Ramírez',
                'owner_phone' => '5215510004004',
                'address' => 'Calz. de Tlalpan 1120, CDMX',
                'primary_color' => '#ef4444',
                'secondary_color' => '#f59e0b',
                'style_preset' => 'vibrante',
                'features' => Business::defaultFeaturesForBranch('comandas'),
                'wa_instance_name' => 'taqueria-don-pepe',
            ]
        );
        $provisioner->runAllSteps($pepe);
        if ($pepe->operations()->count() === 0) {
            $odoo->registerSaleOrOrder($pepe, [
                'operation_type' => 'comanda',
                'status' => 'preparing',
                'table_label' => 'Mesa 2',
                'customer_name' => 'Mesa 2',
                'items' => [
                    ['name' => 'Orden de Tacos Especiales', 'qty' => 2, 'price' => 135],
                    ['name' => 'Agua Fresca 1L', 'qty' => 1, 'price' => 45],
                ],
                'source' => 'landing',
            ]);
            $odoo->registerSaleOrOrder($pepe, [
                'operation_type' => 'comanda',
                'status' => 'ready',
                'table_label' => 'Mesa 5',
                'customer_name' => 'Mesa 5',
                'items' => [
                    ['name' => 'Combo Comida del Día', 'qty' => 3, 'price' => 165],
                ],
                'source' => 'whatsapp_audio',
            ]);
        }
    }
}
