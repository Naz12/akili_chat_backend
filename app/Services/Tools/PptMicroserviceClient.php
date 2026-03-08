<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for tools/ppt microservice (async job API).
 * Uses X-API-Key; endpoints: /generate-outline, /generate-content, /export,
 * GET /jobs/{id}/status, GET /jobs/{id}/result.
 */
class PptMicroserviceClient
{
    /**
     * Submit outline job to tools/ppt. If the microservice accepts num_slides, pass it; otherwise it may be inferred from content.
     *
     * @param  int|null  $numSlides  Optional. When provided, sent as num_slides in payload (if the endpoint supports it).
     */
    public function submitOutline(string $content, string $language = 'English', string $tone = 'Professional', string $length = 'Medium', ?int $numSlides = null): array
    {
        $url = $this->baseUrl() . '/generate-outline';
        $payload = [
            'content' => $content,
            'language' => $language,
            'tone' => $tone,
            'length' => $length,
        ];
        if ($numSlides !== null && $numSlides >= 1 && $numSlides <= 50) {
            $payload['num_slides'] = $numSlides;
        }
        return $this->postJob($url, $payload);
    }

    public function submitContent(array $outline, string $language = 'English', string $tone = 'Professional', string $detailLevel = 'Medium'): array
    {
        $url = $this->baseUrl() . '/generate-content';
        $payload = [
            'outline' => $outline,
            'language' => $language,
            'tone' => $tone,
            'detail_level' => $detailLevel,
        ];
        return $this->postJob($url, $payload);
    }

    public function submitExport(array $content, string $randomId, string $template = 'corporate_blue', string $colorScheme = 'blue', string $fontStyle = 'modern', ?int $userId = null): array
    {
        $url = $this->baseUrl() . '/export';
        $payload = [
            'content' => $content,
            'random_id' => $randomId,
            'template' => $template,
            'color_scheme' => $colorScheme,
            'font_style' => $fontStyle,
        ];
        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }
        return $this->postJob($url, $payload);
    }

    public function getJobStatus(string $jobId): array
    {
        $path = str_replace('{job_id}', $jobId, config('services.presentation.status_path', '/jobs/{job_id}/status'));
        $url = $this->baseUrl() . $path;
        return $this->getJob($url);
    }

    public function getJobResult(string $jobId): array
    {
        $path = str_replace('{job_id}', $jobId, config('services.presentation.result_path', '/jobs/{job_id}/result'));
        $url = $this->baseUrl() . $path;
        return $this->getJob($url);
    }

    /**
     * Get available presentation templates/styles from microservice (for step 3: user picks style before export).
     * Returns array keyed by template id with name, description, color_scheme, etc.
     */
    public function getTemplates(): array
    {
        if (empty($this->baseUrl())) {
            return $this->defaultTemplates();
        }
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->pollTimeout())
                ->get($this->baseUrl() . '/templates');

            $body = $response->json() ?? [];
            if (!$response->successful()) {
                Log::warning('[PptMicroserviceClient] GET /templates failed', ['status' => $response->status()]);
                return $this->defaultTemplates();
            }
            $raw = $body['templates'] ?? $body;
            if (is_array($raw) && !empty($raw)) {
                $templates = [];
                foreach ($raw as $key => $t) {
                    if (!is_array($t)) {
                        continue;
                    }
                    $id = $t['id'] ?? $t['template_id'] ?? $t['name'] ?? (is_string($key) ? $key : null);
                    $id = ($id !== null && !is_int($id)) ? (string) $id : ('template_' . (count($templates) + 1));
                    $templates[$id] = $t;
                }
                if (!empty($templates)) {
                    return $templates;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[PptMicroserviceClient] getTemplates failed', ['error' => $e->getMessage()]);
        }
        return $this->defaultTemplates();
    }

    private function defaultTemplates(): array
    {
        return [
            'corporate_blue' => ['name' => 'Corporate Blue', 'description' => 'Professional blue theme', 'color_scheme' => 'blue', 'category' => 'business'],
            'modern_white' => ['name' => 'Modern White', 'description' => 'Clean white theme', 'color_scheme' => 'white', 'category' => 'modern'],
            'creative_colorful' => ['name' => 'Creative Colorful', 'description' => 'Vibrant colors', 'color_scheme' => 'colorful', 'category' => 'creative'],
            'minimalist_gray' => ['name' => 'Minimalist Gray', 'description' => 'Simple gray theme', 'color_scheme' => 'gray', 'category' => 'minimalist'],
        ];
    }

    private function baseUrl(): string
    {
        return config('services.presentation.url', '');
    }

    private function apiKey(): ?string
    {
        return config('services.presentation.api_key');
    }

    private function timeout(): int
    {
        return (int) config('services.presentation.timeout', 300);
    }

    private function pollTimeout(): int
    {
        return (int) config('services.presentation.poll_timeout', 30);
    }

    private function postJob(string $url, array $payload): array
    {
        if (empty($this->baseUrl())) {
            Log::warning('[PptMicroserviceClient] PRESENTATION_MICROSERVICE_URL not set');
            return ['success' => false, 'error' => 'Presentation service is not configured.'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->post($url, $payload);

            $body = $response->json() ?? [];
            if (!$response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                Log::warning('[PptMicroserviceClient] Export request failed', [
                    'url' => $url,
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Presentation service', $error)];
            }

            $jobId = $body['job_id'] ?? null;
            if (empty($jobId)) {
                Log::warning('[PptMicroserviceClient] Export response missing job_id', ['url' => $url, 'body' => $body]);
            }
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => $body['status'] ?? 'queued',
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[PptMicroserviceClient] Request failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Presentation service', $e->getMessage(), $e)];
        }
    }

    private function getJob(string $url): array
    {
        if (empty($this->baseUrl())) {
            return ['success' => false, 'error' => 'Presentation service is not configured.', 'data' => null];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->pollTimeout())
                ->get($url);

            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return [
                    'success' => false,
                    'error' => MicroserviceErrorHelper::userMessage('Presentation service', $error),
                    'data' => null,
                ];
            }

            // Export result may be returned as binary (FileResponse) instead of JSON
            $contentType = $response->header('Content-Type');
            $bodyRaw = $response->body();
            if ($bodyRaw !== '' && ($this->isBinaryContentType($contentType) || ! $this->looksLikeJson($bodyRaw))) {
                $data = [
                    '_raw_body' => $bodyRaw,
                    '_content_type' => $contentType ?: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
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
            // Merge top-level body into data so file_content/download_url at root are not lost
            $inner = is_array($body['data'] ?? null) ? $body['data'] : [];
            $data = is_array($body) ? array_merge($body, $inner) : $inner;
            return [
                'success' => true,
                'data' => $data,
                'status' => is_array($data) ? ($data['status'] ?? null) : null,
                'progress' => is_array($data) ? ($data['progress'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[PptMicroserviceClient] Get job failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Presentation service', $e->getMessage(), $e), 'data' => null];
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
            || str_contains($primary, 'vnd.openxmlformats-officedocument.presentationml')
            || str_contains($primary, 'application/vnd.ms-powerpoint');
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmed = ltrim($body);
        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
