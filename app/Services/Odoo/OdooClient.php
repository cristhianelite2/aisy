<?php

namespace App\Services\Odoo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OdooClient
{
    private ?int $uid = null;

    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $db = null,
        private readonly ?string $user = null,
        private readonly ?string $password = null,
        private readonly ?int $timeout = null,
    ) {}

    private function baseUrl(): string
    {
        return rtrim($this->url ?? config('aisy.odoo.url', 'http://127.0.0.1:8069'), '/');
    }

    private function dbName(): string
    {
        return $this->db ?? config('aisy.odoo.db', 'aisy_erp');
    }

    private function username(): string
    {
        return $this->user ?? config('aisy.odoo.user', 'admin');
    }

    private function pass(): string
    {
        return $this->password ?? config('aisy.odoo.password', 'admin');
    }

    private function timeoutSeconds(): int
    {
        return $this->timeout ?? (int) config('aisy.odoo.timeout', 6);
    }

    public function isAvailable(): bool
    {
        $parts = parse_url($this->baseUrl());
        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? 8069;
        $fp = @fsockopen($host, (int) $port, $errno, $errstr, 0.25);
        if (!$fp) {
            return false;
        }
        fclose($fp);

        try {
            $this->authenticate();

            return $this->uid !== null && $this->uid > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function authenticate(): int
    {
        if ($this->uid !== null && $this->uid > 0) {
            return $this->uid;
        }

        $result = $this->jsonRpc('common', 'authenticate', [
            $this->dbName(),
            $this->username(),
            $this->pass(),
            [],
        ]);

        if (!is_int($result) || $result <= 0) {
            throw new RuntimeException('Odoo authentication failed.');
        }

        $this->uid = $result;

        return $this->uid;
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $uid = $this->authenticate();

        return $this->jsonRpc('object', 'execute_kw', [
            $this->dbName(),
            $uid,
            $this->pass(),
            $model,
            $method,
            $args,
            $kwargs ?: new \stdClass(),
        ]);
    }

    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 100, string $order = 'id desc'): array
    {
        $result = $this->executeKw($model, 'search_read', [$domain], [
            'fields' => $fields,
            'limit' => $limit,
            'order' => $order,
        ]);

        return is_array($result) ? $result : [];
    }

    public function create(string $model, array $values): int
    {
        $id = $this->executeKw($model, 'create', [$values]);

        return (int) $id;
    }

    public function write(string $model, array $ids, array $values): bool
    {
        return (bool) $this->executeKw($model, 'write', [$ids, $values]);
    }

    private function jsonRpc(string $service, string $method, array $args): mixed
    {
        $response = Http::timeout($this->timeoutSeconds())
            ->acceptJson()
            ->post($this->baseUrl() . '/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => $service,
                    'method' => $method,
                    'args' => $args,
                ],
                'id' => random_int(1, 999999),
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Odoo HTTP error: ' . $response->status());
        }

        $body = $response->json();
        if (isset($body['error'])) {
            $msg = $body['error']['data']['message'] ?? $body['error']['message'] ?? 'Unknown Odoo RPC error';
            throw new RuntimeException($msg);
        }

        return $body['result'] ?? null;
    }
}
