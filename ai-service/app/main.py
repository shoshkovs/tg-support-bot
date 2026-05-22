"""
AI Service - FastAPI микросервис для DeepSeek4Free
Предоставляет REST API для генерации ответов через DeepSeek
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import List, Optional
import logging
from contextlib import asynccontextmanager

from app.services.deepseek_service import DeepSeekService
from app.config import settings

# Настройка логирования
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


# Модели данных
class Message(BaseModel):
    """Модель сообщения в диалоге"""
    role: str = Field(..., description="Роль отправителя: system, user, assistant")
    content: str = Field(..., description="Содержимое сообщения")


class ChatRequest(BaseModel):
    """Запрос на генерацию ответа"""
    messages: List[Message] = Field(..., description="История диалога")
    temperature: Optional[float] = Field(0.7, ge=0.0, le=2.0, description="Температура генерации")
    max_tokens: Optional[int] = Field(2000, ge=1, le=4000, description="Максимальное количество токенов")
    stream: Optional[bool] = Field(False, description="Потоковая генерация (пока не поддерживается)")


class ChatResponse(BaseModel):
    """Ответ от AI"""
    content: str = Field(..., description="Сгенерированный ответ")
    model: str = Field(..., description="Использованная модель")
    usage: dict = Field(default_factory=dict, description="Статистика использования токенов")


class HealthResponse(BaseModel):
    """Статус здоровья сервиса"""
    status: str
    service: str
    version: str


# Инициализация сервиса
deepseek_service: Optional[DeepSeekService] = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Управление жизненным циклом приложения"""
    global deepseek_service
    
    # Startup
    logger.info("Запуск AI Service...")
    deepseek_service = DeepSeekService()
    await deepseek_service.initialize()
    logger.info("AI Service успешно запущен")
    
    yield
    
    # Shutdown
    logger.info("Остановка AI Service...")
    if deepseek_service:
        await deepseek_service.cleanup()
    logger.info("AI Service остановлен")


# Создание приложения
app = FastAPI(
    title="AI Service",
    description="Микросервис для генерации ответов через DeepSeek4Free",
    version="1.0.0",
    lifespan=lifespan
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS.split(",") if settings.CORS_ORIGINS != "*" else ["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/", response_model=HealthResponse)
async def root():
    """Корневой эндпоинт - проверка работоспособности"""
    return HealthResponse(
        status="ok",
        service="AI Service",
        version="1.0.0"
    )


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Проверка здоровья сервиса"""
    return HealthResponse(
        status="healthy",
        service="AI Service",
        version="1.0.0"
    )


@app.post("/api/v1/chat/completions", response_model=ChatResponse)
async def chat_completions(request: ChatRequest):
    """
    Генерация ответа на основе истории диалога
    
    Совместим с OpenAI API format
    """
    try:
        if not deepseek_service:
            raise HTTPException(
                status_code=503,
                detail="AI Service не инициализирован"
            )
        
        logger.info(f"Получен запрос с {len(request.messages)} сообщениями")
        
        # Конвертируем сообщения в формат для DeepSeek
        messages = [
            {"role": msg.role, "content": msg.content}
            for msg in request.messages
        ]
        
        # Генерируем ответ
        response = await deepseek_service.generate_response(
            messages=messages,
            temperature=request.temperature or 0.7,
            max_tokens=request.max_tokens or 2000
        )
        
        logger.info("Ответ успешно сгенерирован")
        
        return ChatResponse(
            content=response["content"],
            model=response.get("model", "deepseek-chat"),
            usage=response.get("usage", {})
        )
        
    except Exception as e:
        logger.error(f"Ошибка при генерации ответа: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=500,
            detail=f"Ошибка генерации ответа: {str(e)}"
        )


class SimpleRequest(BaseModel):
    """Упрощенный запрос с одним промптом"""
    prompt: str = Field(..., description="Текст запроса пользователя")


@app.post("/api/v1/chat/simple")
async def simple_chat(request: SimpleRequest):
    """
    Упрощенный эндпоинт для быстрых запросов
    
    Args:
        request: Запрос с промптом
    
    Returns:
        Сгенерированный ответ
    """
    try:
        if not deepseek_service:
            raise HTTPException(
                status_code=503,
                detail="AI Service не инициализирован"
            )
        
        messages = [{"role": "user", "content": request.prompt}]
        
        response = await deepseek_service.generate_response(messages=messages)
        
        return {"response": response["content"]}
        
    except Exception as e:
        logger.error(f"Ошибка при генерации ответа: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=500,
            detail=f"Ошибка генерации ответа: {str(e)}"
        )


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )

# Made with Bob
