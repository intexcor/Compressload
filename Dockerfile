FROM ubuntu:latest
ARG DEBIAN_FRONTEND=noninteractive
# Установка необходимых пакетов
RUN apt-get update && \
    apt-get install -y python3 python3-pip ffmpeg php composer libcurl4-openssl-dev php-curl git zip python-is-python3


RUN git clone https://github.com/ytdl-org/youtube-dl.git /tmp/youtube-dl

# Сборка youtube-dl
WORKDIR /tmp/youtube-dl
RUN make youtube-dl

# Копирование youtube-dl в /usr/local/bin/
RUN cp /tmp/youtube-dl/youtube-dl /usr/local/bin/

# Удаление временных файлов
RUN rm -rf /tmp/youtube-dl


# Копирование PHP-кода в образ
COPY main.php /app/main.php
COPY composer.json /app/composer.json

WORKDIR /app

# Установка зависимостей Composer
RUN cd /app && \
    composer install


CMD ["php", "/app/main.php"]
