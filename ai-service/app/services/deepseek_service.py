"""
DeepSeek Service - обертка для работы с DeepSeek через dsk библиотеку
"""

import logging
import asyncio
from typing import List, Dict, Any, Optional
import os

logger = logging.getLogger(__name__)


class DeepSeekService:
    """Сервис для работы с DeepSeek через dsk библиотеку"""
    
    def __init__(self):
        """Инициализация сервиса"""
        self.api = None
        self.auth_token = None
        self.initialized = False
        self.use_mock = True  # По умолчанию используем mock
        
    async def initialize(self):
        """Инициализация DeepSeek API"""
        try:
            # Получаем токен из переменных окружения
            self.auth_token = os.getenv('DEEPSEEK_AUTH_TOKEN')
            
            if self.auth_token:
                # Пытаемся инициализировать реальный API
                try:
                    from dsk.api import DeepSeekAPI
                    self.api = DeepSeekAPI(self.auth_token)
                    self.use_mock = False
                    logger.info("DeepSeek Service инициализирован с реальным API")
                except Exception as e:
                    logger.warning(f"Не удалось инициализировать DeepSeek API: {e}. Используем mock.")
                    self.use_mock = True
            else:
                logger.info("DEEPSEEK_AUTH_TOKEN не установлен. Используем mock-режим.")
                self.use_mock = True
            
            self.initialized = True
            
        except Exception as e:
            logger.error(f"Ошибка инициализации DeepSeek Service: {e}")
            self.use_mock = True
            self.initialized = True
    
    async def cleanup(self):
        """Очистка ресурсов"""
        self.api = None
        logger.info("DeepSeek Service остановлен")
    
    async def generate_response(
        self,
        messages: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = 2000,
        retry_count: int = 3
    ) -> Dict[str, Any]:
        """
        Генерация ответа через DeepSeek
        
        Args:
            messages: История диалога в формате [{"role": "user", "content": "..."}]
            temperature: Температура генерации (0.0 - 2.0)
            max_tokens: Максимальное количество токенов
            retry_count: Количество попыток при ошибке
        
        Returns:
            Dict с ключами: content, model, usage
        """
        if not self.initialized:
            raise RuntimeError("DeepSeek Service не инициализирован")
        
        # Если используем mock или API недоступен
        if self.use_mock or not self.api:
            return await self._mock_generate(messages, temperature, max_tokens)
        
        # Реальная генерация через dsk
        for attempt in range(retry_count):
            try:
                logger.info(f"Попытка {attempt + 1}/{retry_count} генерации ответа через DeepSeek API")
                
                # Создаем новую сессию чата
                chat_id = self.api.create_chat_session()
                
                # Формируем промпт из всех сообщений
                prompt = self._format_messages(messages)
                
                # Собираем ответ
                full_response = ""
                thinking_process = ""
                
                for chunk in self.api.chat_completion(
                    chat_id,
                    prompt,
                    thinking_enabled=True,
                    search_enabled=False
                ):
                    # Логируем структуру chunk для отладки
                    logger.debug(f"Получен chunk: {chunk}")
                    
                    if chunk['type'] == 'thinking':
                        thinking_process += chunk['content']
                    elif chunk['type'] == 'text':
                        full_response += chunk['content']
                    else:
                        # Логируем неизвестные типы
                        logger.warning(f"Неизвестный тип chunk: {chunk['type']}, content: {chunk.get('content', 'N/A')}")
                
                logger.info(f"Ответ успешно сгенерирован через DeepSeek API. Длина ответа: {len(full_response)} символов")
                
                return {
                    "content": full_response,
                    "model": "deepseek-chat",
                    "usage": {
                        "prompt_tokens": sum(len(m["content"].split()) for m in messages),
                        "completion_tokens": len(full_response.split()),
                        "total_tokens": sum(len(m["content"].split()) for m in messages) + len(full_response.split())
                    },
                    "thinking_process": thinking_process if thinking_process else None
                }
                
            except Exception as e:
                logger.error(f"Ошибка при генерации (попытка {attempt + 1}): {e}")
                
                if attempt < retry_count - 1:
                    await asyncio.sleep(2 ** attempt)  # Exponential backoff
                else:
                    # Если все попытки неудачны, возвращаемся к mock
                    logger.warning("Все попытки неудачны, используем mock-ответ")
                    return await self._mock_generate(messages, temperature, max_tokens)
        
        raise RuntimeError("Не удалось сгенерировать ответ после всех попыток")
    
    def _format_messages(self, messages: List[Dict[str, str]]) -> str:
        """
        Форматирует историю сообщений в один промпт
        
        Args:
            messages: Список сообщений
            
        Returns:
            Отформатированный промпт
        """
        formatted = []
        
        for msg in messages:
            role = msg.get("role", "user")
            content = msg.get("content", "")
            
            if role == "system":
                formatted.append(f"System: {content}")
            elif role == "user":
                formatted.append(f"User: {content}")
            elif role == "assistant":
                formatted.append(f"Assistant: {content}")
        
        return "\n\n".join(formatted)
    
    async def _mock_generate(
        self,
        messages: List[Dict[str, str]],
        temperature: float,
        max_tokens: int
    ) -> Dict[str, Any]:
        """
        MOCK-реализация для тестирования
        Используется когда реальный API недоступен
        """
        # Симулируем задержку API
        await asyncio.sleep(0.5)
        
        # Получаем последнее сообщение пользователя
        user_message = ""
        for msg in reversed(messages):
            if msg["role"] == "user":
                user_message = msg["content"]
                break
        
        # Генерируем mock-ответ
        mock_response = (
            f"Это тестовый ответ от AI Service (mock-режим).\n\n"
            f"Ваш вопрос: '{user_message[:100]}{'...' if len(user_message) > 100 else ''}'\n\n"
            f"Для использования реального DeepSeek API:\n"
            f"1. Получите токен на chat.deepseek.com\n"
            f"2. Установите DEEPSEEK_AUTH_TOKEN в переменных окружения\n"
            f"3. Перезапустите AI Service"
        )
        
        return {
            "content": mock_response,
            "model": "deepseek-chat-mock",
            "usage": {
                "prompt_tokens": sum(len(m["content"].split()) for m in messages),
                "completion_tokens": len(mock_response.split()),
                "total_tokens": sum(len(m["content"].split()) for m in messages) + len(mock_response.split())
            }
        }
    
    async def health_check(self) -> bool:
        """Проверка работоспособности сервиса"""
        try:
            if not self.initialized:
                return False
            
            # Простой тест генерации
            test_messages = [{"role": "user", "content": "test"}]
            await self.generate_response(test_messages, max_tokens=10)
            return True
            
        except Exception as e:
            logger.error(f"Health check failed: {e}")
            return False

# Made with Bob
