# AI Service

Python микросервис на базе FastAPI для генерации AI ответов через DeepSeek4Free.

## Описание

AI Service предоставляет REST API для генерации ответов с использованием DeepSeek AI. Микросервис интегрирован с основным Laravel приложением и может использоваться для автоматических ответов в системе поддержки.

## Технологии

- **Python 3.11**
- **FastAPI** - современный веб-фреймворк
- **Uvicorn** - ASGI сервер
- **Pydantic** - валидация данных
- **DeepSeek4Free** - библиотека для работы с DeepSeek AI
- **httpx** - асинхронный HTTP клиент

## Структура проекта

```
ai-service/
├── app/
│   ├── __init__.py
│   ├── main.py              # Основное приложение FastAPI
│   ├── config.py            # Конфигурация
│   └── services/
│       ├── __init__.py
│       └── deepseek_service.py  # Сервис для работы с DeepSeek
├── Dockerfile               # Docker образ
├── .dockerignore
├── .env.example            # Пример конфигурации
├── requirements.txt        # Python зависимости
└── README.md              # Этот файл
```

## Быстрый старт

### Локальная разработка

1. Установите зависимости:
```bash
cd ai-service
python -m venv venv
source venv/bin/activate  # Linux/Mac
# или
venv\Scripts\activate  # Windows

pip install -r requirements.txt
```

2. Создайте `.env` файл:
```bash
cp .env.example .env
```

3. Запустите сервер:
```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

4. Откройте в браузере:
- API: http://localhost:8000
- Документация: http://localhost:8000/docs
- Health check: http://localhost:8000/health

### Docker

Сервис автоматически запускается через docker-compose из корня проекта:

```bash
# Из корня проекта
docker compose up -d ai-service

# Просмотр логов
docker compose logs -f ai-service

# Перезапуск
docker compose restart ai-service
```

## API Endpoints

### GET /
Корневой endpoint

**Ответ:**
```json
{
  "status": "ok",
  "service": "AI Service",
  "version": "1.0.0"
}
```

### GET /health
Health check endpoint

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
    {
      "role": "system",
      "content": "Ты помощник службы поддержки"
    },
    {
      "role": "user",
      "content": "Как оформить возврат?"
    }
  ],
  "temperature": 0.7,
  "max_tokens": 2000,
  "stream": false
}
```

**Параметры:**
- `messages` (required) - массив сообщений с ролями (system, user, assistant)
- `temperature` (optional) - температура генерации (0.0-2.0), по умолчанию 0.7
- `max_tokens` (optional) - максимальное количество токенов, по умолчанию 2000
- `stream` (optional) - потоковая генерация (пока не поддерживается)

**Ответ:**
```json
{
  "content": "Для оформления возврата необходимо...",
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
```bash
curl -X POST http://localhost:8000/api/v1/chat/simple \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Привет!"}'
```

**Ответ:**
```json
{
  "response": "Здравствуйте! Чем могу помочь?"
}
```

## Конфигурация

### Переменные окружения

Создайте `.env` файл на основе `.env.example`:

```bash
# Application
APP_NAME=AI Service
APP_VERSION=1.0.0
DEBUG=false

# Server
HOST=0.0.0.0
PORT=8000

# CORS (разделенные запятыми)
CORS_ORIGINS=*

# DeepSeek Settings
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_TEMPERATURE=0.7
DEEPSEEK_MAX_TOKENS=2000
DEEPSEEK_TIMEOUT=60

# Retry Settings
MAX_RETRIES=3
RETRY_DELAY=2

# Logging
LOG_LEVEL=INFO
```

## Разработка

### Добавление новых endpoint'ов

Добавьте новый endpoint в `app/main.py`:

```python
@app.post("/api/v1/your-endpoint")
async def your_endpoint(request: YourRequest):
    # Ваша логика
    return {"result": "success"}
```

### Работа с DeepSeek Service

Сервис находится в `app/services/deepseek_service.py`:

```python
from app.services.deepseek_service import DeepSeekService

service = DeepSeekService()
await service.initialize()

response = await service.generate_response(
    messages=[{"role": "user", "content": "Hello"}],
    temperature=0.7,
    max_tokens=1000
)
```

### Тестирование

```bash
# Установка dev зависимостей
pip install pytest pytest-asyncio httpx

# Запуск тестов
pytest

# С покрытием
pytest --cov=app tests/
```

## Интеграция с DeepSeek4Free

### Важно!

Текущая реализация использует **mock-ответы** для тестирования. Для работы с реальным DeepSeek4Free:

1. Установите библиотеку:
```bash
pip install deepseek4free
```

2. Обновите `app/services/deepseek_service.py`:
```python
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

## Мониторинг

### Логирование

Логи выводятся в stdout и могут быть собраны через Docker:

```bash
# Просмотр логов
docker compose logs -f ai-service

# Последние 100 строк
docker compose logs --tail=100 ai-service
```

### Health Check

Docker автоматически проверяет здоровье сервиса:

```bash
# Проверка статуса
docker compose ps ai-service

# Ручная проверка
curl http://localhost:8000/health
```

### Метрики

Сервис логирует:
- Время обработки запросов
- Количество использованных токенов
- Ошибки и исключения
- Статус запросов

## Производительность

### Оптимизация

1. **Кэширование** - используйте Redis для кэширования частых запросов
2. **Пул соединений** - настроен в httpx.AsyncClient
3. **Timeout** - настройте таймауты для предотвращения зависаний
4. **Rate limiting** - ограничение количества запросов

### Масштабирование

Для увеличения производительности:

```yaml
# docker-compose.yml
ai-service:
  deploy:
    replicas: 3
```

## Troubleshooting

### Сервис не запускается

1. Проверьте логи:
```bash
docker compose logs ai-service
```

2. Проверьте порт:
```bash
netstat -tulpn | grep 8000
```

3. Пересоберите образ:
```bash
docker compose build --no-cache ai-service
```

### Медленные ответы

1. Увеличьте timeout:
```bash
DEEPSEEK_TIMEOUT=120
```

2. Уменьшите max_tokens:
```bash
DEEPSEEK_MAX_TOKENS=1000
```

### Ошибки подключения

Проверьте сетевое подключение:
```bash
docker compose exec app curl http://ai-service:8000/health
```

## Безопасность

### Рекомендации

1. Не выставляйте сервис наружу - только внутри Docker сети
2. Используйте rate limiting
3. Валидируйте все входные данные
4. Логируйте все запросы
5. Мониторьте использование ресурсов

### CORS

По умолчанию разрешены все origins (`*`). Для продакшена ограничьте:

```bash
CORS_ORIGINS=https://your-domain.com,https://api.your-domain.com
```

## Лицензия

См. LICENSE файл в корне проекта.

## Поддержка

- Документация: `/docs/ai-service-setup.md`
- Issues: GitHub Issues
- Email: support@example.com

## Ссылки

- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [DeepSeek4Free GitHub](https://github.com/xtekky/deepseek4free)
- [Pydantic Documentation](https://docs.pydantic.dev/)
- [Uvicorn Documentation](https://www.uvicorn.org/)