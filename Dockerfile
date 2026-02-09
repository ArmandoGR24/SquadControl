# ETAPA 1: Compilación de Frontend (Necesitamos PHP + Node 20+)
FROM php:8.2-alpine AS build-stage

# Instalar Node.js 20 y npm (necesarios para Vite 7)
RUN apk add --no-cache nodejs npm

WORKDIR /app
COPY . .

# Instalar dependencias de Node y compilar
# Ahora 'php artisan' funcionará porque la base ya es PHP
RUN npm install && npm run build


# ETAPA 2: Servidor de Producción (PHP-FPM)
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Instalar extensiones de PHP esenciales para Laravel
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

# Copiar el código y los assets compilados desde la Etapa 1
COPY --from=build-stage /app /var/www/html

# Ajustar permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]