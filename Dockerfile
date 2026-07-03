FROM ubuntu:24.04 AS base

ARG RELEASE_TEXT="localdev"
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt update
RUN apt install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN apt update
RUN apt install -y \
    php8.5\
    php8.5-cli\
    php8.5-common\
    php8.5-fpm\
    php8.5-mysql\
    php8.5-zip\
    php8.5-gd\
    php8.5-mbstring\
    php8.5-curl\
    php8.5-xml\
    php8.5-bcmath\
    php8.5-pdo\
    php8.5-xdebug\
    php8.5-redis\
    nginx\
    curl\
    redis\
    graphviz\
    cron

# Set PHP ini parameters
RUN sed -i 's/^upload_max_filesize.*/upload_max_filesize = 1G/' /etc/php/8.5/fpm/php.ini
RUN sed -i 's/^post_max_size.*/post_max_size = 250M/' /etc/php/8.5/fpm/php.ini
RUN sed -i 's/^memory_limit.*/memory_limit = 1G/' /etc/php/8.5/fpm/php.ini

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
    done\n\
    " > /healthcheck.sh

RUN chmod 755 /healthcheck.sh

# Create startup script
RUN echo "\
    /usr/bin/env > /var/www/html/containerenv\n\
    if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then cp /var/www/html/.env.example /var/www/html/.env && chown www-data:www-data /var/www/html/.env; fi\n\
    if [ -f /var/www/html/.env ] && ! grep -q '^APP_KEY=base64:' /var/www/html/.env; then su -c \"cd /var/www/html && php artisan key:generate --force --no-interaction\" -s /bin/bash www-data; fi\n\
    echo \"Starting services...\"\n\
    if [[ -z \${ENABLE_XDEBUG} ]]; then phpdismod xdebug; fi\n\
    service php8.5-fpm start\n\
    service redis-server start\n\
    nginx -g \"daemon off;\" &\n\
    /healthcheck.sh &\n\
    su -c \"cd /var/www/html && php artisan migrate --force\" -s /bin/bash www-data\n\
    if [ \"\${RELEASE_TEXT}\" != \"localdev\" ]; then su -c \"cd /var/www/html && php artisan route:cache\" -s /bin/bash www-data; fi\n\
    if [ \"\${RELEASE_TEXT}\" != \"localdev\" ]; then su -c \"cd /var/www/html && php artisan view:cache\" -s /bin/bash www-data; fi\n\
    if [ \"\${RELEASE_TEXT}\" != \"localdev\" ]; then su -c \"cd /var/www/html && php artisan config:cache\" -s /bin/bash www-data; fi\n\
    if [[ -n \${ENABLE_VITE} ]]; then cp -R /root/.nvm /var/www/.nvm && chown -R www-data:www-data /var/www/.nvm && su -c \"export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\$(readlink -e /usr/bin/versions/node/*/bin) && cd /var/www/html && npm run dev&\" -s /bin/bash www-data;\nelse\nrm -Rf /var/www/html/resources/js && rm -Rf /var/www/html/resources/css && rm -Rf /var/www/html/resources/fonts && rm -Rf /var/www/html/resources/scss && rm -Rf /var/www/html/vite.config.js && rm -Rf /var/www/html/package.json && rm -Rf /var/www/html/package-lock.json && rm -Rf /var/www/html/node_modules;\nfi\n\
    service cron start\n\
    echo \"Ready.\"\n\
    tail -s 1 /var/log/nginx/*.log -f\n\
    " > /start.sh

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure XDebug
RUN echo "\
    zend_extension=xdebug.so\n\
    xdebug.mode=develop,coverage,debug,profile\n\
    xdebug.start_with_request=yes\n\
    xdebug.client_host=host.docker.internal\n\
    xdebug.client_port=9003\n\
    xdebug.remote_enable=1\n\
    xdebug.remote_autostart=1\n\
    xdebug.discover_client_host=1\n\
    xdebug.idekey=customer\n\
    xdebug.log=/dev/stdout\n\
    xdebug.log_level=0\n\
    " > /etc/php/8.5/mods-available/xdebug.ini

# Configure redis
RUN echo "\
maxmemory 100mb\n\
maxmemory-policy allkeys-lru\n\
" >> /etc/redis/redis.conf

# Create cronjob
RUN echo "* * * * *  www-data cd /var/www/html && /usr/bin/php artisan schedule:run > /dev/null 2>&1\n" >> /etc/crontab

# Clear html directory
RUN rm -rf /var/www/html/*

# Set ownership of directory
RUN chown www-data:www-data /var/www/html

# Set working directory
WORKDIR /var/www/html

# Copy files
COPY --chown=www-data:www-data . /var/www/html

# Install composer dependencies
RUN su -c "cd /var/www/html && composer install" -s /bin/bash www-data

# Create release file
RUN su -c "cd /var/www/html && echo -n ${RELEASE_TEXT} > RELEASE" -s /bin/bash www-data

# Install Node JS (and NPM)
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.5/install.sh | bash && . "$HOME/.nvm/nvm.sh" && nvm install 24 && npm install && npm run build

# Change ownership
RUN chown -R www-data:www-data /var/www/html/public
RUN chown -R www-data:www-data /var/www/html/node_modules

# Remove readme files
RUN rm -Rf /var/www/html/*.md

# Configure healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD curl -f http://localhost:80/login || exit 1

# Expose web port and vite
EXPOSE 80
EXPOSE 5173

# Start commands
CMD ["bash", "/start.sh"]
