FROM php:8.1-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        intl \
        openssl \
        fileinfo \
    && rm -rf /var/lib/apt/lists/*

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www

# 复制应用文件
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 777 /var/www/runtime \
    && mkdir -p /var/www/public/uploads \
    && chmod 777 /var/www/public/uploads

# 暴露端口
EXPOSE 9000

# 启动PHP-FPM
CMD ["php-fpm"]
