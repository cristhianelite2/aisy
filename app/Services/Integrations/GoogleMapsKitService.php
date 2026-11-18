<?php

namespace App\Services\Integrations;

use App\Models\Business;

class GoogleMapsKitService
{
    public function generateKit(Business $business, ?array $coords = null, ?string $facadePhotoUrl = null): array
    {
        $lat = $coords['lat'] ?? $business->latitude;
        $lng = $coords['lng'] ?? $business->longitude;

        if ($coords && isset($coords['lat'], $coords['lng'])) {
            $business->latitude = (float) $coords['lat'];
            $business->longitude = (float) $coords['lng'];
        }

        $schemaType = match ($business->branch) {
            'estetica' => 'BeautySalon',
            'comandas' => 'Restaurant',
            default => 'Store',
        };

        $categoryLabel = match ($business->branch) {
            'estetica' => 'Salón de belleza y estética',
            'comandas' => 'Restaurante / Comida para llevar',
            default => 'Tienda local',
        };

        $landingUrl = url("/{$business->slug}");

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'name' => $business->name,
            'url' => $landingUrl,
            'telephone' => $business->owner_phone,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $business->address ?: 'Dirección local',
                'addressCountry' => 'MX',
            ],
        ];

        if ($lat !== null && $lng !== null) {
            $jsonLd['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        $reviewTarget = $business->gmaps_place_url ?: $landingUrl;
        $reviewQrSvg = $this->renderQrPlaceholderSvg($business, $reviewTarget);

        $kit = [
            'seo_title' => "{$business->name} — {$categoryLabel}",
            'category' => $categoryLabel,
            'description' => "Visita {$business->name} en {$business->address}. Agenda, ordena o conoce nuestras promociones en {$landingUrl}.",
            'create_url' => 'https://business.google.com/create?hl=es',
            'maps_search_url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim("{$business->name} {$business->address}")),
            'facade_photo_url' => $facadePhotoUrl,
            'review_url' => $reviewTarget,
            'review_qr_svg' => $reviewQrSvg,
            'json_ld' => $jsonLd,
            'updated_at' => now()->toIso8601String(),
        ];

        $business->gmaps_kit = $kit;
        $business->save();

        return $kit;
    }

    private function renderQrPlaceholderSvg(Business $business, string $targetUrl): string
    {
        $color = htmlspecialchars($business->primary_color ?: '#0ea5e9', ENT_QUOTES);
        $name = htmlspecialchars($business->name, ENT_QUOTES);
        $hash = md5($targetUrl);

        $cells = '';
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $idx = ($r * 7 + $c) % 32;
                if (hexdec($hash[$idx]) % 2 === 0) {
                    $x = 40 + $c * 24;
                    $y = 40 + $r * 24;
                    $cells .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"20\" height=\"20\" rx=\"4\" fill=\"#0f172a\"/>";
                }
            }
        }

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 248 280\" width=\"248\" height=\"280\">"
            . "<rect width=\"248\" height=\"280\" rx=\"20\" fill=\"#ffffff\" stroke=\"{$color}\" stroke-width=\"4\"/>"
            . $cells
            . "<text x=\"124\" y=\"235\" text-anchor=\"middle\" font-family=\"Segoe UI, Arial\" font-size=\"13\" font-weight=\"700\" fill=\"{$color}\">★★★★★ Reseñas Google</text>"
            . "<text x=\"124\" y=\"256\" text-anchor=\"middle\" font-family=\"Segoe UI, Arial\" font-size=\"11\" fill=\"#475569\">{$name}</text>"
            . "</svg>";
    }
}
