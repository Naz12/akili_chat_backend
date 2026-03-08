<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the presentation (PPT) microservice to generate outline and slides.
 * Default is 5 slides when user does not specify; user can override by specifying a number in the topic/message.
 */
class PresentationService
{
    private const DEFAULT_SLIDES = 5;

    /**
     * Default number of slides when user does not specify. Uses config or DEFAULT_SLIDES.
     */
    public static function defaultSlideCount(): int
    {
        return (int) config('services.presentation.default_slides', self::DEFAULT_SLIDES);
    }

    /**
     * Generate presentation outline from the PPT microservice.
     *
     * @param string $topic User topic (e.g. "presidents of the United States of America")
     * @param int|null $numSlides If null, uses default (5). User can override via parseTopicAndSlides().
     * @return array{success: bool, outline?: array, message?: string, slides?: array}
     */
    public function generateOutline(string $topic, ?int $numSlides = null): array
    {
        $baseUrl = config('services.presentation.url');
        $apiKey = config('services.presentation.api_key');
        $timeout = config('services.presentation.timeout', 300);
        $defaultSlides = config('services.presentation.default_slides', self::DEFAULT_SLIDES);

        if (empty($baseUrl)) {
            Log::warning('[PresentationService] PRESENTATION_MICROSERVICE_URL not set');
            return [
                'success' => false,
                'message' => 'Presentation service is not configured.',
            ];
        }

        $numSlides = $numSlides ?? $defaultSlides;
        $numSlides = max(1, min(50, (int) $numSlides)); // clamp 1–50

        $payload = [
            'topic' => trim($topic),
            'num_slides' => $numSlides,
        ];

        Log::info('[PresentationService] Requesting outline', [
            'topic' => \Illuminate\Support\Str::limit($topic, 80),
            'num_slides' => $numSlides,
        ]);

        try {
            $path = config('services.presentation.outline_path', '/outline');
            $url = $baseUrl . $path;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ($apiKey ?? ''),
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeout)
                ->post($url, $payload);

            $body = $response->json() ?? [];
            $status = $response->status();

            if (!$response->successful()) {
                $message = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$status}";
                Log::warning('[PresentationService] Outline request failed', [
                    'status' => $status,
                    'message' => $message,
                ]);
                return [
                    'success' => false,
                    'message' => is_string($message) ? $message : 'Could not generate the presentation outline. Please try again or use a shorter topic.',
                ];
            }

