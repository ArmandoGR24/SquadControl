# ETAPA 1: Compilar assets de Vue con Vite
FROM node:18-alpine AS build-stage
WORKDIR /app
COPY . .
RUN npm install && npm run build

# ETAPA 2: Servidor de Aplicación (PHP-FPM)
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar dependencias esenciales
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git \
    unzip \
    oniguruma-dev \
    libxml2-dev

# Extensiones PHP necesarias para Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copiar el proyecto y los archivos compilados de Vue
COPY --from=build-stage /app /var/www/html

# Permisos de escritura para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]