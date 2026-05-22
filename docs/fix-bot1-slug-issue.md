# Исправление проблемы с Ботом 1 (отсутствие telegram_bot_slug)

## 🔍 Проблема

**Симптомы:**
- Бот 2 работает правильно (принимает и отправляет сообщения) ✅
- Бот 1 принимает сообщения, но не отправляет ответы ❌

**Причина:**
Старые записи в таблице `bot_users` не имеют значения `telegram_bot_slug`, из-за чего система не может определить, какой токен и группу использовать для отправки ответов.

## 🚀 Решение

### Вариант 1: Автоматическое обновление через миграцию (Рекомендуется)

На сервере выполните:

```bash
# 1. Перейдите в директорию проекта
cd ~/tg-support-bot

# 2. Запустите миграцию
docker compose exec app php artisan migrate

# 3. Проверьте результат
docker compose exec app php artisan tinker
```

В tinker выполните:
```php
\App\Models\BotUser::where('platform', 'telegram')->get(['id', 'chat_id', 'telegram_bot_slug']);
exit
```

Все записи должны иметь `telegram_bot_slug` = 'default' или 'second'.

### Вариант 2: Ручное обновление через SQL

Если миграция не сработала, выполните SQL напрямую:

```bash
# Подключитесь к базе данных
docker compose exec db mysql -u root -p

# Введите пароль из .env (DB_PASSWORD)
```

Затем выполните SQL:

```sql
-- Используйте вашу базу данных
USE your_database_name;

-- Обновите все записи без telegram_bot_slug
UPDATE bot_users 
SET telegram_bot_slug = 'default' 
WHERE platform = 'telegram' 
AND telegram_bot_slug IS NULL;

-- Проверьте результат
SELECT id, chat_id, platform, telegram_bot_slug, topic_id 
FROM bot_users 
WHERE platform = 'telegram';

-- Выйдите
EXIT;
```

### Вариант 3: Через Artisan Tinker (Быстрый способ)

```bash
docker compose exec app php artisan tinker
```

В tinker выполните:

```php
// Обновить все записи без slug
\App\Models\BotUser::where('platform', 'telegram')
    ->whereNull('telegram_bot_slug')
    ->update(['telegram_bot_slug' => 'default']);

// Проверить результат
\App\Models\BotUser::where('platform', 'telegram')
    ->get(['id', 'chat_id', 'telegram_bot_slug', 'topic_id']);

exit
```

## 🔄 После обновления

1. **Перезапустите очередь:**
```bash
docker compose restart queue
```

2. **Проверьте логи:**
```bash
docker compose logs -f queue
```

3. **Протестируйте оба бота:**
   - Отправьте сообщение боту 1 → должно прийти в группу 1
   - Ответьте из группы 1 → ответ должен прийти пользователю
   - Отправьте сообщение боту 2 → должно прийти в группу 2
   - Ответьте из группы 2 → ответ должен прийти пользователю

## 📊 Проверка текущего состояния

### Проверить записи в базе данных:

```bash
docker compose exec app php artisan tinker
```

```php
// Показать все записи telegram пользователей
\App\Models\BotUser::where('platform', 'telegram')
    ->get(['id', 'chat_id', 'telegram_bot_slug', 'topic_id'])
    ->toArray();

// Подсчитать записи без slug
\App\Models\BotUser::where('platform', 'telegram')
    ->whereNull('telegram_bot_slug')
    ->count();

// Подсчитать записи с slug = 'default'
\App\Models\BotUser::where('telegram_bot_slug', 'default')->count();

// Подсчитать записи с slug = 'second'
\App\Models\BotUser::where('telegram_bot_slug', 'second')->count();

exit
```

## 🎯 Ожидаемый результат

После выполнения любого из вариантов:

```
bot_users таблица:
+----+----------+----------+--------------------+----------+
| id | chat_id  | platform | telegram_bot_slug  | topic_id |
+----+----------+----------+--------------------+----------+
| 1  | 12345    | telegram | default            | 123      |
| 2  | 67890    | telegram | second             | 456      |
| 3  | 11111    | telegram | default            | 789      |
+----+----------+----------+--------------------+----------+
```

Все записи с `platform = 'telegram'` должны иметь `telegram_bot_slug`:
- `'default'` - для бота 1
- `'second'` - для бота 2

## ⚠️ Важно

1. **Не удаляйте старые записи** - они содержат историю переписки
2. **Сделайте бэкап базы данных** перед обновлением:
   ```bash
   docker compose exec db mysqldump -u root -p your_database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```
3. **После обновления обязательно перезапустите queue**

## 🐛 Если проблема осталась

1. Проверьте логи:
   ```bash
   docker compose exec app tail -f storage/logs/laravel.log
   ```

2. Проверьте, что в .env правильно настроены оба бота:
   ```env
   TELEGRAM_BOT_TOKEN="токен_бота_1"
   TELEGRAM_BOT_GROUP_ID="-100xxxxxxxxxx"
   
   TELEGRAM_BOT2_TOKEN="токен_бота_2"
   TELEGRAM_BOT2_GROUP_ID="-100yyyyyyyyyy"
   TELEGRAM_BOT2_SLUG="second"
   ```

3. Убедитесь, что оба бота имеют права администратора в своих группах

4. Проверьте webhook для обоих ботов:
   ```bash
   curl https://your-domain.com/api/telegram/set_webhook
   ```

## 📝 Дополнительная информация

Эта проблема возникла потому, что поле `telegram_bot_slug` было добавлено позже, и старые записи в базе данных не имели этого значения. Миграция автоматически заполняет это поле значением 'default' для всех существующих записей.

Для новых пользователей slug будет устанавливаться автоматически при создании записи в зависимости от того, через какой webhook пришло сообщение.