            $outline = $body['outline'] ?? $body;
            return [
                'success' => true,
                'outline' => $outline,
                'outline_id' => $body['outline_id'] ?? $body['id'] ?? null,
                'slides' => $body['slides'] ?? null,
                'num_slides' => $numSlides,
            ];
        } catch (\Throwable $e) {
            Log::error('[PresentationService] Request failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Presentation service error. Please try again.',
            ];
        }
    }

    /**
     * Generate slide content from outline (optional step).
     * POST to content_path with topic + outline/outline_id.
     *
     * @param string $topic
     * @param array $outlineResult Result from generateOutline()
     * @return array{success: bool, content?: array, slides?: array, message?: string}
     */
    public function generateContent(string $topic, array $outlineResult): array
    {
        $baseUrl = config('services.presentation.url');
        $path = config('services.presentation.content_path');
        if (empty($baseUrl) || empty($path)) {
            return ['success' => false, 'message' => 'Content endpoint not configured.'];
        }

        $payload = [
            'topic' => $topic,
            'outline' => $outlineResult['outline'] ?? [],
            'outline_id' => $outlineResult['outline_id'] ?? null,
            'num_slides' => $outlineResult['num_slides'] ?? self::defaultSlideCount(),
        ];

        try {
            $response = $this->postPresentation($baseUrl . $path, $payload);
            $body = $response->json() ?? [];
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $body['message'] ?? $body['error'] ?? $response->body() ?: 'Content generation failed.',
                ];
            }
            return [
                'success' => true,
                'content' => $body['content'] ?? $body['slides'] ?? $body,
                'slides' => $body['slides'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[PresentationService] Content request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate PPT file from outline and content (optional step).
     * POST to ppt_path; expects ppt_url or file in response.
     *
     * @param string $topic
     * @param array $outlineResult
     * @param array $contentResult
     * @return array{success: bool, ppt_url?: string, file_url?: string, message?: string}
     */
    public function generatePpt(string $topic, array $outlineResult, array $contentResult): array
    {
        $baseUrl = config('services.presentation.url');
        $path = config('services.presentation.ppt_path');
        if (empty($baseUrl) || empty($path)) {
            return ['success' => false, 'message' => 'PPT generation endpoint not configured.'];
        }

        $payload = [
            'topic' => $topic,
            'outline' => $outlineResult['outline'] ?? [],
            'outline_id' => $outlineResult['outline_id'] ?? null,
            'content' => $contentResult['content'] ?? $contentResult['slides'] ?? [],
            'num_slides' => $outlineResult['num_slides'] ?? self::defaultSlideCount(),
        ];

        try {
            $response = $this->postPresentation($baseUrl . $path, $payload);
            $body = $response->json() ?? [];
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $body['message'] ?? $body['error'] ?? $response->body() ?: 'PPT generation failed.',
                ];
            }
            $pptUrl = $body['ppt_url'] ?? $body['file_url'] ?? $body['url'] ?? null;
            return [
                'success' => true,
                'ppt_url' => $pptUrl,
                'file_url' => $pptUrl,
            ];
        } catch (\Throwable $e) {
            Log::error('[PresentationService] PPT request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Full pipeline: outline -> content (if configured) -> ppt (if configured).
     * Returns outline (required), content and ppt_url when available.
     *
     * @return array{success: bool, outline: array, content?: array, ppt_url?: string, message?: string}
     */
    public function generateFull(string $topic, ?int $numSlides = null): array
    {
        $outlineResult = $this->generateOutline($topic, $numSlides);
        if (!$outlineResult['success']) {
            return [
                'success' => false,
                'outline' => [],
                'message' => $outlineResult['message'] ?? 'Outline generation failed.',
            ];
        }

        $outline = $outlineResult['outline'] ?? [];
        $content = null;
        $contentResult = ['success' => false];
        $contentPath = config('services.presentation.content_path');
        if (!empty($contentPath)) {
            $contentResult = $this->generateContent($topic, $outlineResult);
            if ($contentResult['success']) {
                $content = $contentResult['content'] ?? $contentResult['slides'];
            }
        }

        $pptUrl = null;
        $pptPath = config('services.presentation.ppt_path');
        if (!empty($pptPath)) {
            $pptResult = $this->generatePpt($topic, $outlineResult, $contentResult);
            if ($pptResult['success'] && !empty($pptResult['ppt_url'])) {
                $pptUrl = $pptResult['ppt_url'];
            }
        }

        return [
            'success' => true,
            'outline' => $outline,
            'content' => $content,
            'ppt_url' => $pptUrl,
        ];
    }

    private function postPresentation(string $url, array $payload): \Illuminate\Http\Client\Response
    {
        $apiKey = config('services.presentation.api_key');
        $timeout = (int) config('services.presentation.timeout', 300);
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . ($apiKey ?? ''),
            'Content-Type' => 'application/json',
        ])->timeout($timeout)->post($url, $payload);
    }

    /**
     * Parse user message to extract topic and optional slide count.
     * Examples:
     *   "presidents of the United States" -> ['presidents of the United States', 5]
     *   "US presidents, 15 slides" -> ['US presidents', 15]
     *   "make a ppt about X with 7 slides" -> ['make a ppt about X', 7]
     *
     * @return array{0: string, 1: int} [topic, numSlides]
     */
    public static function parseTopicAndSlides(string $message): array
    {
        $defaultSlides = config('services.presentation.default_slides', self::DEFAULT_SLIDES);
        $topic = trim($message);
        $numSlides = $defaultSlides;

        // Match patterns like "15 slides", "7 slide", "with 20 slides", ", 12 slides"
        if (preg_match('/\b(?:with\s+)?(\d{1,2})\s*slides?\s*$/i', $topic, $m)) {
            $numSlides = (int) $m[1];
            $topic = trim(preg_replace('/\s*,?\s*(?:with\s+)?\d+\s*slides?\s*$/i', '', $topic));
        } elseif (preg_match('/\b(\d{1,2})\s*slides?\s+(?:about|on|for)\s+/i', $topic, $m)) {
            $numSlides = (int) $m[1];
            $topic = trim(preg_replace('/^\d+\s*slides?\s+(?:about|on|for)\s+/i', '', $topic));
        }

        $numSlides = max(1, min(50, $numSlides));
        return [$topic ?: $message, $numSlides];
    }
}
