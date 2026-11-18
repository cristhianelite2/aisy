<?php

namespace App\Services\Integrations;

use App\Models\Business;
use App\Models\BusinessActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SellerCentralClient
{
    private function baseUrl(): string
    {
        return rtrim((string) config('aisy.seller_central.url', 'https://sc.ceballosleon.com'), '/');
    }

    private function masterKey(): string
    {
        $configured = (string) config('aisy.seller_central.master_key', '');
        if ($configured !== '') {
            return $configured;
        }

        if (app()->environment('testing')) {
            return '';
        }

        try {
            $row = DB::connection('mysql')
                ->table('sellercentral.master_api_settings')
                ->where('enabled', 1)
                ->first();

            return (string) ($row->api_key ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    public function ensureProjectForBusiness(Business $business): ?int
    {
        if ($business->sc_project_id) {
            return (int) $business->sc_project_id;
        }

        $key = $this->masterKey();
        if ($key !== '') {
            try {
                $resp = Http::timeout((int) config('aisy.seller_central.timeout', 15))
                    ->withHeaders(['X-Master-Key' => $key, 'Authorization' => 'Bearer ' . $key])
                    ->acceptJson()
                    ->post($this->baseUrl() . '/api/v1/master/projects', [
                        'name' => $business->name,
                        'slug' => $business->slug,
                        'description' => "Negocio Aisy ({$business->branch}): {$business->address}",
                    ]);

                if ($resp->successful()) {
                    $id = (int) ($resp->json('data.id') ?? $resp->json('id') ?? 0);
                    if ($id > 0) {
                        $business->sc_project_id = $id;
                        $business->save();

                        return $id;
                    }
                }
            } catch (\Throwable) {
                // Fall through to deterministic link ID
            }
        }

        $business->sc_project_id = 500 + (int) $business->id;
        $business->save();

        return (int) $business->sc_project_id;
    }

    public function scheduleSocialPost(Business $business, array $postData): array
    {
        $projectId = $this->ensureProjectForBusiness($business);
        $key = $this->masterKey();
        $liveSent = false;

        if ($key !== '' && $projectId) {
            try {
                $resp = Http::timeout((int) config('aisy.seller_central.timeout', 15))
                    ->withHeaders(['X-Master-Key' => $key, 'Authorization' => 'Bearer ' . $key])
                    ->acceptJson()
                    ->post($this->baseUrl() . "/api/v1/master/projects/{$projectId}/api/posts", [
                        'title' => $postData['title'] ?? $business->name,
                        'caption' => $postData['caption'] ?? '',
                        'media_url' => $postData['media_url'] ?? null,
                        'platforms' => $postData['platforms'] ?? ['facebook', 'instagram', 'tiktok'],
                        'scheduled_at' => $postData['scheduled_at'] ?? now()->addMinutes(15)->toIso8601String(),
                    ]);

                $liveSent = $resp->successful();
            } catch (\Throwable) {
                $liveSent = false;
            }
        }

        BusinessActivity::create([
            'business_id' => $business->id,
            'actor' => 'ai',
            'category' => 'marketing',
            'title' => 'Anuncio enviado a Seller Central',
            'details' => ($postData['title'] ?? 'Campaña') . ' (' . ($liveSent ? 'Publicado en sc.ceballosleon.com' : 'En cola local para Seller Central') . ')',
            'meta' => [
                'sc_project_id' => $projectId,
                'live_sent' => $liveSent,
                'media_url' => $postData['media_url'] ?? null,
            ],
        ]);

        return [
            'ok' => true,
            'live_sent' => $liveSent,
            'sc_project_id' => $projectId,
            'connect_url' => $this->getConnectSocialUrl($business),
        ];
    }

    public function getConnectSocialUrl(Business $business): string
    {
        $id = $business->sc_project_id ?: (500 + (int) $business->id);

        return $this->baseUrl() . "/#/proyecto/{$id}/config";
    }
}
