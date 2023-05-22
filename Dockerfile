FROM php:7.4-cli

# Устанавливаем дополнительные пакеты
RUN apt-get update && apt-get install -y libmemcached-dev zlib1g-dev \
    && pecl install memcached \
    && docker-php-ext-enable memcached

# Копируем файлы проекта
COPY . /app

# Устанавливаем рабочую директорию
WORKDIR /app
