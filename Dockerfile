FROM php:8-apache

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN a2enmod rewrite headers
RUN docker-php-ext-install pdo_mysql

RUN printf '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/ezxss.conf \
    && a2enconf ezxss

RUN { \
      echo "upload_max_filesize=150M"; \
      echo "post_max_size=150M"; \
      echo "memory_limit=256M"; \
      echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/ezxss.ini

RUN apt-get update && \
    apt-get install -y certbot python3-certbot-apache && \
    rm -rf /var/lib/apt/lists/*

RUN a2enmod ssl && a2ensite default-ssl

RUN openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/ssl/private/ssl-cert-snakeoil.key \
    -out /etc/ssl/certs/ssl-cert-snakeoil.pem \
    -subj "/C=US/ST=State/L=City/O=Organization/OU=Unit/CN=localhost"

COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY . /var/www/html

RUN chown www-data:www-data /var/www/html/assets/img && chmod 755 /var/www/html/assets/img

ENTRYPOINT ["docker-entrypoint.sh"]