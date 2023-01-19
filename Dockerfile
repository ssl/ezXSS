FROM php:8-apache as partial

# Use production PHP settings
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
# Load extra Apache modules
RUN a2enmod rewrite headers
# Installs sendmail
RUN apt-get update && apt-get install -y msmtp && rm -rf /var/lib/apt/lists/*
# Installs php modules
RUN docker-php-ext-install pdo_mysql
# config msmtp https://owendavies.net/articles/setting-up-msmtp/
COPY ./msmtprc /etc/msmtprc
RUN chmod 600 /etc/msmtprc
RUN touch /var/log/msmtp.log
RUN chown www-data:www-data /etc/msmtprc
RUN chown www-data:www-data /var/log/msmtp.log

# Fix screenshot folder permissions
RUN mkdir /var/www/html/assets/ && mkdir /var/www/html/assets/img
RUN chmod 777 /var/www/html/assets/img

# config php.ini
RUN echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/php-sendmail.ini

FROM partial
ENV PUID=2000
ENV PGID=2000
COPY . /var/www/html/
RUN find . -type f -exec sed -i 's/\r$//' {} \; && \
    chmod +x /init.sh && \
    sed -i -e 's/\r$//' /init.sh

ENTRYPOINT ["/bin/bash"]
CMD ["/init.sh"]
