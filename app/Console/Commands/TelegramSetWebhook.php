<?php

namespace App\Console\Commands;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\Support\TelegramBotRegistry;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Set Telegram webhook URL(s) for all configured bots';

    /**
     * @return int
     */
    public function handle(): int
    {
        foreach (TelegramBotRegistry::slugs() as $slug) {
            $cfg = TelegramBotRegistry::forSlug($slug);
            if ($cfg['token'] === '') {
                $this->warn("Skip slug \"{$slug}\": empty token.");

                continue;
            }

            $path = $slug === 'default'
                ? '/api/telegram/bot'
                : '/api/telegram/bots/' . $slug . '/bot';
            $url = config('app.url') . $path;

            $queryParams = [
                'url' => $url,
                'max_connections' => 40,
                'drop_pending_updates' => true,
                'secret_token' => $cfg['secret_key'],
            ];

            $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams, $cfg['token']);

            $this->info("Webhook slug \"{$slug}\" -> {$url}");
            if (isset($result->rawData)) {
                $this->line(json_encode($result->rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('No response data');
            }
        }

        return Command::SUCCESS;
    }
}
