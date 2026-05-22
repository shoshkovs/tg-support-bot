<?php

namespace Tests\Unit\Modules\Telegram\Support;

use App\Modules\Telegram\Support\TelegramBotRegistry;
use Tests\TestCase;

class TelegramBotRegistryTest extends TestCase
{
    public function test_group_id_returns_numeric_string_as_int(): void
    {
        config([
            'traffic_source.settings.telegram.bots' => [
                'default' => [
                    'token' => '111:AAA',
                    'secret_key' => 'secret-one',
                    'group_id' => '-100123',
                ],
            ],
        ]);

        $this->assertSame(-100123, TelegramBotRegistry::groupId('default'));
    }

    public function test_find_slug_by_group_id_matches_configured_bots(): void
    {
        config([
            'traffic_source.settings.telegram.bots' => [
                'default' => [
                    'token' => '111:AAA',
                    'secret_key' => 'secret-one',
                    'group_id' => '-1001',
                ],
                'shop' => [
                    'token' => '222:BBB',
                    'secret_key' => 'secret-two',
                    'group_id' => '-1002',
                ],
            ],
        ]);

        $this->assertSame('default', TelegramBotRegistry::findSlugByGroupId('-1001'));
        $this->assertSame('shop', TelegramBotRegistry::findSlugByGroupId('-1002'));
        $this->assertNull(TelegramBotRegistry::findSlugByGroupId('-999'));
    }

    public function test_unknown_slug_throws_when_bots_array_is_non_empty(): void
    {
        config([
            'traffic_source.settings.telegram.bots' => [
                'default' => [
                    'token' => '111:AAA',
                    'secret_key' => 's1',
                    'group_id' => '-1001',
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        TelegramBotRegistry::groupId('second');
    }

    public function test_for_slug_falls_back_to_legacy_top_level_when_bots_empty(): void
    {
        config([
            'traffic_source.settings.telegram.bots' => [],
            'traffic_source.settings.telegram.token' => 'legacy:tok',
            'traffic_source.settings.telegram.secret_key' => 'legacysec',
            'traffic_source.settings.telegram.group_id' => '-500',
        ]);

        $this->assertSame('legacy:tok', TelegramBotRegistry::token('default'));
        $this->assertSame('legacysec', TelegramBotRegistry::secret('default'));
        $this->assertSame(-500, TelegramBotRegistry::groupId('default'));
    }
}
