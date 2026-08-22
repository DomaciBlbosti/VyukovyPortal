# Obraz nese jen toolchain (PHP 8.3 + Apache + git). Vlastní kód se za běhu
# naklonuje z Gitu do volume /var/www/html a aktualizuje přes `git pull`
# při každém restartu kontejneru (self-update jako Kuchařka).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl ca-certificates default-mysql-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers expires

# .htaccess aplikace musí fungovat (bezpečnostní hlavičky, ochrana config/)
RUN { \
      echo 'ServerName typemaster'; \
      echo '<Directory /var/www/html>'; \
      echo '    AllowOverride All'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/typemaster.conf \
    && a2enconf typemaster

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV REPO_URL=https://github.com/DomaciBlbosti/VyukovyPortal.git \
    REPO_BRANCH=main \
    DB_HOST=db \
    DB_PORT=3306 \
    DB_NAME=typemaster \
    DB_USER=typemaster

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
