<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderInterface;
use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Service Provider - интеграция с Python микросервисом AI Service
 */
class AiServiceProvider extends BaseAiProvider
{
    protected string $serviceUrl;

    public function __construct()
    {
        parent::__construct('aiservice');
        $this->serviceUrl = $this->config['service_url'] ?? 'http://ai-service:8000';
    }

    /**
     * Check if AI Service is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->serviceUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("AI Service недоступен: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Generate AI response using AI Service microservice
     *
     * @param AiRequestDto $request
     * @return AiResponseDto
     */
    public function generateResponse(AiRequestDto $request): AiResponseDto
    {
        $startTime = microtime(true);

        try {
            // Проверка rate limit
            if (!$this->checkRateLimit()) {
                throw new \RuntimeException('Rate limit exceeded for AI Service');
            }

            // Подготовка сообщений для AI Service
            $messages = $this->prepareMessages($request);

            // Вызов AI Service
            $response = Http::timeout(60)
                ->post("{$this->serviceUrl}/api/v1/chat/completions", [
                    'messages' => $messages,
                    'temperature' => $this->config['temperature'] ?? 0.7,
                    'max_tokens' => $this->config['max_tokens'] ?? 2000,
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "AI Service error: {$response->status()} - {$response->body()}"
                );
            }

            $data = $response->json();
            $responseTime = microtime(true) - $startTime;

            // Извлечение данных из ответа
            $content = $data['content'] ?? '';
            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

            // Вычисление confidence score (упрощенная версия)
            $confidenceScore = $this->calculateConfidenceScore($content);

            return $this->createResponse(
                response: $content,
                confidenceScore: $confidenceScore,
                shouldEscalate: $this->shouldEscalate($confidenceScore),
                tokensUsed: $tokensUsed,
                responseTime: $responseTime,
                metadata: [
                    'model' => $data['model'] ?? 'deepseek-chat',
                    'service' => 'ai-service',
                    'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                ]
            );

        } catch (\Exception $e) {
            Log::error("AI Service error: {$e->getMessage()}", [
                'user_message' => $request->userMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Prepare messages for AI Service
     *
     * @param AiRequestDto $request
     * @return array
     */
    protected function prepareMessages(AiRequestDto $request): array
    {
        $messages = [];

        // Системный промпт
        if (!empty($request->systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $request->systemPrompt,
            ];
        } else {
            $messages[] = [
                'role' => 'system',
                'content' => $this->buildSystemPrompt(),
            ];
        }

        // История сообщений (контекст)
        if (!empty($request->conversationHistory)) {
            foreach ($request->conversationHistory as $msg) {
                $messages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        // Текущее сообщение пользователя
        $messages[] = [
            'role' => 'user',
            'content' => $request->userMessage,
        ];

        return $messages;
    }

    /**
     * Calculate confidence score based on response
     *
     * @param string $response
     * @return float
     */
    protected function calculateConfidenceScore(string $response): float
    {
        // Упрощенная логика расчета confidence score
        // В реальной реализации можно использовать более сложные алгоритмы

        $score = 0.8; // Базовый score

        // Проверка длины ответа
        $length = mb_strlen($response);
        if ($length < 10) {
            $score -= 0.3;
        } elseif ($length > 50) {
            $score += 0.1;
        }

        // Проверка на наличие ключевых фраз неуверенности
        $uncertainPhrases = [
            'не уверен',
            'возможно',
            'может быть',
            'не знаю',
            'затрудняюсь',
        ];

        foreach ($uncertainPhrases as $phrase) {
            if (mb_stripos($response, $phrase) !== false) {
                $score -= 0.2;
                break;
            }
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Test connection to AI Service
     *
     * @return array
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->serviceUrl}/health");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'AI Service доступен',
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => "AI Service недоступен: {$response->status()}",
                'data' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Ошибка подключения: {$e->getMessage()}",
                'data' => null,
            ];
        }
    }
}

// Made with Bob
