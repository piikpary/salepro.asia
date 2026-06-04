<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    public function send(array $messages, ?string $system = null): array
    {
        $payload = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => (int) config('services.anthropic.max_tokens', 2000),
            'messages' => $messages,
        ];

        if (!empty($system)) {
            $payload['system'] = $system;
        }

        Log::info('Anthropic payload', $payload);

        $url = config('services.anthropic.url', 'https://api.anthropic.com/v1/messages');

        $maxAttempts = 3;
        $attempt = 0;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = Http::withHeaders([
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])
                ->withOptions([
                    'connect_timeout' => 30,
                    'timeout' => 120,
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->post($url, $payload);

                $lastResponse = $response;

                if ($response->successful()) {
                    return $response->json();
                }

                $body = $response->body();

                Log::warning('Anthropic API failed attempt', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                if (
                    $response->status() === 529 ||
                    str_contains($body, 'overloaded_error')
                ) {
                    if ($attempt < $maxAttempts) {
                        sleep(2);
                        continue;
                    }

                    throw new \Exception('AI is busy right now. Please try again in a few seconds.');
                }

                throw new \Exception('Anthropic API error: ' . $body);

            } catch (\Throwable $e) {
                Log::warning('Anthropic API exception attempt', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if (
                    str_contains($e->getMessage(), 'cURL error 28') ||
                    str_contains($e->getMessage(), 'Connection timed out') ||
                    str_contains($e->getMessage(), 'overloaded_error') ||
                    str_contains($e->getMessage(), '529')
                ) {
                    if ($attempt < $maxAttempts) {
                        sleep(2);
                        continue;
                    }

                    throw new \Exception('AI is busy right now. Please try again in a few seconds.');
                }

                throw $e;
            }
        }

        throw new \Exception('AI request failed. Please try again later.');
    }
}