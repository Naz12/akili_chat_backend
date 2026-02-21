<?php

namespace Tests\Feature;

use App\Services\PresentationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PptGenerationE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.presentation.url' => 'https://ppt.akmicroservice.com',
            'services.presentation.api_key' => 'test-key',
            'services.presentation.outline_path' => '/outline',
            'services.presentation.content_path' => '/content',
            'services.presentation.ppt_path' => '/generate',
        ]);
    }

    /** @test */
    public function outline_content_and_ppt_are_generated_in_full_pipeline(): void
    {
        $mockOutline = [
            ['title' => 'Introduction'],
            ['title' => 'George Washington'],
            ['title' => 'Summary'],
        ];
        $mockContent = array_map(fn ($s) => ['title' => $s['title'], 'body' => 'Content for ' . $s['title']], $mockOutline);
        Http::fake([
            'https://ppt.akmicroservice.com/*' => Http::sequence()
                ->push(['outline' => $mockOutline, 'outline_id' => 'mock-1'], 200)
                ->push(['content' => $mockContent, 'slides' => $mockContent], 200)
                ->push(['ppt_url' => 'https://example.com/out.pptx', 'file_url' => 'https://example.com/out.pptx'], 200),
        ]);

        $service = app(PresentationService::class);
        $result = $service->generateFull('US Presidents', 3);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['outline']);
        $this->assertCount(3, $result['outline']);
        $this->assertNotEmpty($result['content']);
        $this->assertSame('https://example.com/out.pptx', $result['ppt_url']);
    }

    /** @test */
    public function chat_api_returns_outline_for_ppt_message(): void
    {
        $mockOutline = [
            ['title' => 'Slide One'],
            ['title' => 'Slide Two'],
        ];
        Http::fake([
            'https://ppt.akmicroservice.com/*' => Http::response(['outline' => $mockOutline, 'outline_id' => 'm1'], 200),
        ]);

        $response = $this->postJson('/api/v1/local/chat', [
            'message' => 'Generate a presentation about Climate Change with 2 slides',
            'session_id' => null,
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('reply', $data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertNotEmpty($data['session_id']);
        $this->assertStringContainsString('outline', strtolower($data['reply']));
        $this->assertStringContainsString('Slide', $data['reply']);
    }
}
