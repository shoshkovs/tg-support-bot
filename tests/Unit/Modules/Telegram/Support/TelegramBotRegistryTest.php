<?php

namespace Tests\Unit\Modules\Telegram\Support;

use App\Modules\Telegram\Support\TelegramBotRegistry;
use Tests\TestCase;

class TelegramBotRegistryTest extends TestCase
{
    public function test_find_slug_by_secret_key_matches_configured_bot(): void
    {
        config([
            'traffic_source.settings.telegram.bots' => [
                'default' => [
                    'token' => '111:AAA',
                    'secret_key' => 'secret-one',
                    'label' => 'First',
                ],
                'second' => [
                    'token' => '222:BBB',
                    'secret_key' => 'secret-two',
                    'label' => 'Second',
                ],
            ],
        ]);

        $this->assertSame('default', TelegramBotRegistry::findSlugBySecretKey('secret-one'));
        $this->assertSame('second', TelegramBotRegistry::findSlugBySecretKey('secret-two'));
        $this->assertNull(TelegramBotRegistry::findSlugBySecretKey('wrong'));
    }
}
