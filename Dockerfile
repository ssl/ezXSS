FROM php:8-apache

# PHP and Apache configuration
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN echo "RemoteIPHeader X-Forwarded-For" >> /etc/apache2/conf-enabled/remoteip.conf
RUN echo "RemoteIPInternalProxy 172.16.0.0/12" >> /etc/apache2/conf-enabled/remoteip.conf
RUN a2enmod rewrite headers remoteip
RUN docker-php-ext-install pdo_mysql

# Install necessary packages
RUN apt-get update && \
    apt-get install -y certbot python3-certbot-apache msmtp && \
    rm -rf /var/lib/apt/lists/*

# Configure Apache and SSL
RUN a2enmod ssl
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy the application files
COPY . /var/www/html

# Mail alerts service configuring
ARG USE_MAIL_ALERTS
RUN if [ "$USE_MAIL_ALERTS" = "true" ]; then \
        cp ./msmtprc /etc/msmtprc; \
        chmod 640 /etc/msmtprc; \
        touch /var/log/msmtp.log; \
        chown root:www-data /etc/msmtprc; \
        chown root:www-data /var/log/msmtp.log; \
        echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/php-sendmail.ini; \
    fi

RUN chmod 777 /var/www/html/assets/img

# Set the entrypoint script to initialize everything
ENTRYPOINT ["docker-entrypoint.sh"]
