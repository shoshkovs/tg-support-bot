<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;

class SendBannedMessage
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        $botSlug = $botUser->telegram_bot_slug ?? 'default';
        $token = \App\Modules\Telegram\Support\TelegramBotRegistry::token($botSlug);

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'token' => $token,
            'chat_id' => $botUser->chat_id,
            'text' => __('messages.ban_user'),
            'parse_mode' => 'html',
        ]));
    }
}
