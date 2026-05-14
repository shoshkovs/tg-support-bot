<?php

namespace App\Modules\Telegram\Support;

/**
 * Несколько Telegram-ботов: у каждого slug свои token, secret_key и своя группа поддержки (group_id).
 */
final class TelegramBotRegistry
{
    /**
     * @return array<string, array{token: string, secret_key: string, group_id: string}>
     */
    public static function bots(): array
    {
        /** @var array<string, array{token: string, secret_key: string, group_id: string}> $bots */
        $bots = config('traffic_source.settings.telegram.bots', []);

        return $bots;
    }

    /**
     * @return array{token: string, secret_key: string, group_id: string}
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
            'group_id' => (string) config('traffic_source.settings.telegram.group_id', ''),
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

    /**
     * ID супергруппы (форум), куда этот бот пишет треды поддержки.
     *
     * @return int|string
     */
    public static function groupId(?string $slug): int|string
    {
        $g = self::forSlug($slug)['group_id'];

        return is_numeric($g) ? (int) $g : $g;
    }

    /**
     * Slug бота по ID группы (для апдейтов из супергруппы: message.chat.id).
     */
    public static function findSlugByGroupId(string $groupId): ?string
    {
        if ($groupId === '') {
            return null;
        }

        foreach (self::slugs() as $slug) {
            if ((string) self::groupId($slug) === $groupId) {
                return $slug;
            }
        }

        return null;
    }

    /**
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
