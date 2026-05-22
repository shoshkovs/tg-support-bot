<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\External\ExternalMessageAnswerDtoMock;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    private int $externalId;

    public function setUp(): void
    {
        parent::setUp();

        $this->externalId = time();
    }

    public function testWebhookService(): void
    {
        $url = 'https://node.tg-support-bot.ru/push-message';

        $saveMessageData = ExternalMessageAnswerDtoMock::getDto();

        $dataMessage = [
            'type_query' => 'send_message',
            'externalId' => $this->externalId,
            'message' => $saveMessageData->result->toArray(),
        ];

        Http::fake([
            'https://node.tg-support-bot.ru/*' => Http::response('{"ok":true}', 200),
        ]);

        $result = (new WebhookService())->sendMessage($url, $dataMessage);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('ok', $result);
    }
}
