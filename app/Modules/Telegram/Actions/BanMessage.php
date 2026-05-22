<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Support\TelegramBotRegistry;

class BanMessage
{
    /**
     * Send message indicating that user has blocked the bot.
     *
     * @param int   $botUserId
     * @param mixed $update
     *
     * @return void
     */
    public function execute(int $botUserId, mixed $update): void
    {
        $botUser = BotUser::find($botUserId);

        SendTelegramMessageJob::dispatch(
            $botUser->id,
            $update,
            TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'token' => TelegramBotRegistry::token($botUser->telegram_bot_slug ?? 'default'),
                'typeSource' => 'supergroup',
                'chat_id' => TelegramBotRegistry::groupId($botUser->telegram_bot_slug ?? 'default'),
                'message_thread_id' => $botUser->topic_id,
                'text' => __('messages.ban_bot'),
                'parse_mode' => 'html',
            ]),
            'incoming',
        );
    }
}
