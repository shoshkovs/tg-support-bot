<?php

return [
    'settings' => [
        'telegram' => [
            'group_id' => env('TELEGRAM_GROUP_ID', ''),
            // use IPv4 only to connect to Telegram api
            'force_ipv4' => (bool) env('TELEGRAM_FORCE_IPV4', false),
            'template_topic_name' => env('TELEGRAM_TOPIC_NAME', ''),
            // backward compat (used when bots[] is empty)
            'token' => env('TELEGRAM_TOKEN', ''),
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            /**
             * Несколько ботов: у каждого свой token, secret (вебхук), своя группа.
             * Вебхук: POST /api/telegram/bots/{slug}/bot и POST /api/telegram/bot (slug=default).
             */
            'bots' => (static function (): array {
                $bots = [];
                // Поддержка обоих вариантов: TELEGRAM_BOT_TOKEN (новый) и TELEGRAM_TOKEN (старый)
                $tokenPrimary = env('TELEGRAM_BOT_TOKEN') ?? env('TELEGRAM_TOKEN');
                if (! empty($tokenPrimary)) {
                    $bots['default'] = [
                        'token' => $tokenPrimary,
                        'secret_key' => (string) (env('TELEGRAM_BOT_SECRET_KEY') ?? env('TELEGRAM_SECRET_KEY', '')),
                        'group_id' => (string) (env('TELEGRAM_BOT_GROUP_ID') ?? env('TELEGRAM_GROUP_ID', '')),
                    ];
                }
                $tokenSecond = env('TELEGRAM_BOT2_TOKEN');
                if (! empty($tokenSecond)) {
                    $slug = strtolower((string) env('TELEGRAM_BOT2_SLUG', 'second'));
                    $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);
                    if ($slug === '' || $slug === 'default') {
                        $slug = 'second';
                    }
                    $bots[$slug] = [
                        'token' => $tokenSecond,
                        'secret_key' => (string) env('TELEGRAM_BOT2_SECRET_KEY', ''),
                        'group_id' => (string) env('TELEGRAM_BOT2_GROUP_ID', ''),
                    ];
                }

                return $bots;
            })(),
        ],
        'telegram_ai' => [
            'username' => env('TELEGRAM_AI_BOT_USERNAME', ''),
            'token' => env('TELEGRAM_AI_BOT_TOKEN', ''),
        ],

        'vk' => [
            'token' => env('VK_TOKEN', ''),
            'secret_key' => env('VK_SECRET_CODE', ''),
            'confirm_code' => env('VK_CONFIRM_CODE', ''),
        ],

        'max' => [
            'token' => env('MAX_TOKEN', ''),
            'secret_key' => env('MAX_SECRET_KEY', ''),
        ],
    ],
];
