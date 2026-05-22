<?php

namespace Tests\Feature\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $topicId = 90001;
        Http::fake([
            'https://api.telegram.org/bot*/getChat*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 1,
                    'type' => 'private',
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
            ], 200),
            'https://api.telegram.org/bot*/createForumTopic*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_thread_id' => $topicId,
                ],
            ], 200),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 1,
                    'date' => time(),
                    'text' => 'contact',
                ],
            ], 200),
        ]);

        $this->dto = TelegramUpdateDtoMock::getDto();
        $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->dto);

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->botUser->refresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            app(DeleteForumTopic::class)->execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_success_send_creates_message_record(): void
    {
        $typeMessage = 'outgoing';

        $textMessage = 'hello';
        $messageId = 555777;

        $apiPayload = [
            'ok' => true,
            'result' => [
                'message_id' => $messageId,
                'date' => time(),
                'text' => $textMessage,
            ],
        ];

        Http::fake([
            'https://api.telegram.org/bot*/sendMessage' => Http::response($apiPayload, 200),
        ]);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'token' => $this->botToken,
            'chat_id' => $this->botUser->chat_id,
            'text' => $textMessage,
        ]);

        $job = new SendTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $params,
            $typeMessage,
        );
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'telegram',
            'to_id' => $messageId,
        ]);
    }
}
