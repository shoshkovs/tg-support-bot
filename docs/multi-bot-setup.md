# Настройка нескольких Telegram ботов

## Описание

Система поддерживает работу с несколькими Telegram ботами одновременно. Каждый бот может иметь:
- Свой токен
- Свой секретный ключ для webhook
- Свою супергруппу (форум) для обработки сообщений

## Конфигурация в .env

### Первый бот (основной)

```env
TELEGRAM_TOKEN="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
TELEGRAM_SECRET_KEY="your_secret_key_1"
TELEGRAM_GROUP_ID="-1001234567890"
```

### Второй бот (дополнительный)

```env
TELEGRAM_BOT2_TOKEN="987654321:ZYXwvuTSRqponMLKjihGFEdcba"
TELEGRAM_BOT2_SECRET_KEY="your_secret_key_2"
TELEGRAM_BOT2_GROUP_ID="-1009876543210"
TELEGRAM_BOT2_SLUG="second"
```

**Важно:**
- `TELEGRAM_BOT2_GROUP_ID` - **обязательно** должен отличаться от `TELEGRAM_GROUP_ID`
- `TELEGRAM_BOT2_SLUG` - идентификатор бота (по умолчанию "second"), используется в URL webhook

## Настройка webhook

### Автоматическая настройка

Перейдите по URL:
```
https://your-domain.com/api/telegram/set_webhook
```

Система автоматически настроит webhook для всех сконфигурированных ботов.

### Ручная настройка

#### Первый бот (default)
```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/api/telegram/bot",
    "secret_token": "<TELEGRAM_SECRET_KEY>",
    "max_connections": 40,
    "drop_pending_updates": true
  }'
```

#### Второй бот
```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT2_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/api/telegram/bots/second/bot",
    "secret_token": "<TELEGRAM_BOT2_SECRET_KEY>",
    "max_connections": 40,
    "drop_pending_updates": true
  }'
```

## Создание супергрупп

### Для каждого бота нужна отдельная супергруппа:

1. Создайте новую группу в Telegram
2. Добавьте бота в группу как администратора
3. Преобразуйте группу в супергруппу (Settings → Group Type → Supergroup)
4. Включите темы (Topics/Forum): Settings → Topics → Enable
5. Получите ID группы:
   - Перешлите любое сообщение из группы боту @userinfobot
   - ID будет в формате `-100XXXXXXXXXX`
6. Добавьте ID в `.env` файл

## Как это работает

### Входящие сообщения (от пользователя к боту)

1. Пользователь пишет в **Бот 1**
2. Система определяет `telegram_bot_slug = "default"`
3. Создается/обновляется запись в `bot_users` с `telegram_bot_slug = "default"`
4. Сообщение отправляется в **Группу 1** (из `TELEGRAM_GROUP_ID`)
5. Создается топик в **Группе 1**

### Исходящие сообщения (от менеджера к пользователю)

1. Менеджер отвечает в топике **Группы 1**
2. Система определяет slug по `group_id` → находит `telegram_bot_slug = "default"`
3. Находит пользователя по `topic_id` и `telegram_bot_slug`
4. Отправляет ответ через **Бот 1** пользователю

### Аналогично для второго бота

1. Пользователь пишет в **Бот 2**
2. `telegram_bot_slug = "second"`
3. Сообщение → **Группа 2** (из `TELEGRAM_BOT2_GROUP_ID`)
4. Ответ менеджера из **Группы 2** → через **Бот 2** → пользователю

## Структура базы данных

Таблица `bot_users` содержит поле `telegram_bot_slug`:

```sql
CREATE TABLE bot_users (
    id SERIAL PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    platform VARCHAR(255) NOT NULL,
    telegram_bot_slug VARCHAR(64) DEFAULT 'default',
    topic_id INTEGER,
    -- ...
    UNIQUE (chat_id, platform, telegram_bot_slug)
);
```

## Проверка конфигурации

### 1. Проверьте, что боты настроены:

```bash
php artisan tinker
```

```php
\App\Modules\Telegram\Support\TelegramBotRegistry::bots();
// Должен вернуть массив с конфигурацией обоих ботов
```

### 2. Проверьте webhook:

```bash
# Для первого бота
curl "https://api.telegram.org/bot<TELEGRAM_TOKEN>/getWebhookInfo"

# Для второго бота
curl "https://api.telegram.org/bot<TELEGRAM_BOT2_TOKEN>/getWebhookInfo"
```

### 3. Проверьте группы:

- Отправьте тестовое сообщение в каждого бота
- Убедитесь, что сообщения попадают в разные группы
- Ответьте из каждой группы
- Убедитесь, что ответы приходят от правильных ботов

## Устранение неполадок

### Сообщения попадают в одну группу

**Проблема:** Оба бота отправляют сообщения в одну и ту же группу.

**Решение:**
1. Проверьте, что `TELEGRAM_BOT2_GROUP_ID` отличается от `TELEGRAM_GROUP_ID`
2. Перезапустите приложение: `php artisan config:clear && php artisan cache:clear`
3. Удалите старые записи из `bot_users` или обновите `telegram_bot_slug`

### Ответы не доходят до пользователей

**Проблема:** Менеджер отвечает в группе, но пользователь не получает сообщение.

**Решение:**
1. Проверьте, что в `bot_users` правильно установлен `telegram_bot_slug`
2. Проверьте логи: `tail -f storage/logs/laravel.log`
3. Убедитесь, что webhook настроен правильно

### Ошибка "Telegram bot slug is not configured"

**Проблема:** Система не может найти конфигурацию для указанного slug.

**Решение:**
1. Проверьте `.env` файл
2. Убедитесь, что `TELEGRAM_BOT2_TOKEN` и `TELEGRAM_BOT2_GROUP_ID` заполнены
3. Очистите кэш: `php artisan config:clear`

## Добавление третьего бота

Для добавления третьего и последующих ботов необходимо:

1. Расширить конфигурацию в `config/traffic_source.php`:

```php
'bots' => (static function (): array {
    $bots = [];
    
    // Первый бот
    if ($token = env('TELEGRAM_TOKEN')) {
        $bots['default'] = [
            'token' => $token,
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_GROUP_ID', ''),
        ];
    }
    
    // Второй бот
    if ($token = env('TELEGRAM_BOT2_TOKEN')) {
        $bots['second'] = [
            'token' => $token,
            'secret_key' => env('TELEGRAM_BOT2_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_BOT2_GROUP_ID', ''),
        ];
    }
    
    // Третий бот
    if ($token = env('TELEGRAM_BOT3_TOKEN')) {
        $bots['third'] = [
            'token' => $token,
            'secret_key' => env('TELEGRAM_BOT3_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_BOT3_GROUP_ID', ''),
        ];
    }
    
    return $bots;
})(),
```

2. Добавить в `.env`:

```env
TELEGRAM_BOT3_TOKEN="..."
TELEGRAM_BOT3_SECRET_KEY="..."
TELEGRAM_BOT3_GROUP_ID="-100..."
TELEGRAM_BOT3_SLUG="third"
```

3. Настроить webhook для третьего бота на URL:
```
https://your-domain.com/api/telegram/bots/third/bot
```

## Миграция с одного бота на несколько

Если у вас уже работает один бот и вы хотите добавить второй:

1. Добавьте конфигурацию второго бота в `.env`
2. Создайте новую супергруппу для второго бота
3. Настройте webhook для второго бота
4. Существующие пользователи останутся с `telegram_bot_slug = 'default'`
5. Новые пользователи второго бота получат `telegram_bot_slug = 'second'`

**Важно:** Не нужно мигрировать существующих пользователей, если они должны остаться на первом боте.