# ETAPA 1: Compilación de Frontend y Preparación de Laravel
FROM php:8.2-alpine AS build-stage

# Instalar dependencias del sistema, Node.js y herramientas de PHP
RUN apk add --no-cache \
    nodejs \
    npm \
    composer \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev

WORKDIR /app
COPY . .

# 1. Instalar dependencias de PHP 
# Usamos --ignore-platform-reqs para que no falle por falta de extensiones en esta etapa
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

# 2. Instalar dependencias de Node y compilar assets
RUN npm install && npm run build


# ETAPA 2: Servidor de Producción (PHP-FPM)
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar extensiones necesarias en la imagen final para que Laravel funcione
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev

# Instalamos las extensiones críticas (incluyendo las que pidió el log anterior)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copiar todo desde la etapa de build
COPY --from=build-stage /app /var/www/html

# Ajustar permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]