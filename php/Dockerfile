# OWASP Top 10 Labs 2025 - Nexo
# PHP 8.2 + Apache - deliberadamente vulnerable para fines educativos

FROM php:8.2-apache

# Extensiones necesarias para MariaDB
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite para .htaccess
RUN a2enmod rewrite headers

# Apache config - VULNERABLE INTENCIONALMENTE
# - ServerTokens Full: expone version de Apache
# - ServerSignature On: firma en paginas de error
# - Options +Indexes: directory listing en /backups/
# Esto es para el lab A02 (Security Misconfiguration)
RUN echo '\n\
ServerTokens Full\n\
ServerSignature On\n\
' >> /etc/apache2/apache2.conf

# Habilitar .htaccess en DocumentRoot
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# PHP config - VULNERABLE INTENCIONALMENTE
# display_errors = On para lab A02 (stack trace con creds)
# expose_php = On para exponer version de PHP
RUN echo '\n\
display_errors = On\n\
display_startup_errors = On\n\
error_reporting = E_ALL\n\
expose_php = On\n\
log_errors = On\n\
error_log = /var/log/nexo/php_errors.log\n\
' > /usr/local/etc/php/conf.d/nexo-vulnerable.ini

# Crear directorios necesarios
RUN mkdir -p /var/log/nexo && \
    chown www-data:www-data /var/log/nexo

# DocumentRoot
WORKDIR /var/www/html

# Puerto 80 - Apache escucha aca, Nginx hace proxy
EXPOSE 80

# Health check basico
HEALTHCHECK --interval=30s --timeout=3s \
  CMD curl -f http://localhost/ || exit 1
