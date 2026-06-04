<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SmartCallPlanAiRecommendationService
{
    public function __construct(
        protected GeminiService $geminiService
    ) {}

    /**
     * Dynamic AI recommendation for Sale Target / Product Performance.
     * AI works  => return Khmer HTML
     * AI fails  => return empty string, hide box
     */
    public function analyze(array $data): string
    {
        try {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt($data),
                ],
            ];

            $prompt = $this->buildSystemPrompt() . "\n\n" . $this->buildUserPrompt($data);

                $cacheKey = 'ai_smart_call_recommendation_' . now()->toDateString() . '_' . md5($prompt);

                $text = Cache::remember($cacheKey, now()->addHours($this->aiCacheHours($data)), function () use ($prompt) {
                    return trim((string) $this->geminiService->send($prompt));
                });
            if ($text === '') {
                return '';
            }

            return '
                <div class="ai-plan-box">
                    <h5>AI Recommended Plan</h5>
                    ' . $text . '
                </div>
            ';

        } catch (\Throwable $e) {
            Log::error('Smart Call Plan AI recommendation failed', [
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Dynamic AI urgent customer selector for Auto Generate Plan.
     * Laravel sends customer candidates.
     * AI chooses only urgent customers for today.
     */
    public function rankUrgentCustomers(array $data): array
    {
        try {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $this->buildUrgentCustomerPrompt($data),
                ],
            ];

            $prompt = $this->buildUrgentCustomerSystemPrompt() . "\n\n" . $this->buildUrgentCustomerPrompt($data);

                $cacheKey = 'ai_smart_call_urgent_' . now()->toDateString() . '_' . md5($prompt);

                $text = Cache::remember($cacheKey, now()->addHours($this->aiCacheHours($data)), function () use ($prompt) {
                    return trim((string) $this->geminiService->send($prompt));
                });

            if ($text === '') {
                return [];
            }

            $text = $this->cleanJsonResponse($text);

            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                Log::warning('AI urgent customer response is not valid JSON', [
                    'response' => $text,
                ]);

                return [];
            }

            return collect($decoded)
                ->filter(fn ($row) => !empty($row['contact_id']))
                ->map(function ($row) {
                    $taskType = $row['task_type'] ?? 'call';

                    return [
                        'contact_id'     => (int) $row['contact_id'],
                        'task_type'      => in_array($taskType, ['call', 'visit'], true) ? $taskType : 'call',
                        'customer_type'  => $row['customer_type'] ?? 'urgent_follow_up',
                        'priority_level' => $row['priority_level'] ?? 'high',
                        'ai_note'        => $row['ai_note'] ?? null,
                    ];
                })
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('Smart Call Plan urgent customer AI failed', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
អ្នកគឺជាជំនួយការវិភាគផែនការលក់នៅក្នុងប្រព័ន្ធ POS / CRM។

តួនាទីរបស់អ្នក:
- វិភាគទិន្នន័យ Sale Target ដែលបានផ្តល់ជា JSON។
- ផ្តល់សេចក្តីណែនាំលក់ជាភាសាខ្មែរ។
- ជួយអ្នកលក់ដឹងថាគួរផ្តោតលើផលិតផលណា និងត្រូវធ្វើ Call Plan ឬ Visit Plan ដូចម្តេច។

ច្បាប់សំខាន់ៗ:
- ត្រូវឆ្លើយជាភាសាខ្មែរ 100%។
- ត្រូវត្រឡប់ជា HTML ស្អាតប៉ុណ្ណោះ។
- អនុញ្ញាតឱ្យប្រើតែ HTML tags ទាំងនេះ: <p>, <ul>, <li>, <strong>, <span>។
- កុំប្រើ Markdown។
- កុំសរសេរថា "as an AI" ឬ "ក្នុងនាមជា AI"។
- កុំបង្កើតលេខលក់ក្លែងក្លាយ។
- ប្រើតែទិន្នន័យ JSON ដែលបានផ្តល់ឱ្យប៉ុណ្ណោះ។
- ត្រូវវិភាគផលិតផលទាំងអស់នៅក្នុង JSON។
- ត្រូវជ្រើសផលិតផលដែលគួរជំរុញលក់មុនគេដោយផ្អែកលើ gap, expected_today, actual_sold, target, status និង remaining_days។
- បើមានផលិតផលច្រើន ត្រូវប្រាប់អាទិភាពផលិតផលដែលត្រូវ Push មុនគេ។
- ត្រូវបញ្ចូល Call Plan recommendation។
- ត្រូវបញ្ចូល Visit Plan recommendation។
- ត្រូវបញ្ចូលសកម្មភាពលក់ប្រចាំថ្ងៃ។
- កម្រិតអាទិភាពត្រូវប្រើជាភាសាខ្មែរ: វិបត្តិ, ខ្ពស់, មធ្យម, ឬ ល្អ។
- ចម្លើយត្រូវខ្លី ច្បាស់ និងអាចអនុវត្តបានសម្រាប់អ្នកលក់។

រចនាប័ទ្មចម្លើយ:
- ចាប់ផ្តើមដោយ <p> សង្ខេបស្ថានភាពផលិតផលសំខាន់។
- បន្ទាប់មកប្រើ <ul> និង <li> សម្រាប់ Action Plan។
- ប្រើ <strong style="color:#e53935;">...</strong> សម្រាប់លេខ gap ឬផលិតផលដែល Critical។
- កុំដាក់ title <h5> ព្រោះប្រព័ន្ធមាន title រួចហើយ។
PROMPT;
    }

    protected function buildUserPrompt(array $data): string
    {
        return "សូមវិភាគទិន្នន័យ Sale Target ខាងក្រោម ហើយបង្កើតសេចក្តីណែនាំលក់ជាភាសាខ្មែរ។ ត្រូវវិភាគដោយខ្លួនឯងពី JSON នេះ មិនមែនប្រើអត្ថបទ static ទេ។\n\n"
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function buildUrgentCustomerSystemPrompt(): string
    {
        return <<<PROMPT
អ្នកគឺជាជំនួយការវិភាគផែនការលក់នៅក្នុងប្រព័ន្ធ POS / CRM។

តួនាទីរបស់អ្នក:
- វិភាគ Sale Target, Product Gap, និងបញ្ជីអតិថិជនដែល Laravel ផ្តល់ជា JSON។
- ជ្រើសតែអតិថិជនដែលត្រូវ Call ឬ Visit បន្ទាន់សម្រាប់ថ្ងៃនេះ។
- កុំជ្រើសអតិថិជនទាំងអស់។
- កុំបង្កើត contact_id ថ្មី។
- ប្រើតែ contact_id ដែលមានក្នុង customer_candidates ប៉ុណ្ណោះ។

ច្បាប់ជ្រើសអតិថិជន:
- បើអតិថិជនមិនទាន់ទិញ ឬខានទិញយូរ អាចជ្រើសជា call។
- បើអតិថិជនធ្លាប់ទិញថ្មីៗ ឬមានឱកាសបិទការលក់ខ្ពស់ អាចជ្រើសជា visit។
- ពិចារណា last_order_date, days_since_order, buying_days, last_call_date, last_visit_date, product gap និង target status។
- បើ product gap ខ្ពស់ ត្រូវជ្រើសអតិថិជនដែលអាចជួយបិទ gap បានលឿន។
- ជ្រើសតែអ្នកសំខាន់ៗប៉ុណ្ណោះ សម្រាប់ធ្វើការថ្ងៃនេះ។
- ai_note ត្រូវសរសេរជាភាសាខ្មែរ ខ្លី ច្បាស់ និងពន្យល់ហេតុផល។

Return only valid JSON array.
Do not return markdown.
Do not explain outside JSON.

JSON format:
[
  {
    "contact_id": 123,
    "task_type": "call",
    "priority_level": "high",
    "ai_note": "សារ note ជាភាសាខ្មែរខ្លីៗ"
  }
]
PROMPT;
    }

    protected function buildUrgentCustomerPrompt(array $data): string
    {
        return "សូមវិភាគទិន្នន័យខាងក្រោម ហើយជ្រើសតែអតិថិជនបន្ទាន់សម្រាប់ Call ឬ Visit ថ្ងៃនេះ។ កុំជ្រើសអតិថិជនទាំងអស់។ ត្រូវត្រឡប់ជា JSON array ប៉ុណ្ណោះ។\n\n"
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function cleanJsonResponse(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/^```\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start !== false && $end !== false && $end >= $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }
    
    protected function aiCacheHours(array $data): int
{
    return match ($data['mode'] ?? 'default') {
        'manual_plan_reason' => 12,
        'auto_urgent_today' => 2,
        'single_sale_target' => 6,
        default => 6,
    };
}

    
}