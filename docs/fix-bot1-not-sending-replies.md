# Исправление проблемы: Бот 1 не отправляет ответы

## 🔍 Проблема

**Симптомы:**
- Бот 2 работает правильно (принимает и отправляет) ✅
- Бот 1 принимает сообщения, но НЕ отправляет ответы из группы ❌
- Название топика неправильное: `#1059138125 (telegram)` вместо форматированного

## 🎯 Найденные проблемы

### 1. Несоответствие имен переменных окружения

**Проблема:** В конфиге используются старые имена переменных (`TELEGRAM_TOKEN`), а в документации указаны новые (`TELEGRAM_BOT_TOKEN`).

**Решение:** Обновлен конфиг для поддержки обоих вариантов.

### 2. Неправильный формат TELEGRAM_TOPIC_NAME

**Проблема:** Используется `{telegram_bot_label}`, которого нет в коде.

**Доступные параметры:**
- `{id}` - ID пользователя
- `{first_name}` - Имя
- `{last_name}` - Фамилия
- `{username}` - Username
- `{email}` - Email (если есть)
- `{platform}` - Платформа (telegram, vk, max)

## 🚀 Решение

### Шаг 1: Обновите код на сервере

```bash
cd ~/tg-support-bot
git pull origin main
```

### Шаг 2: Проверьте .env файл

Убедитесь, что используются правильные имена переменных:

```env
# Бот 1 (default) - используйте ОДИН из вариантов:

# Вариант 1 (новый, рекомендуется):
TELEGRAM_BOT_TOKEN="ваш_токен_бота_1"
TELEGRAM_BOT_SECRET_KEY="секрет_1"
TELEGRAM_BOT_GROUP_ID="-100xxxxxxxxxx"

# Вариант 2 (старый, для обратной совместимости):
TELEGRAM_TOKEN="ваш_токен_бота_1"
TELEGRAM_SECRET_KEY="секрет_1"
TELEGRAM_GROUP_ID="-100xxxxxxxxxx"

# Бот 2:
TELEGRAM_BOT2_TOKEN="ваш_токен_бота_2"
TELEGRAM_BOT2_SECRET_KEY="секрет_2"
TELEGRAM_BOT2_GROUP_ID="-100yyyyyyyyyy"
TELEGRAM_BOT2_SLUG="second"

# Формат названия топика (исправьте на правильный):
TELEGRAM_TOPIC_NAME="✅ {platform} · {username} | {first_name}"
```

### Шаг 3: Очистите кэш конфигурации

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```

### Шаг 4: Перезапустите приложение

```bash
docker compose restart app queue
```

### Шаг 5: Проверьте конфигурацию

```bash
docker compose exec app php artisan tinker
```

В tinker:
```php
// Проверьте, что оба бота настроены
\App\Modules\Telegram\Support\TelegramBotRegistry::bots();

// Должно вернуть:
// [
//   "default" => [
//     "token" => "...",
//     "secret_key" => "...",
//     "group_id" => "-100xxxxxxxxxx"
//   ],
//   "second" => [
//     "token" => "...",
//     "secret_key" => "...",
//     "group_id" => "-100yyyyyyyyyy"
//   ]
// ]

// Проверьте slug для группы 1
\App\Modules\Telegram\Support\TelegramBotRegistry::findSlugByGroupId('-100xxxxxxxxxx');
// Должно вернуть: "default"

// Проверьте slug для группы 2
\App\Modules\Telegram\Support\TelegramBotRegistry::findSlugByGroupId('-100yyyyyyyyyy');
// Должно вернуть: "second"

exit
```

### Шаг 6: Проверьте записи в базе данных

```bash
docker compose exec app php artisan tinker
```

```php
// Проверьте пользователей
\App\Models\BotUser::where('platform', 'telegram')
    ->get(['id', 'chat_id', 'telegram_bot_slug', 'topic_id'])
    ->toArray();

// Если у записей нет telegram_bot_slug, обновите их:
\App\Models\BotUser::where('platform', 'telegram')
    ->whereNull('telegram_bot_slug')
    ->update(['telegram_bot_slug' => 'default']);

