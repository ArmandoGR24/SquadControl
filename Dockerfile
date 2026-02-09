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

# Instalar extensiones de PHP necesarias para que 'php artisan' funcione
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

WORKDIR /app
COPY . .

# 1. Instalar dependencias de PHP (necesario para los plugins de Vite)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 2. Instalar dependencias de Node y compilar assets
RUN npm install && npm run build


# ETAPA 2: Servidor de Producción (PHP-FPM)
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar extensiones necesarias en la imagen final
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copiar todo desde la etapa de build (incluye vendor/ y public/build/)
COPY --from=build-stage /app /var/www/html

# Ajustar permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]