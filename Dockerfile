FROM ubuntu:26.04 AS base

ENV DEBIAN_FRONTEND=noninteractive
ENV NVM_DIR=/root/.nvm
ENV NODE_VERSION=26

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    php8.5 \
    php8.5-cli \
    php8.5-common \
    php8.5-fpm \
    php8.5-mysql \
    php8.5-zip \
    php8.5-gd \
    php8.5-mbstring \
    php8.5-curl \
    php8.5-xml \
    php8.5-bcmath \
    php8.5-pdo \
    php8.5-xdebug \
    php8.5-redis \
    nginx \
    curl \
    redis \
    cron \
    mariadb-client \
    graphviz \
    unzip && rm -rf /var/lib/apt/lists/*

# Set PHP ini parameters
RUN sed -i 's/^upload_max_filesize.*/upload_max_filesize = 128M/' /etc/php/8.5/fpm/php.ini
RUN sed -i 's/^post_max_size.*/post_max_size = 128M/' /etc/php/8.5/fpm/php.ini
RUN sed -i 's/^memory_limit.*/memory_limit = 512M/' /etc/php/8.5/fpm/php.ini

# Create nginx configuration
RUN echo "\
    server {\n\
        listen 80;\n\
        listen [::]:80;\n\
        root /var/www/html/public;\n\
        add_header X-Frame-Options \"SAMEORIGIN\";\n\
        add_header X-Content-Type-Options \"nosniff\";\n\
        index index.php;\n\
        charset utf-8;\n\
        client_max_body_size 128M;\n\
        access_log off;\n\
        log_not_found off;\n\
        location / {\n\
            try_files \$uri \$uri/ /index.php?\$query_string;\n\
        }\n\
        error_page 404 /index.php;\n\
        location ~ \.php$ {\n\
            fastcgi_pass unix:/run/php/php8.5-fpm.sock;\n\
            fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;\n\
            include fastcgi_params;\n\
            fastcgi_hide_header X-Powered-By;\n\
        }\n\
        location ~ /\.(?!well-known).* {\n\
            deny all;\n\
        }\n\
    }\n" > /etc/nginx/sites-available/default

# Create healthcheck script
RUN echo "\
    sleep 60\n\
    while true; do\n\
      /usr/bin/curl -f http://localhost:80/login\n\
      rc=\$?\n\
      if [ \$rc -ne 0 ]; then\n\
        pkill tail || true\n\
      fi\n\
      sleep 15\n\
    done\n" > /healthcheck.sh

RUN chmod 755 /healthcheck.sh

# Create startup script
RUN echo "\
    /usr/bin/env > /var/www/html/containerenv\n\
    echo \"Starting services...\"\n\
    phpdismod xdebug\n\
    service php8.5-fpm start\n\
    service redis-server start\n\
    nginx -g \"daemon off;\" &\n\
    /healthcheck.sh &\n\
    echo -e \"ssl = 1\nssl-verify-server-cert = 0\n\" >> /etc/mysql/mariadb.cnf\n\
    su -c \"cd /var/www/html && php artisan migrate --force\" -s /bin/bash www-data\n\
    su -c \"cd /var/www/html && php artisan route:cache\" -s /bin/bash www-data\n\
    su -c \"cd /var/www/html && php artisan view:cache\" -s /bin/bash www-data\n\
    su -c \"cd /var/www/html && php artisan config:cache\" -s /bin/bash www-data\n\
    service cron start\n\
    echo \"Ready.\"\n\
    tail -s 1 /var/log/nginx/*.log -f\n" > /start.sh

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure redis
RUN echo "\
    maxmemory 100mb\n\
    maxmemory-policy allkeys-lru\n" >> /etc/redis/redis.conf

# Create cronjob
RUN printf '%s\n' "* * * * *  www-data cd /var/www/html && /usr/bin/php artisan schedule:run > /dev/null 2>&1" >> /etc/crontab

# Clear html directory
RUN rm -rf /var/www/html/*

# Set ownership of directory
RUN chown www-data:www-data /var/www/html

# Set working directory
WORKDIR /var/www/html

# Copy files
COPY --chown=www-data:www-data . /var/www/html

FROM base AS composer-builder

# Install composer dependencies
RUN su -c "cd /var/www/html && composer install --no-interaction --prefer-dist --optimize-autoloader" -s /bin/bash www-data

FROM base AS frontend-builder

# Install Node.js and build frontend assets
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.7/install.sh | bash && \
    . "$NVM_DIR/nvm.sh" && \
    nvm install ${NODE_VERSION} && \
    nvm alias default ${NODE_VERSION} && \
    nvm use ${NODE_VERSION} && \
    npm install && npm run build

FROM base AS runtime

# Copy built application dependencies and assets from build stages
COPY --from=composer-builder --chown=www-data:www-data /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend-builder --chown=www-data:www-data /var/www/html/public /var/www/html/public
COPY --from=frontend-builder --chown=www-data:www-data /var/www/html/bootstrap /var/www/html/bootstrap

# Configure healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD curl -f http://localhost:80/login || exit 1

# Expose web port
EXPOSE 80

# Start commands
CMD ["bash", "/start.sh"]
