#!/bin/bash

# AI Service Diagnostics Script
# Быстрая диагностика проблем с AI Service

echo "========================================="
echo "   AI Service Diagnostics"
echo "========================================="
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Проверка статуса контейнера
echo "1. Container Status:"
echo "-------------------"
if docker compose ps ai-service &>/dev/null; then
    docker compose ps ai-service
    STATUS=$?
    if [ $STATUS -eq 0 ]; then
        echo -e "${GREEN}✓ Container exists${NC}"
    else
        echo -e "${RED}✗ Container not found${NC}"
    fi
else
    echo -e "${RED}✗ Docker compose not available or ai-service not defined${NC}"
fi
echo ""

# 2. Логи контейнера
echo "2. Container Logs (last 30 lines):"
echo "-----------------------------------"
if docker compose logs --tail=30 ai-service 2>/dev/null; then
    echo -e "${GREEN}✓ Logs retrieved${NC}"
else
    echo -e "${RED}✗ Failed to get logs${NC}"
fi
echo ""

# 3. Проверка порта
echo "3. Port Check:"
echo "--------------"
PORT_CHECK=$(docker compose port ai-service 8000 2>/dev/null)
if [ -n "$PORT_CHECK" ]; then
    echo -e "${GREEN}✓ Port exposed: $PORT_CHECK${NC}"
else
    echo -e "${RED}✗ Port 8000 not exposed${NC}"
fi
echo ""

# 4. Health check с хоста
echo "4. Health Check from Host:"
echo "--------------------------"
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" http://localhost:8000/health 2>/dev/null)
HTTP_CODE=$(echo "$HEALTH_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$HEALTH_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Health check successful${NC}"
    echo "Response: $RESPONSE_BODY"
else
    echo -e "${RED}✗ Health check failed (HTTP $HTTP_CODE)${NC}"
    echo "Trying to connect..."
    curl -v http://localhost:8000/health 2>&1 | head -n 20
fi
echo ""

# 5. Health check из app контейнера
echo "5. Health Check from App Container:"
echo "------------------------------------"
APP_HEALTH=$(docker compose exec -T app curl -s http://ai-service:8000/health 2>/dev/null)
if [ -n "$APP_HEALTH" ]; then
    echo -e "${GREEN}✓ Connection from app successful${NC}"
    echo "Response: $APP_HEALTH"
else
    echo -e "${RED}✗ Cannot connect from app container${NC}"
fi
echo ""

# 6. Проверка процессов в контейнере
echo "6. Process Check:"
echo "-----------------"
PROCESSES=$(docker compose exec -T ai-service ps aux 2>/dev/null | grep -E "uvicorn|python")
if [ -n "$PROCESSES" ]; then
    echo -e "${GREEN}✓ Uvicorn process found${NC}"
    echo "$PROCESSES"
else
    echo -e "${RED}✗ Uvicorn process not running${NC}"
fi
echo ""

# 7. Проверка Python и зависимостей
echo "7. Python Environment:"
echo "----------------------"
PYTHON_VERSION=$(docker compose exec -T ai-service python --version 2>/dev/null)
if [ -n "$PYTHON_VERSION" ]; then
    echo -e "${GREEN}✓ Python: $PYTHON_VERSION${NC}"
else
    echo -e "${RED}✗ Python not found${NC}"
fi

FASTAPI_CHECK=$(docker compose exec -T ai-service python -c "import fastapi; print(fastapi.__version__)" 2>/dev/null)
if [ -n "$FASTAPI_CHECK" ]; then
    echo -e "${GREEN}✓ FastAPI: $FASTAPI_CHECK${NC}"
else
    echo -e "${RED}✗ FastAPI not installed${NC}"
fi
echo ""

# 8. Проверка файлов
echo "8. File Structure:"
echo "------------------"
if [ -f "ai-service/Dockerfile" ]; then
    echo -e "${GREEN}✓ Dockerfile exists${NC}"
else
    echo -e "${RED}✗ Dockerfile not found${NC}"
fi

if [ -f "ai-service/requirements.txt" ]; then
    echo -e "${GREEN}✓ requirements.txt exists${NC}"
else
    echo -e "${RED}✗ requirements.txt not found${NC}"
fi

if [ -f "ai-service/app/main.py" ]; then
    echo -e "${GREEN}✓ app/main.py exists${NC}"
else
    echo -e "${RED}✗ app/main.py not found${NC}"
fi
echo ""

# 9. Проверка docker-compose.yml
echo "9. Docker Compose Configuration:"
echo "--------------------------------"
if grep -q "ai-service:" docker-compose.yml; then
    echo -e "${GREEN}✓ ai-service defined in docker-compose.yml${NC}"
    echo "Configuration:"
    grep -A 20 "ai-service:" docker-compose.yml | head -n 20
else
    echo -e "${RED}✗ ai-service not found in docker-compose.yml${NC}"
fi
echo ""

# 10. Рекомендации
echo "========================================="
echo "   Recommendations"
echo "========================================="
echo ""

if [ "$HTTP_CODE" != "200" ]; then
    echo -e "${YELLOW}⚠ AI Service is not responding${NC}"
    echo "Try these steps:"
    echo "1. Check logs: docker compose logs ai-service"
    echo "2. Rebuild: docker compose build --no-cache ai-service"
    echo "3. Restart: docker compose restart ai-service"
    echo "4. Full restart: docker compose down && docker compose up -d"
    echo ""
fi

if [ -z "$PROCESSES" ]; then
    echo -e "${YELLOW}⚠ Uvicorn is not running${NC}"
    echo "Try:"
    echo "1. Check container logs: docker compose logs ai-service"
    echo "2. Check if container is running: docker compose ps ai-service"
    echo "3. Restart container: docker compose restart ai-service"
    echo ""
fi

if [ -z "$APP_HEALTH" ]; then
    echo -e "${YELLOW}⚠ App container cannot reach AI Service${NC}"
    echo "Check:"
    echo "1. Network configuration in docker-compose.yml"
    echo "2. Both containers are in the same network"
    echo "3. Service name is 'ai-service' in docker-compose.yml"
    echo ""
fi

echo "========================================="
echo "For more help, see: docs/ai-service-deployment.md"
echo "========================================="

# Made with Bob
