#!/bin/bash

echo "Starting ezXSS with Apache in the background..."
# Start Apache in the background to allow for certificate validation
apache2ctl start || { echo "Failed to start Apache"; exit 1; }

# Short delay to ensure Apache starts properly
sleep 5

echo "Checking and obtaining SSL certificate if necessary..."
# Function to check and obtain a certificate if needed
obtain_certificate() {
    if [ "$INSTALL_CERTIFICATE" = "true" ]; then
        if [ ! -e "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
            echo "Setting ServerName $DOMAIN in Apache configuration..."
            if ! grep -q "ServerName $DOMAIN" /etc/apache2/apache2.conf; then
                echo "ServerName $DOMAIN" >> /etc/apache2/apache2.conf
            fi
            
            echo "Attempting to obtain SSL certificate for $DOMAIN..."
            certbot certonly --non-interactive --agree-tos --email webmaster@$DOMAIN --webroot --webroot-path=/var/www/html -d $DOMAIN
            
            if [ $? -eq 0 ] && [ -e "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
                echo "SSL certificate obtained successfully. Updating Apache SSL configuration..."
                sed -i "s|SSLCertificateFile .*|SSLCertificateFile /etc/letsencrypt/live/$DOMAIN/fullchain.pem|g" /etc/apache2/sites-available/default-ssl.conf
                sed -i "s|SSLCertificateKeyFile .*|SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN/privkey.pem|g" /etc/apache2/sites-available/default-ssl.conf
                
                if [ ! -e "/etc/apache2/sites-enabled/default-ssl.conf" ]; then
                    echo "Enabling SSL site configuration..."
                    a2ensite default-ssl.conf && apache2ctl graceful || { echo "Failed to enable SSL site or reload Apache"; exit 1; }
                else
                    echo "SSL site already enabled. Reloading Apache to apply changes..."
                    apache2ctl graceful || { echo "Failed to reload Apache"; exit 1; }
                fi
            else
                echo "Certbot failed to obtain the certificate. Exiting..."
                exit 1
            fi
        else
            echo "SSL certificate already exists. Skipping certificate acquisition..."
        fi
    else
        echo "SSL certificate installation not requested. Skipping..."
        if [ "$HTTPMODE" = "false" ]; then
            if [ ! -e "/etc/apache2/sites-enabled/default-ssl.conf" ]; then
                echo "Enabling SSL site configuration..."
                a2ensite default-ssl.conf && apache2ctl graceful || { echo "Failed to enable SSL site or reload Apache"; exit 1; }
            fi
        fi
    fi
}

# Obtain the certificate
obtain_certificate

echo "Switching Apache to run in the foreground..."
# Switch Apache to run in the foreground to keep the container running
apache2ctl stop
exec apache2ctl -D FOREGROUND
