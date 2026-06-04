<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function send(string $prompt): ?string
    {
        $key = config('services.gemini.key');

        if (!$key) {
            return null;
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => $key,
            'Content-Type' => 'application/json',
        ])
        ->withOptions([
            'connect_timeout' => 60,
            'timeout' => 120,
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])
        ->post(config('services.gemini.url'), [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => (int) config('services.gemini.max_tokens', 300),
            ],
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json('candidates.0.content.parts.0.text');
    }
}