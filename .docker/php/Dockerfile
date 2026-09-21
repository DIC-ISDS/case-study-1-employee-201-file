FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

# Create group and user
RUN addgroup --gid ${GID} wwwgroup && \
    adduser --uid ${UID} --ingroup wwwgroup --shell /bin/sh --disabled-password --home /home/wwwuser wwwuser && \
    mkdir -p /var/www && \
    chown -R wwwuser:wwwgroup /var/www

# mix
RUN apt-get update \
    && apt-get install -y build-essential zlib1g-dev default-mysql-client curl gnupg procps vim git unzip libzip-dev libpq-dev supervisor \
    && docker-php-ext-install sockets zip pdo_mysql pdo_pgsql pgsql exif

# intl
RUN apt-get install -y libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

# gd
RUN apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
    docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
    docker-php-ext-install gd

# Node.js, NPM, Yarn
RUN curl -sL https://deb.nodesource.com/setup_22.x | bash -
RUN apt-get install -y nodejs
RUN npm install yarn -g

# Composer, pulled from the official image instead of piping
# getcomposer.org through the build: same binary, no dependency on that host
# being reachable while building.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /composer
ENV PATH $PATH:/composer/vendor/bin
RUN composer config --global process-timeout 3600

# Configs are copied last: editing one rebuilds only these cheap final layers
# instead of re-running the apt/pecl installs above.
COPY php.ini /usr/local/etc/php/
COPY docker.conf /usr/local/etc/php-fpm.d/docker.conf
COPY .bashrc /home/wwwuser/
RUN chown wwwuser:wwwgroup /home/wwwuser/.bashrc
COPY supervisord.conf /etc/supervisord.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 5173
WORKDIR /var/www

#entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
