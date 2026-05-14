<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $botToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botToken = '123:ABC';
        config(['traffic_source.settings.telegram.token' => $this->botToken]);
    }

    /**
     * Keep legacy `group_id` and `bots.default.group_id` in sync so TelegramBotRegistry matches tests.
     */
    protected function setTelegramSupportGroupIdForTests(int|string $groupId): void
    {
        $gid = (string) $groupId;
        /** @var array<string, array<string, string>> $bots */
        $bots = config('traffic_source.settings.telegram.bots', []);
        $token = (string) config('traffic_source.settings.telegram.token', $this->botToken);
        $secret = (string) config('traffic_source.settings.telegram.secret_key', '');

        if (isset($bots['default'])) {
            $bots['default'] = array_merge($bots['default'], ['group_id' => $gid]);
        } else {
            $bots['default'] = [
                'token' => $token,
                'secret_key' => $secret,
                'group_id' => $gid,
            ];
        }

        config([
            'traffic_source.settings.telegram.group_id' => $gid,
            'traffic_source.settings.telegram.bots' => $bots,
        ]);
    }
}
