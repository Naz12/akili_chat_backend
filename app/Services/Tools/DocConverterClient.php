<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for tools/doc-convertor microservice.
 * POST /v1/convert (multipart: file + target_format), returns job_id.
 * GET /v1/conversion/status?job_id=, GET /v1/conversion/result?job_id=.
 * Uses X-API-Key (if configured).
 */
class DocConverterClient
{
    public function convertDocument(string $filePath, string $targetFormat, array $options = []): array
    {
        $url = $this->baseUrl() . '/v1/convert';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (! is_readable($filePath)) {
            return ['success' => false, 'error' => 'File not found or not readable.'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($url, array_merge(['target_format' => $targetFormat], $options));

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error)];
            }

            $jobId = $body['job_id'] ?? null;
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => $body['status'] ?? 'queued',
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Convert failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    public function getConversionStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/conversion/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getConversionResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/conversion/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.doc_converter.url', ''), '/');
    }

    private function apiKey(): ?string
    {
        return config('services.doc_converter.api_key');
    }

    private function timeout(): int
    {
        return (int) config('services.doc_converter.timeout', 120);
    }

    private function get(string $url): array
    {
        if (empty($this->baseUrl())) {
            return ['success' => false, 'error' => 'Doc-converter service is not configured.', 'data' => null];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get($url);

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return [
                    'success' => false,
                    'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error),
                    'data' => null,
                ];
            }

            $data = $body['data'] ?? $body;
            return [
                'success' => true,
                'data' => $data,
                'status' => is_array($data) ? ($data['status'] ?? null) : null,
                'progress' => is_array($data) ? ($data['progress'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Get failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e), 'data' => null];
        }
    }

    private function headers(): array
    {
        $headers = [];
        $key = $this->apiKey();
        if (! empty($key)) {
            $headers['X-API-Key'] = $key;
        }
        return $headers;
    }
}
