# Решение проблем с правами бота и webhook

## 🚨 Текущие проблемы

### Проблема 1: "Bad Request: not enough rights to create a topic"

**Симптомы:**
```
TopicCreateJob: unknown error {"response":{"ok":false,"error_code":400,"description":"Bad Request: not enough rights to create a topic"}}
```

**Причина:** Бот не имеет прав администратора в Telegram группе для создания топиков (тем).

**Решение:**

1. **Откройте группу поддержки в Telegram**

2. **Проверьте права бота:**
   - Нажмите на название группы → "Администраторы"
   - Найдите вашего бота в списке администраторов
   - Если бота нет - добавьте его как администратора

3. **Настройте права администратора для бота:**
   
   Бот ДОЛЖЕН иметь следующие права:
   - ✅ **Управление темами** (Manage Topics) - ОБЯЗАТЕЛЬНО!
   - ✅ Удаление сообщений (Delete Messages)
   - ✅ Закрепление сообщений (Pin Messages)
   - ✅ Отправка сообщений (Send Messages)
   - ✅ Отправка медиа (Send Media)
   - ✅ Добавление пользователей (Add Users)

4. **Для КАЖДОГО бота повторите эти действия в СООТВЕТСТВУЮЩЕЙ группе:**
   - Бот 1 → Группа 1
   - Бот 2 → Группа 2

5. **Проверьте, что группа настроена как Форум:**
   - Настройки группы → Темы → Включить темы
   - Без этого создание топиков невозможно!

### Проблема 2: Бот 2 не принимает сообщения

**Симптомы:**
- Бот 1 работает
- Бот 2 не реагирует на сообщения

**Причина:** Webhook для второго бота не настроен или настроен неправильно.

**Решение:**

1. **Проверьте текущие webhook:**

```bash
# Для бота 1 (default)
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo" | jq

# Для бота 2
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT2_TOKEN}/getWebhookInfo" | jq
```

2. **Правильные URL для webhook:**

```bash
# Бот 1 (default):
https://your-domain.com/api/telegram/bot

# Бот 2 (с slug "second"):
https://your-domain.com/api/telegram/bots/second/bot
```

3. **Установите webhook для обоих ботов:**

```bash
# Через API приложения (рекомендуется)
curl https://your-domain.com/api/telegram/set_webhook

# Или вручную для каждого бота:

# Бот 1
curl -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/api/telegram/bot",
    "secret_token": "'"${TELEGRAM_BOT_SECRET_KEY}"'"
  }'

# Бот 2
curl -X POST "https://api.telegram.org/bot${TELEGRAM_BOT2_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/api/telegram/bots/second/bot",
    "secret_token": "'"${TELEGRAM_BOT2_SECRET_KEY}"'"
  }'
```

4. **Проверьте .env файл:**

```env
# Бот 1 (default)
TELEGRAM_BOT_TOKEN="ваш_токен_бота_1"
TELEGRAM_BOT_SECRET_KEY="секретный_ключ_1"
TELEGRAM_BOT_GROUP_ID="-100xxxxxxxxxx"  # ID группы 1

# Бот 2
TELEGRAM_BOT2_TOKEN="ваш_токен_бота_2"
TELEGRAM_BOT2_SECRET_KEY="секретный_ключ_2"
TELEGRAM_BOT2_GROUP_ID="-100yyyyyyyyyy"  # ID группы 2
TELEGRAM_BOT2_SLUG="second"
```

5. **Перезапустите приложение:**

```bash
docker compose restart app
docker compose restart queue
```

## 📋 Чек-лист проверки

### Для каждого бота проверьте:

- [ ] Бот добавлен в группу как администратор
- [ ] У бота есть право "Управление темами" (Manage Topics)
- [ ] Группа настроена как Форум (Темы включены)
- [ ] Webhook настроен с правильным URL
- [ ] В .env указаны все параметры бота
- [ ] Приложение перезапущено после изменений

### Проверка работы:

1. **Отправьте сообщение боту 1** → должно появиться в группе 1
2. **Ответьте из группы 1** → ответ должен прийти пользователю
3. **Отправьте сообщение боту 2** → должно появиться в группе 2
4. **Ответьте из группы 2** → ответ должен прийти пользователю

## 🔍 Диагностика проблем

### Проверка логов:

```bash
# Логи приложения
docker compose exec app tail -f storage/logs/laravel.log

# Логи очереди
docker compose logs -f queue

# Поиск ошибок TopicCreateJob
docker compose exec app grep "TopicCreateJob" storage/logs/laravel.log | tail -20
```

### Проверка webhook:

```bash
# Статус webhook бота 1
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo" | jq '.result'

# Статус webhook бота 2
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT2_TOKEN}/getWebhookInfo" | jq '.result'
```

Ожидаемый результат:
```json
{
  "url": "https://your-domain.com/api/telegram/bot",
  "has_custom_certificate": false,
  "pending_update_count": 0,
  "max_connections": 40,
  "ip_address": "xxx.xxx.xxx.xxx"
}
```

### Проверка прав бота в группе:

```bash
# Получить информацию о боте в группе
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getChatMember?chat_id=${TELEGRAM_BOT_GROUP_ID}&user_id=BOT_USER_ID" | jq
```

Бот должен иметь статус "administrator" и `can_manage_topics: true`

## 🚀 Быстрое решение

Если нужно быстро исправить все проблемы:

```bash
# 1. Остановите очередь
docker compose stop queue

# 2. Очистите failed jobs
docker compose exec app php artisan queue:flush

# 3. Настройте права ботов в Telegram группах (вручную через интерфейс)

# 4. Установите webhook
curl https://your-domain.com/api/telegram/set_webhook

# 5. Перезапустите все
docker compose restart app queue

# 6. Проверьте статус
docker compose ps
docker compose logs -f queue
```

## ⚠️ Важные замечания

1. **Группа ДОЛЖНА быть настроена как Форум** (Темы включены)
2. **Бот ДОЛЖЕН быть администратором** с правом "Управление темами"
3. **Каждый бот должен иметь свой уникальный webhook URL**
4. **После изменения .env всегда перезапускайте приложение**
5. **Проверяйте логи после каждого изменения**

## 📞 Если проблемы остались

1. Проверьте, что группа - это супергруппа (supergroup), а не обычная группа
2. Убедитесь, что ID группы начинается с `-100`
3. Проверьте, что токены ботов правильные и активные
4. Убедитесь, что боты не заблокированы в группах
5. Проверьте, что SSL сертификат домена валидный (Telegram требует HTTPS)