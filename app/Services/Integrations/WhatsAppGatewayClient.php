<?php

namespace App\Services\Integrations;

use App\Models\Business;
use Illuminate\Support\Facades\Http;

class WhatsAppGatewayClient
{
    private function baseUrl(): string
    {
        return rtrim((string) config('aisy.wa_gateway.url', 'http://127.0.0.1:8085'), '/');
    }

    public function isAvailable(): bool
    {
        $parts = parse_url($this->baseUrl());
        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? 8085;
        $fp = @fsockopen($host, (int) $port, $errno, $errstr, 0.25);
        if (!$fp) {
            return false;
        }
        fclose($fp);

        return true;
    }

    public function createInstance(Business $business): array
    {
        $instanceName = $business->wa_instance_name ?: $business->slug;
        if (!$business->wa_instance_name) {
            $business->wa_instance_name = $instanceName;
            $business->save();
        }

        if (!$this->isAvailable()) {
            return [
                'ok' => true,
                'offline' => true,
                'instanceName' => $instanceName,
                'state' => 'offline',
                'qrcode' => null,
            ];
        }

        try {
            $webhookUrl = url("/api/webhooks/whatsapp/{$business->slug}");
            $resp = Http::timeout((int) config('aisy.wa_gateway.timeout', 6))
                ->post($this->baseUrl() . '/instance/create', [
                    'instanceName' => $instanceName,
                    'webhookUrl' => $webhookUrl,
                ]);

            if ($resp->successful()) {
                return array_merge(['ok' => true], $resp->json() ?? []);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'instanceName' => $instanceName];
        }

        return ['ok' => false, 'instanceName' => $instanceName, 'state' => 'error'];
    }

    public function getQrCode(Business $business): array
    {
        $instanceName = $business->wa_instance_name ?: $business->slug;
        if (!$this->isAvailable()) {
            return ['ok' => false, 'state' => 'offline', 'qrcode' => null];
        }

        try {
            $resp = Http::timeout(8)->get($this->baseUrl() . "/instance/connect/{$instanceName}");
            if ($resp->successful()) {
                $data = $resp->json() ?? [];
                if (($data['state'] ?? '') === 'open' && !$business->wa_connected) {
                    $business->wa_connected = true;
                    $business->save();
                }

                return $data;
            }
        } catch (\Throwable) {
            return ['ok' => false, 'state' => 'error', 'qrcode' => null];
        }

        return ['ok' => false, 'state' => 'error', 'qrcode' => null];
    }

    public function getStatus(Business $business): array
    {
        $instanceName = $business->wa_instance_name ?: $business->slug;
        if (!$this->isAvailable()) {
            return ['ok' => false, 'state' => 'offline', 'connectedPhone' => null];
        }

        try {
            $resp = Http::timeout(4)->get($this->baseUrl() . "/instance/status/{$instanceName}");
            if ($resp->successful()) {
                $data = $resp->json() ?? [];
                $isOpen = ($data['state'] ?? '') === 'open';
                if ($business->wa_connected !== $isOpen) {
                    $business->wa_connected = $isOpen;
                    $business->save();
                }

                return $data;
            }
        } catch (\Throwable) {
            return ['ok' => false, 'state' => 'error'];
        }

        return ['ok' => false, 'state' => 'disconnected'];
    }

    public function sendText(Business $business, string $phone, string $text): array
    {
        $instanceName = $business->wa_instance_name ?: $business->slug;
        if (!$this->isAvailable()) {
            return ['ok' => false, 'queued_locally' => true, 'reason' => 'gateway_offline'];
        }

        try {
            $resp = Http::timeout(6)->post($this->baseUrl() . "/message/sendText/{$instanceName}", [
                'number' => $phone,
                'text' => $text,
            ]);

            return $resp->json() ?? ['ok' => $resp->successful()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function buildClickToChatLink(string $phone, string $message): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return "https://wa.me/{$digits}?text=" . rawurlencode($message);
    }
}
