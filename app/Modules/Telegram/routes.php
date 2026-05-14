<?php

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\Controllers\AiTelegramBotController;
use App\Modules\Telegram\Controllers\TelegramBotController;
use App\Modules\Telegram\Middleware\TelegramQuery;
use App\Modules\Telegram\Support\TelegramBotRegistry;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'telegram',
], function () {
    Route::post('ai/bot', [AiTelegramBotController::class, 'bot_query'])->middleware(TelegramQuery::class);

    Route::post('bots/{telegram_bot_slug}/bot', [TelegramBotController::class, 'bot_query'])
        ->middleware(TelegramQuery::class)
        ->where('telegram_bot_slug', '[a-z0-9_-]+');

    Route::post('bot', [TelegramBotController::class, 'bot_query'])->middleware(TelegramQuery::class);

    Route::get('set_webhook', function () {
        $results = [];
        foreach (TelegramBotRegistry::slugs() as $slug) {
            $cfg = TelegramBotRegistry::forSlug($slug);
            if ($cfg['token'] === '') {
                continue;
            }
            $path = $slug === 'default'
                ? '/api/telegram/bot'
                : '/api/telegram/bots/' . $slug . '/bot';
            $queryParams = [
                'url' => config('app.url') . $path,
                'max_connections' => 40,
                'drop_pending_updates' => true,
                'secret_token' => $cfg['secret_key'],
            ];
            $results[$slug] = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams, $cfg['token'])->rawData;
        }

        return response()->json($results);
    });
});