exit
```

## 🧪 Тестирование

### Тест 1: Бот 1

1. Отправьте сообщение боту 1
2. Проверьте, что сообщение появилось в группе 1
3. Ответьте из группы 1
4. Проверьте, что ответ пришел пользователю

### Тест 2: Бот 2

1. Отправьте сообщение боту 2
2. Проверьте, что сообщение появилось в группе 2
3. Ответьте из группы 2
4. Проверьте, что ответ пришел пользователю

### Тест 3: Название топика

Новый топик должен создаваться с правильным названием согласно `TELEGRAM_TOPIC_NAME`.

Например, если `TELEGRAM_TOPIC_NAME="✅ {platform} · {username} | {first_name}"`:
- Результат: `✅ telegram · @username | Иван`

## 🔍 Диагностика

### Проверка логов

```bash
# Логи приложения
docker compose exec app tail -f storage/logs/laravel.log

# Логи очереди
docker compose logs -f queue

# Поиск ошибок
docker compose exec app grep "ERROR" storage/logs/laravel.log | tail -20
```

### Проверка webhook

```bash
# Проверьте webhook для бота 1
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo" | jq

# Проверьте webhook для бота 2
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT2_TOKEN}/getWebhookInfo" | jq
```

### Проверка конфигурации в runtime

```bash
docker compose exec app php artisan tinker
```

```php
// Проверьте, какие переменные используются
env('TELEGRAM_BOT_TOKEN');
env('TELEGRAM_TOKEN');
env('TELEGRAM_BOT_GROUP_ID');
env('TELEGRAM_GROUP_ID');

// Проверьте конфиг
config('traffic_source.settings.telegram.bots');

exit
```

## ⚠️ Частые ошибки

### Ошибка 1: "Telegram bot slug is not configured"

**Причина:** В .env не указаны переменные для бота или они пустые.

**Решение:**
```bash
# Проверьте .env
cat .env | grep TELEGRAM

# Убедитесь, что все переменные заполнены
```

### Ошибка 2: Ответы не приходят

**Причина:** `telegram_bot_slug` в базе данных NULL или неправильный.

**Решение:**
```bash
docker compose exec app php artisan tinker
```

```php
// Обновите все записи
\App\Models\BotUser::where('platform', 'telegram')
    ->whereNull('telegram_bot_slug')
    ->update(['telegram_bot_slug' => 'default']);

exit
```

### Ошибка 3: Неправильное название топика

**Причина:** Неправильный формат в `TELEGRAM_TOPIC_NAME`.

**Решение:**
```env
# Используйте только доступные параметры:
TELEGRAM_TOPIC_NAME="{first_name} {last_name} ({platform})"
# или
TELEGRAM_TOPIC_NAME="✅ {username} | {first_name}"
# или
TELEGRAM_TOPIC_NAME="#{id} · {first_name} {last_name}"
```

## 📝 Изменения в коде

### config/traffic_source.php

Добавлена поддержка обоих вариантов имен переменных:
- Новые: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_SECRET_KEY`, `TELEGRAM_BOT_GROUP_ID`
- Старые: `TELEGRAM_TOKEN`, `TELEGRAM_SECRET_KEY`, `TELEGRAM_GROUP_ID`

Теперь можно использовать любой вариант, система автоматически выберет правильный.

## ✅ Проверка успешного исправления

После выполнения всех шагов:

1. ✅ Оба бота принимают сообщения
2. ✅ Оба бота отправляют ответы
3. ✅ Сообщения не путаются между ботами
4. ✅ Топики создаются с правильными названиями
5. ✅ Каждый бот работает со своей группой

## 🆘 Если проблема осталась

1. Проверьте логи на наличие ошибок
2. Убедитесь, что оба бота имеют права администратора в своих группах
3. Проверьте, что webhook настроены правильно
4. Убедитесь, что в базе данных у всех записей есть `telegram_bot_slug`
5. Проверьте, что переменные окружения загружены правильно

Если ничего не помогло, создайте issue на GitHub с логами ошибок.