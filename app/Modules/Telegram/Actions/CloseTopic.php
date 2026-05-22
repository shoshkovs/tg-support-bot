<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Telegram\Support\TelegramBotRegistry;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;

class CloseTopic
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        if ($botUser->isClosed()) {
            return;
        }

        $slug = $botUser->telegram_bot_slug ?? 'default';
        $groupId = TelegramBotRegistry::groupId($slug);
        $token = TelegramBotRegistry::token($slug);

        switch ($botUser->platform) {
            case 'telegram':
                $this->sendMessageInTelegram($botUser);
                break;

            case 'vk':
                $this->sendMessageInVk($botUser);
                break;
        }

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'token' => $token,
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.outgoing'),
        ]));

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'closeForumTopic',
            'token' => $token,
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
        ]));

        $botUser->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    private function sendMessageInTelegram(BotUser $botUser): void
    {
        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => __('messages.message_close_topic'),
            'parse_mode' => 'html',
        ]));
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    private function sendMessageInVk(BotUser $botUser): void
    {
        SendVkSimpleMessageJob::dispatch(
            VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $botUser->chat_id,
                'message' => __('messages.message_close_topic'),
            ]),
        );
    }
}
