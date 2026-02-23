<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for tools/diagram microservice.
 * POST /generate-diagram, GET /status/{job_id}, GET /result/{job_id}.
 * Uses X-API-Key.
 */
class DiagramMicroserviceClient
{
    public function generateDiagram(string $prompt, string $diagramType = 'flowchart', string $outputFormat = 'png'): array
    {
        $url = $this->baseUrl() . '/generate-diagram';
        $payload = [
            'prompt' => $prompt,
            'diagram_type' => $diagramType,
            'output_format' => $outputFormat,
        ];
        return $this->postJob($url, $payload);
    }

    public function getJobStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/status/' . $jobId;
        return $this->getJob($url);
    }

    public function getJobResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/result/' . $jobId;
        return $this->getJob($url);
    }

    private function baseUrl(): string
    {
        return config('services.diagram.url', '');
    }

    private function apiKey(): ?string
    {
        return config('services.diagram.api_key');
    }

    private function timeout(): int
    {
        return (int) config('services.diagram.timeout', 120);
    }

    private function postJob(string $url, array $payload): array
    {
        if (empty($this->baseUrl())) {
            Log::warning('[DiagramMicroserviceClient] DIAGRAM_MICROSERVICE_URL not set');
            return ['success' => false, 'error' => 'Diagram service is not configured.'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->post($url, $payload);

            $body = $response->json() ?? [];
            if (!$response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Diagram service', $error)];
            }

            $jobId = $body['job_id'] ?? null;
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => $body['status'] ?? 'queued',
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DiagramMicroserviceClient] Request failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Diagram service', $e->getMessage(), $e)];
        }
    }

    private function getJob(string $url): array
    {
        if (empty($this->baseUrl())) {
            return ['success' => false, 'error' => 'Diagram service is not configured.', 'data' => null];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get($url);

            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return [
                    'success' => false,
                    'error' => MicroserviceErrorHelper::userMessage('Diagram service', $error),
                    'data' => null,
                ];
            }

            // Diagram service may return image as binary (e.g. GET /result/{id} returns PNG bytes)
            $contentType = $response->header('Content-Type');
            $bodyRaw = $response->body();
            if ($bodyRaw !== '' && ($this->isBinaryContentType($contentType) || ! $this->looksLikeJson($bodyRaw))) {
                $data = [
                    '_raw_body' => $bodyRaw,
                    '_content_type' => $contentType ?: 'image/png',
                    'status' => 'completed',
                ];
                return [
                    'success' => true,
                    'data' => $data,
                    'status' => 'completed',
                    'progress' => 100,
                ];
            }

            $body = $response->json() ?? [];
            // Merge top-level body into data so status/download_url at root are not lost
            $inner = is_array($body['data'] ?? null) ? $body['data'] : [];
            $data = is_array($body) ? array_merge($body, $inner) : $inner;
            return [
                'success' => true,
                'data' => $data,
                'status' => is_array($data) ? ($data['status'] ?? null) : null,
                'progress' => is_array($data) ? ($data['progress'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DiagramMicroserviceClient] Get job failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Diagram service', $e->getMessage(), $e), 'data' => null];
        }
    }

    private function headers(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        $key = $this->apiKey();
        if (!empty($key)) {
            $headers['X-API-Key'] = $key;
        }
        return $headers;
    }

    private function isBinaryContentType(?string $contentType): bool
    {
        if (empty($contentType)) {
            return false;
        }
        $primary = strtolower(trim(explode(';', $contentType)[0]));
        return $primary === 'application/octet-stream'
            || str_starts_with($primary, 'image/');
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmed = ltrim($body);
        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
