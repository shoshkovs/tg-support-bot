#!/usr/bin/env python3
"""
Скрипт для получения Cloudflare cookies для DeepSeek API
Запускает браузер и обходит защиту Cloudflare
"""

import sys
import os

# Добавляем текущую директорию в путь для импорта dsk
sys.path.insert(0, '/app')

try:
    from dsk.bypass import main as bypass_main
    
    print("Запуск Cloudflare bypass...")
    print("Это откроет браузер и автоматически обойдет защиту Cloudflare")
    print("Cookies будут сохранены в /app/dsk/cookies.json")
    print()
    
    bypass_main()
    
    print()
    print("✅ Cloudflare cookies успешно получены!")
    print("Перезапустите AI Service: docker compose restart ai-service")
    
except ImportError as e:
    print(f"❌ Ошибка импорта: {e}")
    print("Убедитесь, что все зависимости установлены")
    sys.exit(1)
except Exception as e:
    print(f"❌ Ошибка: {e}")
    sys.exit(1)

# Made with Bob
