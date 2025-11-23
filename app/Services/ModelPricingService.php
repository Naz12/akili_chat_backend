<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ModelPricingService
{
    public function getModels(): array
    {
        return Cache::remember('ai_models_cache', 3600, function () {
            return [
                [
                    'name' => 'GPT-3.5 Turbo',
                    'provider' => 'OpenAI',
                    'price' => 0.0015,
                    'tokens' => 4096,
                    'api' => 'https://api.openai.com/v1/chat/completions',
                    'model_type' => 'chat',
                    'version' => 'gpt-3.5-turbo',
                ],
                [
                    'name' => 'GPT-4o',
                    'provider' => 'OpenAI',
                    'price' => 0.005,
                    'tokens' => 128000,
                    'api' => 'https://api.openai.com/v1/chat/completions',
                    'model_type' => 'chat',
                    'version' => 'gpt-4o',
                ],
                [
                    'name' => 'Claude 3 Opus',
                    'provider' => 'Anthropic',
                    'price' => 0.008,
                    'tokens' => 200000,
                    'api' => 'https://api.anthropic.com/v1/messages',
                    'model_type' => 'chat',
                    'version' => 'claude-3-opus-20240229',
                ],
                [
                    'name' => 'Claude 3 Sonnet',
                    'provider' => 'Anthropic',
                    'price' => 0.003,
                    'tokens' => 200000,
                    'api' => 'https://api.anthropic.com/v1/messages',
                    'model_type' => 'chat',
                    'version' => 'claude-3-sonnet-20240229',
                ],
                [
                    'name' => 'Gemini Pro',
                    'provider' => 'Google',
                    'price' => 0.0025,
                    'tokens' => 32768,
                    'api' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'model_type' => 'chat',
                    'version' => 'gemini-pro',
                ],
                [
                    'name' => 'Gemini 1.5 Flash',
                    'provider' => 'Google',
                    'price' => 0.0003,
                    'tokens' => 128000,
                    'api' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'model_type' => 'chat',
                    'version' => 'gemini-1.5-flash',
                ],
                [
                    'name' => 'Mistral 7B Instruct',
                    'provider' => 'Mistral',
                    'price' => 0.0002,
                    'tokens' => 32000,
                    'api' => 'https://api.mistral.ai/v1/chat/completions',
                    'model_type' => 'chat',
                    'version' => 'mistral-7b-instruct',
                ],
                [
                    'name' => 'Mixtral 8x7B',
                    'provider' => 'Mistral',
                    'price' => 0.00045,
                    'tokens' => 32000,
                    'api' => 'https://api.mistral.ai/v1/chat/completions',
                    'model_type' => 'chat',
                    'version' => 'mixtral-8x7b',
                ],
                [
                    'name' => 'DeepSeek Chat',
                    'provider' => 'DeepSeek',
                    'price' => 0.0005,
                    'tokens' => 32000,
                    'api' => 'https://api.deepseek.com/v1/chat/completions',
                    'model_type' => 'chat',
                    'version' => 'deepseek-chat',
                ],
                [
                    'name' => 'DeepSeek Coder',
                    'provider' => 'DeepSeek',
                    'price' => 0.0008,
                    'tokens' => 32768,
                    'api' => 'https://api.deepseek.com/v1/chat/completions',
                    'model_type' => 'code',
                    'version' => 'deepseek-coder',
                ]
            ];
        });
    }
}