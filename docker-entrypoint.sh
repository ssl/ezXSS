#!/bin/bash

# Start Apache in the background
if ! pidof apache2 > /dev/null; then
    apache2ctl start
    sleep 5
fi

# Check if we should install the certificate
if [ "$INSTALL_CERTIFICATE" = "true" ]; then
    if [ ! -e "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
        if ! grep -q "ServerName $DOMAIN" /etc/apache2/apache2.conf; then
            echo "ServerName $DOMAIN" >> /etc/apache2/apache2.conf
        fi

        # Attempt to obtain SSL certificate
        certbot certonly --non-interactive --agree-tos --email webmaster@$DOMAIN --webroot --webroot-path=/var/www/html -d $DOMAIN
        
        # Check if Certbot succeeded and update Apache SSL configuration
        if [ -e "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ] && [ -e "/etc/letsencrypt/live/$DOMAIN/privkey.pem" ]; then
            # Update the SSLCertificateFile and SSLCertificateKeyFile directives with the correct paths
            sed -i "s|SSLCertificateFile .*|SSLCertificateFile /etc/letsencrypt/live/$DOMAIN/fullchain.pem|g" /etc/apache2/sites-available/default-ssl.conf
            sed -i "s|SSLCertificateKeyFile .*|SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN/privkey.pem|g" /etc/apache2/sites-available/default-ssl.conf
            
            # Reload Apache to apply SSL configuration
            apache2ctl graceful
        fi
    fi
fi

# Keep Apache running in the foreground to prevent the container from exiting
if ! pidof apache2 > /dev/null; then
    exec apache2ctl -DFOREGROUND
fi
