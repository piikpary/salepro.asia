<?php

namespace App\Services;

use App\SmartCallPlanChat;
use InvalidArgumentException;

class SmartCallPlanAiService
{
    protected SmartCallPlanPromptInterpreter $interpreter;
    protected SmartCallPlanScopeResolver $scopeResolver;
    protected SmartCallPlanExecutor $executor;

    public function __construct(
        SmartCallPlanPromptInterpreter $interpreter,
        SmartCallPlanScopeResolver $scopeResolver,
        SmartCallPlanExecutor $executor
    ) {
        $this->interpreter = $interpreter;
        $this->scopeResolver = $scopeResolver;
        $this->executor = $executor;
    }

    public function chatAndGenerate(string $prompt, int $userId, int $loginBusinessId): array
    {
        $prompt = trim($prompt);
        $this->storeChat($userId, 'user', $prompt);

        try {
            if ($loginBusinessId <= 0) {
                throw new InvalidArgumentException('Logged-in user business not found.');
            }

            $intent = $this->interpreter->interpret($prompt);
            $intent['raw_prompt'] = $prompt;
            $intent['login_business_id'] = $loginBusinessId;
            $intent['business_id'] = $loginBusinessId;

            $resolvedIntent = $this->scopeResolver->resolve($intent);

            if ((int) ($resolvedIntent['business_id'] ?? 0) !== $loginBusinessId) {
                throw new InvalidArgumentException('You are not allowed to access another business.');
            }

            $result = $this->executor->execute($resolvedIntent);

            $reply = $result['reply'] ?? 'Done.';
            $plans = $result['plans'] ?? [];
            $shouldReload = (bool) ($result['should_reload'] ?? false);

            $this->storeChat($userId, 'assistant', $reply);

            return [
                'reply' => $reply,
                'plans' => $plans,
                'should_reload' => $shouldReload,
            ];
        } catch (\Throwable $e) {
            $reply = $e->getMessage() ?: 'Unable to process your request right now.';

            $this->storeChat($userId, 'assistant', $reply);

            return [
                'reply' => $reply,
                'plans' => [],
                'should_reload' => false,
            ];
        }
    }

    public function chatStream(string $prompt, int $userId, int $loginBusinessId, callable $onChunk): void
    {
        $result = $this->chatAndGenerate($prompt, $userId, $loginBusinessId);
        $reply = $result['reply'] ?? 'Done.';

        $words = preg_split('/\s+/', trim($reply));
        $buffer = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $buffer .= ($buffer === '' ? '' : ' ') . $word;

            if (mb_strlen($buffer) >= 60) {
                $onChunk($buffer);
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $onChunk($buffer);
        }
    }

    protected function storeChat(int $userId, string $role, string $message): void
    {
        try {
            SmartCallPlanChat::create([
                'user_id' => $userId,
                'role' => $role,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
        }
    }
}