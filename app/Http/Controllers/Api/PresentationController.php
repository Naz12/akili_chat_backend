<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Models\Plan;
use App\Services\GuestUserService;
use App\Services\Tools\PptMicroserviceClient;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresentationController extends Controller
{
    public function __construct(
        protected PptMicroserviceClient $pptClient,
        protected UsageValidatorService $usageValidator,
        protected GuestUserService $guestUserService
    ) {}

    private function checkQuota(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $guestSession = null;
        $plan = null;
        if (!$user) {
            $guestSession = $this->guestUserService->getGuestSessionFromRequest($request);
            $region = $request->segment(3) ?? 'local';
            $plan = Plan::where('is_default', true)->where('region', $region)->first() ?? Plan::where('is_default', true)->first();
        }
        $result = $this->usageValidator->checkQuota($user, false, $guestSession, $plan);
        if (!empty($result['error'])) {
            return response()->json(['error' => $result['message'] ?? 'Quota exceeded.'], 403);
        }
        return null;
    }

    /**
     * POST /api/v1/{region}/presentations/generate-outline
     */
    public function generateOutline(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'content' => 'required|string|max:32000',
            'language' => 'nullable|string|max:50',
            'tone' => 'nullable|string|max:50',
            'length' => 'nullable|string|max:50',
        ]);

        $result = $this->pptClient->submitOutline(
            $request->input('content'),
            $request->input('language', 'English'),
            $request->input('tone', 'Professional'),
            $request->input('length', 'Medium')
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Presentation service is temporarily unavailable. Please try again later.'], 502);
        }
        if (empty($result['job_id'])) {
            return response()->json(['success' => false, 'error' => 'Presentation service did not return a job. Please try again.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Outline generation started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/presentations/generate-content
     */
    public function generateContent(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'outline' => 'required|array',
            'outline.title' => 'nullable|string',
            'outline.slides' => 'nullable|array',
            'language' => 'nullable|string|max:50',
            'tone' => 'nullable|string|max:50',
            'detail_level' => 'nullable|string|max:50',
        ]);

        $result = $this->pptClient->submitContent(
            $request->input('outline'),
            $request->input('language', 'English'),
            $request->input('tone', 'Professional'),
            $request->input('detail_level', 'Medium')
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to submit content.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Content generation started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/presentations/export
     */
    public function export(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'content' => 'required|array',
            'content.title' => 'nullable|string',
            'content.slides' => 'nullable|array',
            'random_id' => 'required|string|max:64',
            'template' => 'nullable|string|max:100',
            'color_scheme' => 'nullable|string|max:50',
            'font_style' => 'nullable|string|max:50',
        ]);

        $content = $request->input('content');
        $contentForExport = $this->transformContentForExport($content);

        $result = $this->pptClient->submitExport(
            $contentForExport,
            $request->input('random_id'),
            $request->input('template', 'corporate_blue'),
            $request->input('color_scheme', 'blue'),
            $request->input('font_style', 'modern'),
            $request->user()?->id
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to submit export.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Export started.',
        ]);
    }

    /**
     * Normalize content for tools/ppt POST /export (same shape as zooys processExportJob).
     * Frontend sends slides with { title, content }; tools/ppt expects slide_number, header, subheaders, slide_type, content (string).
     */
    private function transformContentForExport(array $content): array
    {
        $title = $content['title'] ?? 'Presentation';
        $slidesRaw = $content['slides'] ?? [];
        if (! is_array($slidesRaw)) {
            $slidesRaw = [];
        }

        $slides = [];
        foreach ($slidesRaw as $i => $slide) {
            if (! is_array($slide)) {
                continue;
            }
            $contentVal = $slide['content'] ?? '';
            if (is_array($contentVal)) {
                $contentVal = implode("\n", $contentVal);
            }
            $slides[] = [
                'slide_number' => (int) ($slide['slide_number'] ?? $i + 1),
                'header' => (string) ($slide['header'] ?? $slide['title'] ?? ''),
                'subheaders' => isset($slide['subheaders']) && is_array($slide['subheaders']) ? $slide['subheaders'] : [],
                'slide_type' => (string) ($slide['slide_type'] ?? 'content'),
                'content' => (string) $contentVal,
            ];
        }

        return [
            'title' => (string) $title,
            'slides' => $slides,
        ];
    }

    /**
     * GET /api/v1/{region}/presentations/templates
     * Returns available presentation styles for step 3 (user chooses before export).
     */
    public function templates(Request $request)
    {
        $templates = $this->pptClient->getTemplates();
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * GET /api/v1/{region}/presentations/status?job_id=
     */
    public function status(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->pptClient->getJobStatus($jobId);
        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        return response()->json([
            'success' => true,
            'status' => $data['status'] ?? $result['status'] ?? 'unknown',
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/{region}/presentations/result?job_id=
     */
    public function result(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->pptClient->getJobResult($jobId);
        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
        }

        $data = $result['data'] ?? [];
        $inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $response = ['success' => true, 'data' => $inner, 'metadata' => is_array($data) && isset($data['metadata']) ? $data['metadata'] : null];
        if (is_array($inner) && !empty($inner['file_content'])) {
            $decoded = base64_decode($inner['file_content'], true);
            if ($decoded !== false) {
                $fileId = (string) Str::uuid();
                $rawName = $inner['filename'] ?? 'presentation';
                $filename = str_ends_with(strtolower($rawName), '.pptx') ? $rawName : $rawName . '.pptx';
                $path = 'presentations/' . $fileId . '.pptx';
                if (! Storage::disk('local')->exists('presentations')) {
                    Storage::disk('local')->makeDirectory('presentations');
                }
                $written = Storage::disk('local')->put($path, $decoded);
                if ($written) {
                    GeneratedFile::create(['id' => $fileId, 'path' => $path, 'filename' => $filename, 'type' => 'presentation']);
                    $response['file_id'] = $fileId;
                    $response['filename'] = $filename;
                    unset($inner['file_id'], $inner['file_content']);
                    $response['data'] = $inner;
                } else {
                    \Illuminate\Support\Facades\Log::error('[PresentationController] Failed to save presentation file', ['path' => $path, 'job_id' => $jobId]);
                }
            }
        }
        return response()->json($response);
    }

    /**
     * GET /api/v1/{region}/presentations/files/{fileId}/download
     */
    public function download(Request $request, string $fileId)
    {
        $file = GeneratedFile::where('id', $fileId)->where('type', 'presentation')->first();
        if (! $file) {
            \Illuminate\Support\Facades\Log::warning('[PresentationController] Download: no record', ['file_id' => $fileId]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        if (! Storage::disk('local')->exists($file->path)) {
            \Illuminate\Support\Facades\Log::warning('[PresentationController] Download: record exists but file missing (check storage or use shared disk for multiple servers)', ['file_id' => $fileId, 'path' => $file->path]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($file->path, $file->filename ?? $fileId . '.pptx');
    }
}
