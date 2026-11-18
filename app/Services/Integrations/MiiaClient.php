<?php

namespace App\Services\Integrations;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MiiaClient
{
    private function baseUrl(): string
    {
        return rtrim((string) config('aisy.miia.url', 'https://ia.ceballosleon.com'), '/');
    }

    private function apiKey(): string
    {
        return (string) config('aisy.miia.api_key', '');
    }

    private function timeout(): int
    {
        return (int) config('aisy.miia.timeout', 20);
    }

    public function chatWithTools(array $messages, array $tools = [], ?string $systemPrompt = null): array
    {
        $payloadMessages = [];
        if ($systemPrompt) {
            $payloadMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($messages as $m) {
            $payloadMessages[] = $m;
        }

        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'fallback' => true, 'message' => null];
        }

        try {
            $body = [
                'messages' => $payloadMessages,
                'temperature' => 0.3,
            ];
            if (!empty($tools)) {
                $body['tools'] = $tools;
                $body['tool_choice'] = 'auto';
            }

            $resp = Http::timeout($this->timeout())
                ->withToken($key)
                ->acceptJson()
                ->post($this->baseUrl() . '/v1/chat/completions', $body);

            if ($resp->successful()) {
                $data = $resp->json();
                $choice = $data['choices'][0]['message'] ?? null;
                if ($choice) {
                    return ['ok' => true, 'fallback' => false, 'message' => $choice, 'raw' => $data];
                }
            }
        } catch (\Throwable) {
            // Fall through to deterministic fallback
        }

        return ['ok' => false, 'fallback' => true, 'message' => null];
    }

    public function decide(string $prompt, array $options): ?string
    {
        $key = $this->apiKey();
        if ($key === '') {
            return null;
        }

        try {
            $resp = Http::timeout(6)
                ->withToken($key)
                ->acceptJson()
                ->post($this->baseUrl() . '/v1/decisions', [
                    'prompt' => $prompt,
                    'options' => array_values($options),
                ]);

            if ($resp->successful()) {
                $choice = $resp->json('decision') ?? $resp->json('choice');
                if (is_string($choice) && in_array($choice, $options, true)) {
                    return $choice;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function transcribeAudio(string $filePathOrBase64, string $mimeType = 'audio/ogg'): string
    {
        $key = $this->apiKey();
        if ($key === '') {
            return '';
        }

        try {
            $tmpFile = null;
            if (is_file($filePathOrBase64)) {
                $path = $filePathOrBase64;
            } else {
                $raw = base64_decode($filePathOrBase64, true);
                if ($raw === false) {
                    return '';
                }
                $ext = str_contains($mimeType, 'wav') ? 'wav' : (str_contains($mimeType, 'mp3') ? 'mp3' : 'ogg');
                $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aisy_audio_' . Str::random(8) . '.' . $ext;
                file_put_contents($tmpFile, $raw);
                $path = $tmpFile;
            }

            $resp = Http::timeout($this->timeout())
                ->withToken($key)
                ->attach('file', fopen($path, 'r'), basename($path))
                ->post($this->baseUrl() . '/v1/audio/transcriptions', [
                    'model' => 'whisper-large-v3',
                    'language' => 'es',
                ]);

            if ($tmpFile && is_file($tmpFile)) {
                @unlink($tmpFile);
            }

            if ($resp->successful()) {
                return trim((string) ($resp->json('text') ?? ''));
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    public function generateImage(string $prompt, Business $business, string $headline = ''): array
    {
        $key = $this->apiKey();
        if ($key !== '') {
            try {
                $resp = Http::timeout($this->timeout())
                    ->withToken($key)
                    ->acceptJson()
                    ->post($this->baseUrl() . '/v1/images/generations', [
                        'prompt' => $prompt,
                        'size' => '1024x1024',
                    ]);

                if ($resp->successful()) {
                    $url = $resp->json('data.0.url') ?? $resp->json('url');
                    if (is_string($url) && $url !== '') {
                        return ['ok' => true, 'url' => $url, 'provider' => 'miia_imagesia'];
                    }
                }
            } catch (\Throwable) {
                // Fall back to branded SVG flyer
            }
        }

        $url = $this->renderBrandedSvgFlyer($business, $headline ?: $prompt);

        return ['ok' => true, 'url' => $url, 'provider' => 'aisy_branded_flyer'];
    }

    private function renderBrandedSvgFlyer(Business $business, string $headline): string
    {
        $p = htmlspecialchars($business->primary_color ?: '#0ea5e9', ENT_QUOTES);
        $s = htmlspecialchars($business->secondary_color ?: '#6366f1', ENT_QUOTES);
        $name = htmlspecialchars(Str::limit($business->name, 34), ENT_QUOTES);
        $title = htmlspecialchars(Str::limit($headline, 62), ENT_QUOTES);
        $slugUrl = htmlspecialchars("aisy.ceballosleon.com/{$business->slug}", ENT_QUOTES);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 1080 1080">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$p}"/>
      <stop offset="100%" stop-color="{$s}"/>
    </linearGradient>
  </defs>
  <rect width="1080" height="1080" fill="url(#bg)"/>
  <circle cx="920" cy="160" r="210" fill="rgba(255,255,255,0.12)"/>
  <circle cx="140" cy="940" r="240" fill="rgba(255,255,255,0.10)"/>
  <rect x="80" y="140" width="920" height="800" rx="48" fill="rgba(15,23,42,0.72)"/>
  <text x="140" y="270" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="44" font-weight="700">{$name}</text>
  <text x="140" y="470" fill="#f8fafc" font-family="Segoe UI, Arial, sans-serif" font-size="56" font-weight="800">{$title}</text>
  <rect x="140" y="700" width="520" height="88" rx="20" fill="{$p}"/>
  <text x="175" y="757" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="32" font-weight="700">Reserva o pide hoy</text>
  <text x="140" y="870" fill="#cbd5e1" font-family="Segoe UI, Arial, sans-serif" font-size="28">{$slugUrl}</text>
</svg>
SVG;

        $filename = "ads/{$business->slug}-" . Str::random(8) . '.svg';
        Storage::disk('public')->put($filename, $svg);

        return '/storage/' . $filename;
    }
}
