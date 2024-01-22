FROM php:8-apache

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN echo "RemoteIPHeader X-Forwarded-For" >> /etc/apache2/conf-enabled/remoteip.conf
RUN echo "RemoteIPInternalProxy 172.16.0.0/12" >> /etc/apache2/conf-enabled/remoteip.conf
RUN a2enmod rewrite headers remoteip ssl

RUN docker-php-ext-install pdo_mysql

RUN apt-get update && \
    apt-get install -y certbot python3-certbot-apache && \
    rm -rf /var/lib/apt/lists/*


RUN a2enmod ssl

RUN certbot certonly --non-interactive --agree-tos --email webmaster@${DOMAIN} --webroot --webroot-path=/data/letsencrypt -d ${DOMAIN}

RUN echo "<VirtualHost *:80>\n\
    ServerAdmin webmaster@${DOMAIN}\n\
    DocumentRoot /var/www/html\n\
    Alias /.well-known/acme-challenge /var/www/letsencrypt/data/.well-known/acme-challenge
   
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/000-no-ssl-default.conf

RUN a2ensite no-ssl-default

RUN echo "<VirtualHost *:443>\n\
    ServerAdmin webmaster@${DOMAIN}\n\
    DocumentRoot /var/www/html\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/letsencrypt/live/${DOMAIN}/fullchain.pem\n\
    SSLCertificateKeyFile /etc/letsencrypt/live/${DOMAIN}/privkey.pem\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/default-ssl.conf

RUN a2ensite default-ssl

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

