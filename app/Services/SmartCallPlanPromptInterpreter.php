<?php

namespace App\Services;

class SmartCallPlanPromptInterpreter
{
    public function interpret(string $prompt): array
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $prompt)));

        $intent = [
            'action' => 'chat',
            'metric' => 'board',
            'period' => $this->detectPeriod($text),
            'business_id' => null,
            'location_id' => null,
            'limit' => 5,
            'customer_type' => null,
            'raw_prompt' => $prompt,
        ];

        if ($text === '') {
            return $intent;
        }

        if ($this->isGreeting($text) || $this->containsAny($text, [
            'help',
            'what can you do',
            'example',
            'examples',
            'guide me',
            'how to use',
        ])) {
            return $intent;
        }

        $intent['customer_type'] = $this->detectCustomerType($text);
        $intent['metric'] = $this->detectMetric($text);

        preg_match('/business\s+(\d+)/i', $prompt, $businessMatch);
        preg_match('/location\s+(\d+)/i', $prompt, $locationMatch);
        preg_match('/limit\s+(\d+)/i', $prompt, $limitMatch);
        preg_match('/top\s+(\d+)/i', $prompt, $topMatch);

        $intent['business_id'] = isset($businessMatch[1]) ? (int) $businessMatch[1] : null;
        $intent['location_id'] = isset($locationMatch[1]) ? (int) $locationMatch[1] : null;

        if (isset($topMatch[1])) {
            $intent['limit'] = (int) $topMatch[1];
        } elseif (isset($limitMatch[1])) {
            $intent['limit'] = (int) $limitMatch[1];
        }

        $hasSyncWord = $this->containsAny($text, [
            'sync',
            'fetch',
            'load',
            'import',
            'pull',
            'refresh',
            'update data',
            'update customer',
            'get customer',
            'get customers',
            'get data',
            'bring customer',
            'bring customers',
        ]);

        $hasGenerateVerb = $this->containsAny($text, [
            'generate',
            'create',
            'make',
            'build',
            'prepare',
            'produce',
            'regenerate',
        ]);

        $hasPlanWord = $this->containsAny($text, [
            'call plan',
            'smart call plan',
            'plan list',
            'follow up list',
            'follow-up list',
            'priority list',
            'prioritize',
            'today plan',
        ]);

        $hasShowWord = $this->containsAny($text, [
            'show',
            'list',
            'view',
            'display',
            'see',
            'who',
            'which',
            'give me',
        ]);

        $hasTopSellerPattern = $this->hasTopSellerPattern($text);

        $hasStatsWord = $hasTopSellerPattern || $this->containsAny($text, [
            'how many',
            'count',
            'total',
            'number of',
            'do we have',
            'did we have',
            'is there',
            'are there',
            'any ',
            'sale',
            'sales',
            'revenue',
        ]);

        if ($hasSyncWord && ($hasGenerateVerb || $hasPlanWord)) {
            $intent['action'] = 'sync_and_generate';
            return $intent;
        }

        if ($hasSyncWord) {
            $intent['action'] = 'sync';
            return $intent;
        }

        if ($hasGenerateVerb || ($hasPlanWord && !$hasShowWord && !$hasStatsWord)) {
            $intent['action'] = 'generate';
            return $intent;
        }

        if ($hasStatsWord) {
            $intent['action'] = 'stats';
            return $intent;
        }

        if ($hasShowWord) {
            $intent['action'] = 'show';
            return $intent;
        }

        return $intent;
    }

    protected function isGreeting(string $text): bool
    {
        $greetings = [
            'hi',
            'hello',
            'hey',
            'good morning',
            'good afternoon',
            'good evening',
            'how are you',
            'thanks',
            'thank you',
        ];

        return in_array($text, $greetings, true);
    }

    protected function detectCustomerType(string $text): ?string
    {
        if (str_contains($text, 'retail')) {
            return 'Retail';
        }

        if (str_contains($text, 'wholesale')) {
            return 'Wholesale';
        }

        if (str_contains($text, 'customer')) {
            return 'customer';
        }

        return null;
    }

    protected function detectMetric(string $text): string
    {
        if ($this->hasTopSellerPattern($text)) {
            return 'top_seller';
        }

        if ($this->containsAny($text, ['sale', 'sales', 'order', 'orders', 'invoice', 'invoices'])) {
            return 'sales';
        }

        if ($this->containsAny($text, ['pending', 'to call', 'to_call'])) {
            return 'pending';
        }

        if ($this->containsAny($text, ['follow up', 'follow-up', 'follow_up'])) {
            return 'follow_up';
        }

        if ($this->containsAny($text, ['completed', 'done', 'finished'])) {
            return 'completed';
        }

        if ($this->containsAny($text, ['skipped', 'skip', 'no answer', 'no_answer'])) {
            return 'skipped';
        }

        if ($this->containsAny($text, ['business', 'businesses'])) {
            return 'businesses';
        }

        if ($this->containsAny($text, ['location', 'locations', 'branch', 'branches'])) {
            return 'locations';
        }

        if ($this->containsAny($text, ['customer', 'customers', 'client', 'clients', 'contact', 'contacts'])) {
            return 'customers';
        }

        return 'board';
    }

    protected function detectPeriod(string $text): string
    {
        if (str_contains($text, 'today')) {
            return 'today';
        }

        if (str_contains($text, 'this week') || str_contains($text, 'weekly') || str_contains($text, 'week')) {
            return 'week';
        }

        if (str_contains($text, 'this year') || str_contains($text, 'yearly') || str_contains($text, 'year')) {
            return 'year';
        }

        return 'month';
    }

    protected function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function hasTopSellerPattern(string $text): bool
    {
        if (preg_match('/\btop\s+\d+\s+sellers?\b/i', $text)) {
            return true;
        }

        if (preg_match('/\btop\s+sellers?\b/i', $text)) {
            return true;
        }

        if (preg_match('/\btop\s+saler\b/i', $text)) {
            return true;
        }

        if (preg_match('/\bbest\s+sellers?\b/i', $text)) {
            return true;
        }

        if (preg_match('/\bwho\s+is\s+top\s+seller\b/i', $text)) {
            return true;
        }

        return false;
    }
}