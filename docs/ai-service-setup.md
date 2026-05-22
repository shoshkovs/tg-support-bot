# AI Service - Настройка и использование

## Описание

AI Service - это Python микросервис на базе FastAPI, который предоставляет REST API для генерации ответов через DeepSeek4Free. Микросервис интегрирован с основным Laravel приложением и может использоваться для автоматических ответов в чате поддержки.

## Архитектура

```
┌─────────────────┐      HTTP API      ┌──────────────────┐
│  Laravel App    │ ◄─────────────────► │   AI Service     │
│  (PHP)          │   localhost:8000    │   (Python)       │
└─────────────────┘                     └──────────────────┘
        │                                        │
        │                                        │
        ▼                                        ▼
┌─────────────────┐                     ┌──────────────────┐
│   PostgreSQL    │                     │  DeepSeek4Free   │
│   Redis         │                     │  (AI Provider)   │
└─────────────────┘                     └──────────────────┘
```

## Компоненты

### Python микросервис (ai-service/)
- **FastAPI** - веб-фреймворк для REST API
- **DeepSeek4Free** - библиотека для работы с DeepSeek AI
- **Uvicorn** - ASGI сервер
- **Pydantic** - валидация данных

### PHP интеграция (app/Services/Ai/)
- **AiServiceProvider** - провайдер для работы с AI Service
- **AiAssistantService** - основной сервис для обработки AI запросов
- **BaseAiProvider** - базовый класс для всех AI провайдеров

## Установка и запуск

### 1. Настройка переменных окружения

Добавьте в `.env` файл:

```bash
# AI Service Configuration
AI_ENABLED=true
AI_AUTO_REPLY=false
AI_DEFAULT_PROVIDER=aiservice

# AI Service URL (для Docker)
AI_SERVICE_URL=http://ai-service:8000
AI_SERVICE_MODEL=deepseek-chat
AI_SERVICE_MAX_TOKENS=2000
AI_SERVICE_TEMPERATURE=0.7

# AI Settings
AI_CONFIDENCE_THRESHOLD=0.8
AI_MAX_CONTEXT_MESSAGES=10
AI_AUTO_ESCALATION=true
AI_ENABLE_LOGGING=true
AI_RATE_LIMIT_PER_MINUTE=60
```

### 2. Запуск через Docker Compose

AI Service уже добавлен в `docker-compose.yml`:

```bash
# Сборка и запуск всех сервисов
docker compose up -d --build

# Проверка статуса AI Service
docker compose ps ai-service

# Просмотр логов
docker compose logs -f ai-service
```

### 3. Проверка работоспособности

```bash
# Health check
curl http://localhost:8000/health

# Тестовый запрос
curl -X POST http://localhost:8000/api/v1/chat/simple \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Привет!"}'
```

## API Endpoints

### GET /health
Проверка здоровья сервиса

**Ответ:**
```json
{
  "status": "healthy",
  "service": "AI Service",
  "version": "1.0.0"
}
```

### POST /api/v1/chat/completions
Генерация ответа (OpenAI-совместимый формат)

**Запрос:**
```json
{
  "messages": [
    {"role": "system", "content": "Ты помощник службы поддержки"},
    {"role": "user", "content": "Как оформить возврат?"}
  ],
  "temperature": 0.7,
  "max_tokens": 2000
}
```

**Ответ:**
```json
{
  "content": "Для оформления возврата...",
  "model": "deepseek-chat",
  "usage": {
    "prompt_tokens": 25,
    "completion_tokens": 150,
    "total_tokens": 175
  }
}
```

### POST /api/v1/chat/simple
Упрощенный endpoint для быстрых запросов

**Запрос:**
```json
{
  "prompt": "Как оформить возврат?"
}
```

**Ответ:**
```json
{
  "response": "Для оформления возврата..."
}
```

## Использование в Laravel

### Через AiAssistantService

```php
use App\Services\Ai\AiAssistantService;
use App\DTOs\Ai\AiRequestDto;

$aiService = new AiAssistantService();

$request = new AiRequestDto(
    message: 'Как оформить возврат?',
    userId: 123,
    platform: 'telegram',
    provider: 'aiservice' // Использовать AI Service
);

$response = $aiService->processMessage($request);

if ($response) {
    echo $response->response; // Ответ от AI
    echo $response->confidenceScore; // Уверенность (0-1)
    echo $response->shouldEscalate; // Нужна ли эскалация
}
```

### Прямой вызов AiServiceProvider

