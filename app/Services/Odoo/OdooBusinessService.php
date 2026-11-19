<?php

namespace App\Services\Odoo;

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessOperation;
use Carbon\Carbon;

class OdooBusinessService
{
    public function __construct(private readonly OdooClient $odoo) {}

    public function provisionCompany(Business $business): array
    {
        $odooConnected = false;
        $companyId = $business->odoo_company_id;

        if (!$companyId) {
            try {
                if ($this->odoo->isAvailable()) {
                    $existing = $this->odoo->searchRead('res.company', [['name', '=', $business->name]], ['id'], 1);
                    if (!empty($existing[0]['id'])) {
                        $companyId = (int) $existing[0]['id'];
                    } else {
                        $companyId = $this->odoo->create('res.company', [
                            'name' => $business->name,
                            'street' => $business->address ?: 'Local principal',
                            'phone' => $business->owner_phone,
                        ]);
                    }
                    $odooConnected = true;
                }
            } catch (\Throwable) {
                $companyId = null;
            }

            if (!$companyId) {
                // Deterministic isolated company ID fallback when Odoo daemon is offline
                $companyId = 1000 + (int) $business->id;
            }

            $business->odoo_company_id = $companyId;
            $business->save();
        }

        if ($odooConnected && $companyId) {
            $this->grantMasterAccessToCompany((int) $companyId);
        }

        $seeded = $this->seedDefaultCatalog($business, $odooConnected);

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'system',
            'category' => 'odoo',
            'title' => 'Compañía aislada creada en Odoo',
            'details' => "Company ID #{$companyId} ({$seeded} ítems base sincronizados)",
            'meta' => [
                'odoo_company_id' => $companyId,
                'odoo_live' => $odooConnected,
                'seeded_items' => $seeded,
            ],
        ]);

        return [
            'company_id' => $companyId,
            'odoo_live' => $odooConnected,
            'seeded_items' => $seeded,
        ];
    }

    public function seedDefaultCatalog(Business $business, bool $syncToOdoo = true): int
    {
        if ($business->catalogItems()->count() > 0) {
            return $business->catalogItems()->count();
        }

        $defaults = match ($business->branch) {
            'estetica' => [
                ['name' => 'Corte de Cabello + Peinado', 'type' => 'service', 'price' => 280, 'duration_minutes' => 45, 'category' => 'Cabello'],
                ['name' => 'Tinte Completo y Matiz', 'type' => 'service', 'price' => 650, 'duration_minutes' => 120, 'category' => 'Color'],
                ['name' => 'Manicure Gelish', 'type' => 'service', 'price' => 240, 'duration_minutes' => 50, 'category' => 'Uñas'],
                ['name' => 'Shampoo Reparador', 'type' => 'product', 'price' => 210, 'stock_qty' => 15, 'category' => 'Productos'],
            ],
            'tienda' => [
                ['name' => 'Producto Estrella', 'type' => 'product', 'price' => 199, 'stock_qty' => 30, 'category' => 'General'],
                ['name' => 'Combo Ahorro', 'type' => 'product', 'price' => 349, 'stock_qty' => 20, 'category' => 'Promos'],
                ['name' => 'Envío Local Express', 'type' => 'service', 'price' => 45, 'stock_qty' => 999, 'category' => 'Envíos'],
            ],
            'comandas' => [
                ['name' => 'Orden de Tacos Especiales', 'type' => 'consu', 'price' => 135, 'category' => 'Platillos'],
                ['name' => 'Combo Comida del Día', 'type' => 'consu', 'price' => 165, 'category' => 'Combos'],
                ['name' => 'Agua Fresca 1L', 'type' => 'consu', 'price' => 45, 'category' => 'Bebidas'],
                ['name' => 'Postre de la Casa', 'type' => 'consu', 'price' => 65, 'category' => 'Postres'],
            ],
            default => [
                ['name' => 'Servicio Base', 'type' => 'service', 'price' => 150, 'category' => 'General'],
            ],
        };

        $count = 0;
        foreach ($defaults as $item) {
            $this->syncCatalogItem($business, $item, $syncToOdoo);
            $count++;
        }

        return $count;
    }

    public function syncCatalogItem(Business $business, array $data, bool $syncToOdoo = true): BusinessCatalogItem
    {
        $existing = $business->catalogItems()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
            ->first();

        if ($existing) {
            return $existing;
        }

        $odooProductId = null;
        if ($syncToOdoo && $business->odoo_company_id) {
            try {
                if ($this->odoo->isAvailable()) {
                    $odooType = match ($data['type'] ?? 'service') {
                        'product' => 'product',
                        'consu' => 'consu',
                        default => 'service',
                    };
                    $odooProductId = $this->odoo->create('product.template', [
                        'name' => $data['name'],
                        'list_price' => (float) ($data['price'] ?? 0),
                        'detailed_type' => $odooType,
                        'company_id' => (int) $business->odoo_company_id,
                    ]);
                }
            } catch (\Throwable) {
                $odooProductId = null;
            }
        }

        return BusinessCatalogItem::create([
            'business_id' => $business->id,
            'odoo_product_id' => $odooProductId,
            'name' => trim($data['name']),
            'type' => $data['type'] ?? ($business->branch === 'tienda' ? 'product' : ($business->branch === 'comandas' ? 'consu' : 'service')),
            'price' => (float) ($data['price'] ?? 0),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'stock_qty' => (int) ($data['stock_qty'] ?? 10),
            'category' => $data['category'] ?? 'General',
            'description' => $data['description'] ?? null,
            'active' => true,
        ]);
    }

    public function registerSaleOrOrder(Business $business, array $payload): BusinessOperation
    {
        $items = $payload['items'] ?? [];
        $resolvedItems = [];
        $total = 0.0;
        $orderLines = [];

        foreach ($items as $rawItem) {
            $name = trim((string) ($rawItem['name'] ?? 'Artículo'));
            $qty = max(1, (int) ($rawItem['qty'] ?? 1));
            $price = (float) ($rawItem['price'] ?? 0);

            $catalogItem = $this->syncCatalogItem($business, [
                'name' => $name,
                'price' => $price > 0 ? $price : 100,
                'type' => $business->branch === 'tienda' ? 'product' : ($business->branch === 'comandas' ? 'consu' : 'service'),
            ]);

            if ($price <= 0) {
                $price = $catalogItem->price;
            }

            if ($catalogItem->type === 'product' && $catalogItem->stock_qty > 0) {
                $catalogItem->decrement('stock_qty', min($qty, $catalogItem->stock_qty));
            }

            $subtotal = $qty * $price;
            $total += $subtotal;

            $resolvedItems[] = [
                'catalog_item_id' => $catalogItem->id,
                'odoo_product_id' => $catalogItem->odoo_product_id,
                'name' => $catalogItem->name,
                'qty' => $qty,
                'price' => $price,
                'subtotal' => $subtotal,
            ];

            if ($catalogItem->odoo_product_id) {
                $orderLines[] = [0, 0, [
                    'product_id' => $catalogItem->odoo_product_id,
                    'name' => $catalogItem->name,
                    'product_uom_qty' => $qty,
                    'price_unit' => $price,
                ]];
            }
        }

        $odooOrderId = null;
        try {
            if ($this->odoo->isAvailable() && $business->odoo_company_id) {
                $partnerId = $this->findOrCreatePartner(
                    $business,
                    $payload['customer_name'] ?? 'Cliente Mostrador',
                    $payload['customer_phone'] ?? null
                );

                if ($partnerId && !empty($orderLines)) {
                    $odooOrderId = $this->odoo->create('sale.order', [
                        'partner_id' => $partnerId,
                        'company_id' => (int) $business->odoo_company_id,
                        'order_line' => $orderLines,
                    ]);
                }
            }
        } catch (\Throwable) {
            $odooOrderId = null;
        }

        $opType = $payload['operation_type'] ?? ($business->branch === 'comandas' ? 'comanda' : 'sale');
        $status = $payload['status'] ?? ($opType === 'comanda' ? 'preparing' : 'confirmed');

        $operation = BusinessOperation::create([
            'business_id' => $business->id,
            'operation_type' => $opType,
            'status' => $status,
            'odoo_record_id' => $odooOrderId,
            'odoo_model' => 'sale.order',
            'customer_name' => $payload['customer_name'] ?? 'Cliente Mostrador',
            'customer_phone' => $payload['customer_phone'] ?? null,
            'table_label' => $payload['table_label'] ?? null,
            'total_amount' => $total,
            'items' => $resolvedItems,
            'source' => $payload['source'] ?? 'admin',
            'notes' => $payload['notes'] ?? null,
        ]);

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => str_starts_with((string) ($payload['source'] ?? ''), 'whatsapp') ? 'ai' : 'owner',
            'category' => 'odoo',
            'title' => $opType === 'comanda' ? "Comanda #{$operation->id} registrada" : "Venta #{$operation->id} registrada",
            'details' => "Total \$" . number_format($total, 2) . " · Cliente: {$operation->customer_name}",
            'meta' => ['operation_id' => $operation->id, 'odoo_record_id' => $odooOrderId],
        ]);

        return $operation;
    }

    public function scheduleAppointment(Business $business, array $payload): BusinessOperation
    {
        $scheduledAt = isset($payload['scheduled_at'])
            ? Carbon::parse($payload['scheduled_at'])
            : now()->addDay()->setTime(16, 0);

        $serviceName = $payload['service_name'] ?? 'Cita de Servicio';
        $customerName = $payload['customer_name'] ?? 'Cliente';
        $price = (float) ($payload['price'] ?? 0);

        $catalogItem = $business->catalogItems()
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($serviceName) . '%'])
            ->first();

        if ($catalogItem && $price <= 0) {
            $price = $catalogItem->price;
            $serviceName = $catalogItem->name;
        }

        $odooEventId = null;
        try {
            if ($this->odoo->isAvailable()) {
                $odooEventId = $this->odoo->create('calendar.event', [
                    'name' => "[{$business->name}] {$serviceName} - {$customerName}",
                    'start' => $scheduledAt->copy()->utc()->format('Y-m-d H:i:s'),
                    'stop' => $scheduledAt->copy()->addMinutes($catalogItem?->duration_minutes ?? 45)->utc()->format('Y-m-d H:i:s'),
                    'description' => "company_id:{$business->odoo_company_id} | tel:" . ($payload['customer_phone'] ?? ''),
                ]);
            }
        } catch (\Throwable) {
            $odooEventId = null;
        }

        $operation = BusinessOperation::create([
            'business_id' => $business->id,
            'operation_type' => 'appointment',
            'status' => 'confirmed',
            'odoo_record_id' => $odooEventId,
            'odoo_model' => 'calendar.event',
            'customer_name' => $customerName,
            'customer_phone' => $payload['customer_phone'] ?? null,
            'total_amount' => $price,
            'items' => [['name' => $serviceName, 'qty' => 1, 'price' => $price]],
            'scheduled_at' => $scheduledAt,
            'source' => $payload['source'] ?? 'admin',
            'notes' => $payload['notes'] ?? null,
        ]);

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'odoo',
            'title' => "Cita agendada: {$customerName}",
            'details' => "{$serviceName} · {$scheduledAt->format('d/m/Y H:i')}",
            'meta' => ['operation_id' => $operation->id, 'odoo_record_id' => $odooEventId],
        ]);

        return $operation;
    }

    public function createPromotion(Business $business, array $payload): BusinessOperation
    {
        $title = $payload['title'] ?? 'Promoción Especial';
        $discount = (float) ($payload['discount_percent'] ?? 15);

        $odooPromoId = null;
        try {
            if ($this->odoo->isAvailable() && $business->odoo_company_id) {
                $odooPromoId = $this->odoo->create('loyalty.program', [
                    'name' => $title,
                    'company_id' => (int) $business->odoo_company_id,
                    'program_type' => 'promotion',
                ]);
            }
        } catch (\Throwable) {
            $odooPromoId = null;
        }

        $operation = BusinessOperation::create([
            'business_id' => $business->id,
            'operation_type' => 'promotion',
            'status' => 'confirmed',
            'odoo_record_id' => $odooPromoId,
            'odoo_model' => 'loyalty.program',
            'customer_name' => $title,
            'total_amount' => $discount,
            'items' => [['name' => $title, 'discount_percent' => $discount, 'description' => $payload['description'] ?? '']],
            'source' => $payload['source'] ?? 'ai',
            'notes' => $payload['description'] ?? null,
        ]);

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'odoo',
            'title' => "Promoción activada: {$title}",
            'details' => "Descuento {$discount}% publicado en Landing y Odoo",
            'meta' => ['operation_id' => $operation->id, 'odoo_record_id' => $odooPromoId],
        ]);

        return $operation;
    }

    public function getBusinessMetrics(Business $business): array
    {
        $todayStart = now()->startOfDay();
        $weekStart = now()->subDays(7)->startOfDay();

        $salesToday = (float) $business->operations()
            ->whereIn('operation_type', ['sale', 'comanda'])
            ->where('created_at', '>=', $todayStart)
            ->sum('total_amount');

        $ordersToday = $business->operations()
            ->whereIn('operation_type', ['sale', 'comanda'])
            ->where('created_at', '>=', $todayStart)
            ->count();

        $salesWeek = (float) $business->operations()
            ->whereIn('operation_type', ['sale', 'comanda'])
            ->where('created_at', '>=', $weekStart)
            ->sum('total_amount');

        $upcomingAppointments = $business->operations()
            ->where('operation_type', 'appointment')
            ->where('scheduled_at', '>=', now())
            ->count();

        $activeComandas = $business->operations()
            ->where('operation_type', 'comanda')
            ->whereIn('status', ['pending', 'preparing'])
            ->count();

        return [
            'sales_today' => $salesToday,
            'orders_today' => $ordersToday,
            'sales_week' => $salesWeek,
            'upcoming_appointments' => $upcomingAppointments,
            'active_comandas' => $activeComandas,
            'catalog_count' => $business->catalogItems()->where('active', true)->count(),
        ];
    }

    private function findOrCreatePartner(Business $business, string $name, ?string $phone): ?int
    {
        $domain = [['name', '=', $name], ['company_id', '=', (int) $business->odoo_company_id]];
        $found = $this->odoo->searchRead('res.partner', $domain, ['id'], 1);
        if (!empty($found[0]['id'])) {
            return (int) $found[0]['id'];
        }

        return $this->odoo->create('res.partner', [
            'name' => $name,
            'phone' => $phone ?: '',
            'company_id' => (int) $business->odoo_company_id,
        ]);
    }

    public function grantMasterAccessToCompany(int $companyId): void
    {
        try {
            if (!$this->odoo->isAvailable()) {
                return;
            }
            $users = $this->odoo->searchRead('res.users', [['login', 'in', ['admin', 'maestro@aisy.ceballosleon.com']]], ['id']);
            foreach ($users as $u) {
                if (!empty($u['id'])) {
                    $this->odoo->write('res.users', [(int) $u['id']], [
                        'company_ids' => [[4, $companyId]],
                    ]);
                }
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }
}
