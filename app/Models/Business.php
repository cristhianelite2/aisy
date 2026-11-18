<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'branch',
        'owner_name',
        'owner_phone',
        'address',
        'latitude',
        'longitude',
        'primary_color',
        'secondary_color',
        'style_preset',
        'features',
        'ai_memory_semantic',
        'odoo_company_id',
        'odoo_pos_config_id',
        'odoo_warehouse_id',
        'sc_project_id',
        'wa_instance_name',
        'wa_connected',
        'gmaps_place_url',
        'gmaps_kit',
        'provisioning_status',
        'provisioning_steps',
    ];

    protected $casts = [
        'features' => 'array',
        'ai_memory_semantic' => 'array',
        'gmaps_kit' => 'array',
        'provisioning_steps' => 'array',
        'wa_connected' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (empty($business->features)) {
                $business->features = static::defaultFeaturesForBranch($business->branch);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'negocio';
        }

        $reserved = ['nuevo', 'api', 'admin', 'login', 'logout', 'up', 'storage'];
        if (in_array($base, $reserved, true)) {
            $base .= '-local';
        }

        $slug = $base;
        $counter = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public static function defaultFeaturesForBranch(string $branch): array
    {
        return match ($branch) {
            'estetica' => [
                'agenda' => true,
                'ventas_pos' => true,
                'promociones' => true,
                'anuncios_ia' => true,
                'google_maps' => true,
            ],
            'tienda' => [
                'pos' => true,
                'catalogo_web' => true,
                'estadisticas' => true,
                'envios' => true,
                'promociones' => true,
                'anuncios_ia' => true,
                'google_maps' => true,
            ],
            'comandas' => [
                'menu_qr' => true,
                'comandas_kds' => true,
                'mesas' => true,
                'pos_voz' => true,
                'promociones' => true,
                'anuncios_ia' => true,
                'google_maps' => true,
            ],
            default => [
                'ventas_pos' => true,
                'promociones' => true,
                'anuncios_ia' => true,
                'google_maps' => true,
            ],
        };
    }

    public static function featureLabels(): array
    {
        return [
            'agenda' => 'Agenda de Citas',
            'ventas_pos' => 'Cobro Rápido / POS',
            'pos' => 'Punto de Venta e Inventario',
            'catalogo_web' => 'Catálogo Tienda Online',
            'estadisticas' => 'Estadísticas e Insights IA',
            'envios' => 'Gestión de Envíos',
            'menu_qr' => 'Menú Digital QR',
            'comandas_kds' => 'Pantalla de Cocina (KDS)',
            'mesas' => 'Control de Mesas',
            'pos_voz' => 'Comandas por Voz',
            'promociones' => 'Promociones y Lealtad',
            'anuncios_ia' => 'Anuncios IA y Redes (Seller Central)',
            'google_maps' => 'Kit Google Maps y Reseñas',
        ];
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $features = $this->features ?? static::defaultFeaturesForBranch($this->branch);

        return (bool) ($features[$feature] ?? false);
    }

    public function toggleFeature(string $feature, ?bool $enabled = null): array
    {
        $features = $this->features ?? static::defaultFeaturesForBranch($this->branch);
        $current = (bool) ($features[$feature] ?? false);
        $features[$feature] = $enabled ?? !$current;
        $this->features = $features;
        $this->save();

        return $features;
    }

    public function episodicMemories(): HasMany
    {
        return $this->hasMany(AiEpisodicMemory::class);
    }

    public function pageEvents(): HasMany
    {
        return $this->hasMany(PageEvent::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BusinessActivity::class);
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(BusinessCatalogItem::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(BusinessOperation::class);
    }
}
