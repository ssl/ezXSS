FROM php:8-apache

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN echo "RemoteIPHeader X-Forwarded-For" >> /etc/apache2/conf-enabled/remoteip.conf
RUN echo "RemoteIPInternalProxy 172.16.0.0/12" >> /etc/apache2/conf-enabled/remoteip.conf
RUN a2enmod rewrite headers remoteip

RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html

# Mail alerts service configuring
ARG USE_MAIL_ALERTS
RUN if [ "$USE_MAIL_ALERTS" = "true" ]; then \
        set -e; \
        apt-get update && apt-get install -y msmtp && rm -rf /var/lib/apt/lists/*;  \
        cp ./msmtprc /etc/msmtprc; \
        chmod 640 /etc/msmtprc; \
        touch /var/log/msmtp.log; \
        chown root:www-data /etc/msmtprc; \
        chown root:www-data /var/log/msmtp.log; \
        echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/php-sendmail.ini; \
        set +e; \
    fi


RUN chmod 777 /var/www/html/assets/img

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["apache2-foreground"]

