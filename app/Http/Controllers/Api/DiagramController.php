<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Models\Plan;
use App\Services\GuestUserService;
use App\Services\Tools\DiagramMicroserviceClient;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiagramController extends Controller
{
    public function __construct(
        protected DiagramMicroserviceClient $diagramClient,
        protected UsageValidatorService $usageValidator,
        protected GuestUserService $guestUserService
    ) {}

    private function checkQuota(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $guestSession = null;
        $plan = null;
        if (! $user) {
            $guestSession = $this->guestUserService->getGuestSessionFromRequest($request);
            $region = $request->segment(3) ?? 'local';
            $plan = Plan::where('is_default', true)->where('region', $region)->first() ?? Plan::where('is_default', true)->first();
        }
        $result = $this->usageValidator->checkQuota($user, false, $guestSession, $plan);
        if (! empty($result['error'])) {
            return response()->json(['error' => $result['message'] ?? 'Quota exceeded.'], 403);
        }
        return null;
    }

    /**
     * POST /api/v1/{region}/diagram/generate
     */
    public function generate(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'prompt' => 'required|string|max:8000',
            'diagram_type' => 'nullable|string|max:50',
            'output_format' => 'nullable|string|max:20',
        ]);

        $result = $this->diagramClient->generateDiagram(
            $request->input('prompt'),
            $request->input('diagram_type', 'flowchart'),
            $request->input('output_format', 'png')
        );

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Diagram service is temporarily unavailable. Please try again later.'], 502);
        }
        if (empty($result['job_id'])) {
            return response()->json(['success' => false, 'error' => 'Diagram service did not return a job. Please try again.'], 502);
        }
        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Diagram generation started.',
        ]);
    }

    /**
     * GET /api/v1/{region}/diagram/status?job_id=
     */
    public function status(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->diagramClient->getJobStatus($jobId);
        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        $status = $data['status'] ?? $result['status'] ?? 'unknown';
        $response = [
            'success' => true,
            'status' => $status,
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ];
        if ($status === 'failed') {
            $response['error'] = $data['error'] ?? $data['message'] ?? 'Diagram generation failed. Please try again.';
        }
        return response()->json($response);
    }

    /**
     * GET /api/v1/{region}/diagram/result?job_id=
     * Stores diagram file when microservice returns download_url or image data; returns file_id.
     */
    public function result(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->diagramClient->getJobResult($jobId);
        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
        }

        $data = $result['data'] ?? [];
        $inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $response = ['success' => true, 'data' => $inner];

        if (! is_array($inner)) {
            return response()->json($response);
        }

        $downloadUrl = $inner['download_url'] ?? $inner['image_url'] ?? null;
        $imageBase64 = $inner['image_base64'] ?? $inner['file_content'] ?? null;
        $fileId = (string) Str::uuid();
        $ext = $inner['output_format'] ?? 'png';
        if (! preg_match('/^[a-z0-9]+$/i', $ext)) {
            $ext = 'png';
        }
        $filename = 'diagram.' . $ext;
        $path = 'diagrams/' . $fileId . '.' . $ext;

        if (! Storage::disk('local')->exists('diagrams')) {
            Storage::disk('local')->makeDirectory('diagrams');
        }

        if (! empty($downloadUrl)) {
            try {
                $imageResponse = Http::timeout(30)->get($downloadUrl);
                if ($imageResponse->successful()) {
                    $written = Storage::disk('local')->put($path, $imageResponse->body());
                    if ($written) {
                        GeneratedFile::create([
                            'id' => $fileId,
                            'path' => $path,
                            'filename' => $filename,
                            'type' => 'diagram',
                        ]);
                        $response['file_id'] = $fileId;
                        $response['filename'] = $filename;
                    } else {
                        \Illuminate\Support\Facades\Log::error('[DiagramController] Failed to save diagram file from URL', ['path' => $path, 'job_id' => $jobId]);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[DiagramController] Failed to fetch diagram from URL', ['url' => $downloadUrl, 'error' => $e->getMessage()]);
            }
        } elseif (! empty($imageBase64)) {
            $decoded = base64_decode($imageBase64, true);
            if ($decoded !== false) {
                $written = Storage::disk('local')->put($path, $decoded);
                if ($written) {
                    GeneratedFile::create([
                        'id' => $fileId,
                        'path' => $path,
                        'filename' => $filename,
                        'type' => 'diagram',
                    ]);
                    $response['file_id'] = $fileId;
                    $response['filename'] = $filename;
                } else {
                    \Illuminate\Support\Facades\Log::error('[DiagramController] Failed to save diagram file from base64', ['path' => $path, 'job_id' => $jobId]);
                }
            }
        }

        return response()->json($response);
    }

    /**
     * GET /api/v1/{region}/diagram/files/{fileId}/download
     */
    public function download(Request $request, string $fileId)
    {
        $file = GeneratedFile::where('id', $fileId)->where('type', 'diagram')->first();
        if (! $file) {
            \Illuminate\Support\Facades\Log::warning('[DiagramController] Download: no record', ['file_id' => $fileId]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        if (! Storage::disk('local')->exists($file->path)) {
            \Illuminate\Support\Facades\Log::warning('[DiagramController] Download: record exists but file missing (check storage or use shared disk for multiple servers)', ['file_id' => $fileId, 'path' => $file->path]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($file->path, $file->filename ?? $fileId . '.png');
    }
}
