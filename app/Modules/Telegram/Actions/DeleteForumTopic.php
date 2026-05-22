<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\Support\TelegramBotRegistry;

/**
 * Delete forum topic.
 */
class DeleteForumTopic
{
    /**
     * Delete forum topic.
     *
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        $slug = $botUser->telegram_bot_slug ?? 'default';

        TelegramMethods::sendQueryTelegram('deleteForumTopic', [
            'chat_id' => TelegramBotRegistry::groupId($slug),
            'message_thread_id' => $botUser->topic_id,
        ], TelegramBotRegistry::token($slug));
    }
}
