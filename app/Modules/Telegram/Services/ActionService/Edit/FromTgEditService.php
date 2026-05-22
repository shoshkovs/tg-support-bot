<?php

namespace App\Modules\Telegram\Services\ActionService\Edit;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Support\TelegramBotRegistry;
use phpDocumentor\Reflection\Exception;

/**
 * Class FromTgEditService
 */
abstract class FromTgEditService extends TemplateEditService
{
    public function __construct(mixed $update)
    {
        $this->update = $update;
        $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->update);

        if (empty($this->botUser)) {
            throw new Exception('User does not exist!');
        }

        switch ($update->typeSource) {
            case 'private':
                $this->typeMessage = 'incoming';

                // Используем slug бота из BotUser для определения правильной группы
                $botSlug = $this->botUser->telegram_bot_slug ?? 'default';
                $groupId = TelegramBotRegistry::groupId($botSlug);
                $queryParams = [
                    'chat_id' => $groupId,
                    'message_thread_id' => $this->botUser->topic_id,
                ];
                $token = TelegramBotRegistry::token($botSlug);
                break;

            case 'supergroup':
                $this->typeMessage = 'outgoing';
                // Используем slug бота из BotUser для отправки ответа правильным ботом
                $botSlug = $this->botUser->telegram_bot_slug ?? 'default';
                $queryParams = [
                    'chat_id' => $this->botUser->chat_id,
                ];
                $token = TelegramBotRegistry::token($botSlug);
                break;

            default:
                throw new Exception('This request type is not supported!');
        }

        $queryParams['methodQuery'] = 'sendMessage';
        $queryParams['typeSource'] = $update->typeSource;
        $queryParams['token'] = $token;

        $this->messageParamsDTO = TGTextMessageDto::from($queryParams);
    }

    /**
     * Edit text message.
     *
     * @return void
     */
    abstract protected function editMessageText(): void;

    /**
     * Edit message with photo or document.
     *
     * @return void
     */
    abstract protected function editMessageCaption(): void;
}
