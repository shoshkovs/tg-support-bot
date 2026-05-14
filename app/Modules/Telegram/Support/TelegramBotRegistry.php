<?php

namespace App\Modules\Telegram\Support;

/**
 * Конфигурация нескольких Telegram-ботов (одна группа поддержки, разные токены/секреты).
 */
final class TelegramBotRegistry
{
    /**
     * Список ботов: slug => [token, secret_key, label].
     *
     * @return array<string, array{token: string, secret_key: string, label: string}>
     */
    public static function bots(): array
    {
        /** @var array<string, array{token: string, secret_key: string, label: string}> $bots */
        $bots = config('traffic_source.settings.telegram.bots', []);

        return $bots;
    }

    /**
     * Конфиг одного бота по slug (fallback на default).
     *
     * @return array{token: string, secret_key: string, label: string}
     */
    public static function forSlug(?string $slug): array
    {
        $slug = $slug === null || $slug === '' ? 'default' : $slug;
        $bots = self::bots();

        if (isset($bots[$slug])) {
            return $bots[$slug];
        }

        if (isset($bots['default'])) {
            return $bots['default'];
        }

        return [
            'token' => (string) config('traffic_source.settings.telegram.token', ''),
            'secret_key' => (string) config('traffic_source.settings.telegram.secret_key', ''),
            'label' => (string) config('traffic_source.settings.telegram.bot_label', 'Бот'),
        ];
    }

    public static function token(?string $slug): string
    {
        return self::forSlug($slug)['token'];
    }

    public static function secret(?string $slug): string
    {
        return self::forSlug($slug)['secret_key'];
    }

    public static function label(?string $slug): string
    {
        return self::forSlug($slug)['label'];
    }

    /**
     * Найти slug бота по секрету вебхука (X-Telegram-Bot-Api-Secret-Token).
     * Нужен для URL без сегмента slug: POST /api/telegram/bot — иначе всегда считался default.
     *
     * @return string|null slug или null, если секрет не совпал ни с одним ботом
     */
    public static function findSlugBySecretKey(string $secret): ?string
    {
        foreach (self::slugs() as $slug) {
            if (hash_equals(self::secret($slug), $secret)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Slugs для вебхуков: из `bots[]` или один `default`, если задан legacy `TELEGRAM_TOKEN`.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        $bots = self::bots();
        if ($bots !== []) {
            return array_keys($bots);
        }

        $legacyToken = (string) config('traffic_source.settings.telegram.token', '');

        return $legacyToken !== '' ? ['default'] : [];
    }
}
