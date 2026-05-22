# Развертывание AI Service на сервере

## Проблема

AI Service контейнер не запускается. Ошибка: `Connection refused` на порту 8000.

## Решение

### Шаг 1: Проверка статуса контейнера

```bash
# Проверьте все контейнеры
docker compose ps

# Проверьте логи AI Service
docker compose logs ai-service

# Если контейнер не запущен, проверьте ошибки сборки
docker compose logs --tail=100 ai-service
```

### Шаг 2: Запуск AI Service

```bash
# Перейдите в директорию проекта
cd ~/tg-support-bot

# Остановите все контейнеры
docker compose down

# Пересоберите AI Service
docker compose build ai-service

# Запустите только AI Service для проверки
docker compose up ai-service

# Если все ОК, запустите все сервисы
docker compose up -d
```

### Шаг 3: Проверка работы

```bash
# Проверьте статус контейнера
docker compose ps ai-service

# Должно быть: Up (healthy)

# Проверьте логи
docker compose logs -f ai-service

# Проверьте health endpoint
curl http://localhost:8000/health

# Или изнутри Docker сети
docker compose exec app curl http://ai-service:8000/health
```

### Шаг 4: Тестовый запрос

```bash
# Простой запрос
curl -X POST http://localhost:8000/api/v1/chat/simple \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Привет!"}'

# Полный запрос
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "user", "content": "Привет!"}
    ],
    "temperature": 0.7,
    "max_tokens": 100
  }'
```

## Возможные проблемы и решения

### 1. Контейнер не запускается

**Проблема:** `docker compose ps` показывает `Exit 1` или `Restarting`

**Решение:**
```bash
# Проверьте логи
docker compose logs ai-service

# Пересоберите без кэша
docker compose build --no-cache ai-service

# Проверьте Dockerfile
cat ai-service/Dockerfile
```

### 2. Порт 8000 занят

**Проблема:** `port is already allocated`

**Решение:**
```bash
# Проверьте, что использует порт 8000
netstat -tulpn | grep 8000
# или
lsof -i :8000

# Измените порт в docker-compose.yml
# Вместо "8000:8000" используйте "8001:8000"
```

### 3. Ошибка импорта Python модулей

**Проблема:** `ModuleNotFoundError: No module named 'fastapi'`

**Решение:**
```bash
# Проверьте requirements.txt
cat ai-service/requirements.txt

# Пересоберите образ
docker compose build --no-cache ai-service
```

### 4. Healthcheck fails

**Проблема:** Контейнер запущен, но healthcheck показывает `unhealthy`

**Решение:**
```bash
# Проверьте логи
docker compose logs ai-service

# Зайдите в контейнер
docker compose exec ai-service bash

# Проверьте процессы
ps aux | grep uvicorn

# Проверьте порт изнутри
curl http://localhost:8000/health
```

### 5. Connection refused

**Проблема:** `curl: (7) Failed to connect to localhost port 8000`

**Причины:**
1. Контейнер не запущен
2. Порт не пробрасывается
3. Приложение не слушает на 0.0.0.0

**Решение:**
```bash
# 1. Проверьте статус
docker compose ps ai-service

# 2. Проверьте порты
docker compose port ai-service 8000

# 3. Проверьте из другого контейнера
docker compose exec app curl http://ai-service:8000/health

# 4. Проверьте конфигурацию в app/config.py
docker compose exec ai-service cat app/config.py | grep HOST
```

## Альтернативный запуск (без Docker)

Если Docker не работает, можно запустить локально:

```bash
# Установите Python 3.11
sudo apt update
sudo apt install python3.11 python3.11-venv python3-pip

# Перейдите в директорию
cd ~/tg-support-bot/ai-service

# Создайте виртуальное окружение
python3.11 -m venv venv
source venv/bin/activate

# Установите зависимости
pip install -r requirements.txt

# Создайте .env файл
cp .env.example .env

# Запустите сервер
uvicorn app.main:app --host 0.0.0.0 --port 8000

# Или в фоне
nohup uvicorn app.main:app --host 0.0.0.0 --port 8000 > ai-service.log 2>&1 &
```

## Настройка для продакшена

### 1. Используйте systemd service

Создайте `/etc/systemd/system/ai-service.service`:

```ini
[Unit]
Description=AI Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/root/tg-support-bot/ai-service
Environment="PATH=/root/tg-support-bot/ai-service/venv/bin"
ExecStart=/root/tg-support-bot/ai-service/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Запустите:
```bash
sudo systemctl daemon-reload
sudo systemctl enable ai-service
sudo systemctl start ai-service
sudo systemctl status ai-service
```

### 2. Настройте nginx reverse proxy

Добавьте в nginx конфигурацию:

```nginx
location /ai/ {
    proxy_pass http://localhost:8000/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # Таймауты для AI запросов
    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;
}
```

### 3. Настройте мониторинг

```bash
# Добавьте в crontab проверку
*/5 * * * * curl -f http://localhost:8000/health || systemctl restart ai-service
```

## Проверка интеграции с Laravel

```bash
# Зайдите в контейнер Laravel
docker compose exec app bash

# Проверьте подключение к AI Service
curl http://ai-service:8000/health

# Проверьте через PHP
php artisan tinker

# В tinker:
$provider = new \App\Services\Ai\AiServiceProvider();
$status = $provider->testConnection();
print_r($status);
```

## Логи и отладка

```bash
# Логи AI Service
docker compose logs -f ai-service

# Логи Laravel (AI запросы)
docker compose logs -f app | grep "AI Service"

# Логи nginx
docker compose logs -f nginx

# Все логи вместе
docker compose logs -f ai-service app nginx
```

## Быстрая диагностика

Выполните эту команду для полной диагностики:

```bash
#!/bin/bash
echo "=== AI Service Diagnostics ==="
echo ""
echo "1. Container status:"
docker compose ps ai-service
echo ""
echo "2. Container logs (last 20 lines):"
docker compose logs --tail=20 ai-service
echo ""
echo "3. Port check:"
docker compose port ai-service 8000 2>/dev/null || echo "Port not exposed"
echo ""
echo "4. Health check from host:"
curl -s http://localhost:8000/health || echo "Failed to connect from host"
echo ""
echo "5. Health check from app container:"
docker compose exec -T app curl -s http://ai-service:8000/health || echo "Failed to connect from app"
echo ""
echo "6. Process check:"
docker compose exec -T ai-service ps aux | grep uvicorn || echo "Uvicorn not running"
```

Сохраните как `diagnose-ai-service.sh` и запустите:
```bash
chmod +x diagnose-ai-service.sh
./diagnose-ai-service.sh
```

## Контакты для поддержки

Если проблема не решается:
1. Соберите вывод диагностики
2. Проверьте логи: `docker compose logs ai-service`
3. Создайте issue в репозитории с полной информацией