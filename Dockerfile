FROM php:8-apache

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN echo "RemoteIPHeader X-Forwarded-For" >> /etc/apache2/conf-enabled/remoteip.conf
RUN echo "RemoteIPInternalProxy 172.16.0.0/12" >> /etc/apache2/conf-enabled/remoteip.conf
RUN a2enmod rewrite headers remoteip

RUN apt-get update && apt-get install -y msmtp && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

COPY ./msmtprc /etc/msmtprc
RUN chmod 640 /etc/msmtprc
RUN touch /var/log/msmtp.log
RUN chown root:msmtp /etc/msmtprc
RUN chown root:msmtp /var/log/msmtp.log
RUN echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/php-sendmail.ini

COPY . /var/www/html

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["apache2-foreground"]
