<?php

namespace App\Modules\Telegram\Actions;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;

/**
 * Get chat.
 */
class GetChat
{
    /**
     * Get Telegram chat.
     *
     * @param int         $chatId
     * @param string|null $botToken Bot API token (defaults to primary bot from config)
     *
     * @return TelegramAnswerDto
     */
    public function execute(int $chatId, ?string $botToken = null): TelegramAnswerDto
    {
        return TelegramMethods::sendQueryTelegram('getChat', [
            'chat_id' => $chatId,
        ], $botToken);
    }
}