```php
use App\Services\Ai\AiServiceProvider;
use App\DTOs\Ai\AiRequestDto;

$provider = new AiServiceProvider();

// Проверка доступности
if ($provider->isAvailable()) {
    $request = new AiRequestDto(
        message: 'Привет!',
        userId: 123,
        platform: 'telegram'
    );
    
    $response = $provider->generateResponse($request);
}

// Тест подключения
$status = $provider->testConnection();
if ($status['success']) {
    echo "AI Service доступен";
}
```

## Автоматические ответы

Для включения автоматических ответов от AI:

1. Установите в `.env`:
```bash
AI_ENABLED=true
AI_AUTO_REPLY=true
AI_DEFAULT_PROVIDER=aiservice
```

2. Настройте условия для AI в админ-панели:
   - Перейдите в раздел "AI Conditions"
   - Создайте условия для автоматических ответов
   - Укажите ключевые слова или паттерны

3. AI будет автоматически отвечать на сообщения, соответствующие условиям

## Мониторинг и логирование

### Просмотр логов AI Service

```bash
# Логи контейнера
docker compose logs -f ai-service

# Логи внутри контейнера
docker compose exec ai-service tail -f /var/log/ai-service.log
```

### Метрики в Laravel

AI Service автоматически логирует:
- Время ответа
- Использованные токены
- Confidence score
- Ошибки и исключения

Логи доступны через Loki/Grafana (если настроено).

## Настройка DeepSeek4Free

### Важно!
Текущая реализация использует **mock-ответы** для тестирования. Для работы с реальным DeepSeek4Free:

1. Установите библиотеку в контейнер:
```bash
docker compose exec ai-service pip install deepseek4free
```

2. Обновите `ai-service/app/services/deepseek_service.py`:
```python
# Замените метод _mock_generate на реальный вызов:
from deepseek4free import DeepSeek

async def generate_response(self, messages, temperature, max_tokens):
    response = await DeepSeek.create(
        messages=messages,
        temperature=temperature,
        max_tokens=max_tokens
    )
    return response
```

3. Настройте аутентификацию (если требуется)

## Масштабирование

### Горизонтальное масштабирование

Для увеличения производительности можно запустить несколько экземпляров:

```yaml
# docker-compose.yml
ai-service:
  deploy:
    replicas: 3
  # ... остальная конфигурация
```

### Балансировка нагрузки

Используйте nginx для балансировки между экземплярами:

```nginx
upstream ai_service {
    server ai-service-1:8000;
    server ai-service-2:8000;
    server ai-service-3:8000;
}

location /ai/ {
    proxy_pass http://ai_service;
}
```

## Troubleshooting

### AI Service не запускается

1. Проверьте логи:
```bash
docker compose logs ai-service
```

2. Проверьте порт 8000:
```bash
docker compose ps
netstat -tulpn | grep 8000
```

3. Пересоберите контейнер:
```bash
docker compose build --no-cache ai-service
docker compose up -d ai-service
```

### Ошибка "AI Service недоступен"

1. Проверьте health endpoint:
```bash
curl http://localhost:8000/health
```

2. Проверьте сетевое подключение из Laravel:
```bash
docker compose exec app curl http://ai-service:8000/health
```

3. Проверьте настройки в `.env`:
```bash
AI_SERVICE_URL=http://ai-service:8000
```

### Медленные ответы

1. Увеличьте timeout в конфигурации:
```bash
AI_SERVICE_TIMEOUT=120
```

2. Уменьшите max_tokens:
```bash
AI_SERVICE_MAX_TOKENS=1000
```

3. Проверьте нагрузку на сервер:
```bash
docker stats ai-service
```

### Rate limit exceeded

Увеличьте лимиты в `.env`:
```bash
AI_RATE_LIMIT_PER_MINUTE=120
AI_RATE_LIMIT_PER_HOUR=2000
```

## Безопасность

### Рекомендации

1. **Не выставляйте AI Service наружу** - он должен быть доступен только внутри Docker сети
2. **Используйте rate limiting** - защита от злоупотреблений
3. **Логируйте все запросы** - для аудита и отладки
4. **Валидируйте входные данные** - защита от инъекций
5. **Мониторьте использование** - контроль расходов на API

### Ограничение доступа

AI Service доступен только внутри Docker сети `pet`. Для внешнего доступа используйте nginx с аутентификацией:

```nginx
location /ai/ {
    auth_basic "AI Service";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://ai-service:8000/;
}
```

## Дополнительные ресурсы

- [DeepSeek4Free GitHub](https://github.com/xtekky/deepseek4free)
- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)

## Поддержка

При возникновении проблем:
1. Проверьте логи: `docker compose logs ai-service`
2. Проверьте health endpoint: `curl http://localhost:8000/health`
3. Проверьте конфигурацию в `.env`
4. Создайте issue в репозитории проекта