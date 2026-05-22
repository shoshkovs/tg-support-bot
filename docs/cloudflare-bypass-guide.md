# Cloudflare Bypass для DeepSeek API

## Проблема

DeepSeek API защищен Cloudflare, который блокирует автоматические запросы. Библиотека `dsk` требует специальный cookie `cf_clearance` для обхода этой защиты.

## Признаки проблемы

- API возвращает 0 chunks
- В логах: `Получено 0 chunks от API`
- Ответы пустые: `{"response":""}`

## Решение

### Вариант 1: Автоматический bypass (рекомендуется)

Запустите скрипт внутри контейнера:

```bash
# Войдите в контейнер
docker compose exec ai-service bash

# Запустите bypass
python -m dsk.bypass

# Или используйте готовый скрипт
python /app/get_cloudflare_cookies.py
```

Скрипт:
1. Откроет браузер (headless)
2. Посетит DeepSeek
3. Автоматически обойдет Cloudflare
4. Сохранит cookies в `/app/dsk/cookies.json`

После этого перезапустите сервис:
```bash
docker compose restart ai-service
```

### Вариант 2: Ручное получение cookies

Если автоматический bypass не работает (нет GUI на сервере):

1. **На локальной машине с браузером:**
   ```bash
   # Клонируйте репозиторий
   git clone https://github.com/xtekky/deepseek4free.git
   cd deepseek4free
   
   # Установите зависимости
   pip install -r requirements.txt
   
   # Запустите bypass
   python -m dsk.bypass
   ```

2. **Скопируйте cookies на сервер:**
   ```bash
   # Найдите созданный cookies.json
   # Обычно в: deepseek4free/dsk/cookies.json
   
   # Скопируйте на сервер
   scp dsk/cookies.json root@your-server:/root/tg-support-bot/ai-service/dsk/
   ```

3. **Перезапустите контейнер:**
   ```bash
   docker compose restart ai-service
   ```

### Вариант 3: Создание cookies вручную

Если bypass не работает, создайте cookies.json вручную:

1. **Получите cf_clearance cookie:**
   - Откройте https://chat.deepseek.com в браузере
   - Откройте DevTools (F12) → Application → Cookies
   - Найдите cookie `cf_clearance`
   - Скопируйте его значение

2. **Создайте файл cookies.json:**
   ```bash
   docker compose exec ai-service bash
   
   cat > /app/dsk/cookies.json << 'EOF'
   {
     "cf_clearance": "ваше_значение_cf_clearance_здесь"
   }
   EOF
   ```

3. **Перезапустите:**
   ```bash
   docker compose restart ai-service
   ```

## Проверка

После настройки cookies проверьте работу:

```bash
# Тест API
docker compose exec app curl -X POST http://ai-service:8000/api/v1/chat/simple \
  -H "Content-Type: application/json" \
  -d '{"prompt":"Привет!"}'

# Должен вернуть реальный ответ, а не пустую строку
```

Проверьте логи:
```bash
docker compose logs ai-service | grep -E "(chunks|Cloudflare)"
```

Должно быть:
```
INFO - Получено X chunks от API  # X > 0
INFO - Ответ успешно сгенерирован через DeepSeek API. Длина ответа: Y символов  # Y > 0
```

## Срок действия cookies

Cloudflare cookies могут истекать. Если API снова перестал работать:

1. Проверьте логи на ошибки Cloudflare
2. Повторите процедуру получения cookies
3. Перезапустите сервис

## Альтернатива

Если Cloudflare bypass не работает, используйте другие AI провайдеры:

```bash
# В .env
AI_PROVIDER=openai  # или gigachat, или deepseek (официальный API)
```

См. [`docs/ai-service-setup.md`](ai-service-setup.md) для настройки других провайдеров.

## Troubleshooting

### Ошибка: "No module named 'nodriver'"

Установите зависимости:
```bash
docker compose exec ai-service pip install nodriver drissionpage
```

### Ошибка: "Display not found"

На сервере без GUI используйте Вариант 2 (ручное получение на локальной машине).

### Cookies не помогают

1. Проверьте формат cookies.json
2. Убедитесь, что файл доступен: `/app/dsk/cookies.json`
3. Проверьте права: `chmod 644 /app/dsk/cookies.json`
4. Попробуйте получить свежие cookies

## Дополнительная информация

- [DeepSeek4Free GitHub](https://github.com/xtekky/deepseek4free)
- [Cloudflare Bypass документация](https://github.com/xtekky/deepseek4free#handling-cloudflare-challenges)