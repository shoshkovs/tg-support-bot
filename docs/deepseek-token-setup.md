# Настройка DeepSeek токена для AI Service

## Получение токена авторизации DeepSeek

### Метод 1: Из локального хранилища (рекомендуется)

1. Посетите https://chat.deepseek.com
2. Войдите в свой аккаунт
3. Откройте инструменты разработчика браузера (F12 или правый клик > Inspect)
4. Перейдите на вкладку **Application** (если не видна, нажмите >> для других вкладок)
5. В левой панели разверните **Local Storage**
6. Перейдите к `https://chat.deepseek.com`
7. Найдите ключ `userToken`
8. Скопируйте значение поля `"value"` - это ваш токен

**Быстрый способ через консоль:**
```javascript
JSON.parse(localStorage.getItem("userToken")).value
```

### Метод 2: Через вкладку Network

1. Посетите https://chat.deepseek.com
2. Войдите в свой аккаунт
3. Откройте инструменты разработчика (F12)
4. Перейдите на вкладку **Network**
5. Отправьте любой запрос в чате
6. Найдите заголовки запроса
7. Скопируйте `authorization` токен (без префикса 'Bearer')

## Настройка токена на сервере

### Шаг 1: Добавьте токен в .env

```bash
cd ~/tg-support-bot

# Откройте .env файл
nano .env

# Добавьте строку (замените YOUR_TOKEN на ваш токен):
DEEPSEEK_AUTH_TOKEN=YOUR_TOKEN_HERE

# Сохраните: Ctrl+O, Enter, Ctrl+X
```

### Шаг 2: Пересоберите и перезапустите AI Service

```bash
# Остановите контейнер
docker compose stop ai-service

# Пересоберите с новыми зависимостями
docker compose build --no-cache ai-service

# Запустите
docker compose up -d ai-service

# Проверьте логи
docker compose logs -f ai-service
```

Вы должны увидеть:
```
INFO - DeepSeek Service инициализирован с реальным API
```

Вместо:
```
INFO - DEEPSEEK_AUTH_TOKEN не установлен. Используем mock-режим.
```

### Шаг 3: Проверьте работу

```bash
# Тестовый запрос
docker compose exec app curl -X POST http://ai-service:8000/api/v1/chat/simple \
  -H "Content-Type: application/json" \
  -d '{"prompt":"Привет! Как дела?"}'
```

Если токен работает, вы получите реальный ответ от DeepSeek, а не mock-сообщение.

## Решение проблем с Cloudflare

Если вы столкнетесь с проблемами Cloudflare (страница "Подождите немного..."), выполните:

```bash
# Зайдите в контейнер
docker compose exec ai-service bash

# Запустите bypass
python -m dsk.bypass

# Это откроет браузер для решения Cloudflare challenge
# Cookie будет автоматически сохранен
```

Cookie сохраняется в `dsk/cookies.json` и автоматически используется в запросах.

Запускать bypass нужно только если:
- Появляются запросы Cloudflare
- Истек срок действия cookie
- Видите ошибку "Please wait a few minutes before trying again"

## Проверка статуса

### Проверка через логи:

```bash
docker compose logs ai-service | grep -i "deepseek"
```

Должно быть:
```
DeepSeek Service инициализирован с реальным API
```

### Проверка через API:

```bash
# Полный запрос с историей
docker compose exec app curl -X POST http://ai-service:8000/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "system", "content": "Ты помощник службы поддержки"},
      {"role": "user", "content": "Как оформить возврат?"}
    ],
    "temperature": 0.7,
    "max_tokens": 500
  }'
```

## Интеграция с Laravel

После настройки токена, AI Service автоматически будет использовать реальный DeepSeek API.

### Включите AI в боте:

```bash
# В .env добавьте:
AI_ENABLED=true
AI_AUTO_REPLY=false  # или true для автоответов
AI_DEFAULT_PROVIDER=aiservice
AI_SERVICE_URL=http://ai-service:8000
```

### Перезапустите Laravel:

```bash
docker compose restart app queue
```

### Проверьте интеграцию:

```bash
docker compose exec app php artisan tinker
```

В tinker:
```php
$provider = new \App\Services\Ai\AiServiceProvider();

// Проверка подключения
$status = $provider->testConnection();
print_r($status);

// Тестовый запрос
$request = new \App\DTOs\Ai\AiRequestDto(
    message: 'Привет! Как оформить возврат?',
    userId: 1,
    platform: 'telegram'
);

$response = $provider->generateResponse($request);
echo "Ответ: " . $response->response . "\n";
echo "Модель: " . $response->modelUsed . "\n";
echo "Токены: " . $response->tokensUsed . "\n";
```

## Безопасность токена

⚠️ **Важно:**

1. **Не коммитьте токен в Git** - он уже в `.gitignore`
2. **Храните токен в .env** - не в коде
3. **Регулярно обновляйте токен** - если подозреваете компрометацию
4. **Не делитесь токеном** - это ваш личный ключ доступа

## Ограничения и рекомендации

### Ограничения DeepSeek4Free:

- Использует неофициальный доступ через веб-интерфейс
- Может быть нестабильным
- Требует периодического обновления cookie
- Может нарушать Terms of Service DeepSeek

### Рекомендации:

1. **Для продакшена** используйте официальный DeepSeek API:
   - Зарегистрируйтесь на https://platform.deepseek.com/
   - Получите официальный API ключ
   - Используйте [`DeepSeekProvider.php`](../app/Services/Ai/DeepSeekProvider.php)

2. **Для тестирования** можно использовать DeepSeek4Free

3. **Мониторьте использование** - следите за логами и ошибками

## Troubleshooting

### Ошибка: "Authentication failed"

```bash
# Проверьте токен
docker compose exec ai-service env | grep DEEPSEEK_AUTH_TOKEN

# Если пустой - добавьте в .env и перезапустите
docker compose restart ai-service
```

### Ошибка: "Cloudflare protection"

```bash
# Запустите bypass
docker compose exec ai-service python -m dsk.bypass
```

### Ошибка: "Rate limit exceeded"

Подождите несколько минут перед следующим запросом.

### Mock-режим не отключается

```bash
# Проверьте логи
docker compose logs ai-service | tail -20

# Пересоберите контейнер
docker compose build --no-cache ai-service
docker compose up -d ai-service
```

## Дополнительные ресурсы

- [DeepSeek4Free GitHub](https://github.com/xtekky/deepseek4free)
- [DeepSeek Official](https://www.deepseek.com/)
- [AI Service Setup](./ai-service-setup.md)
- [AI Service Deployment](./ai-service-deployment.md)