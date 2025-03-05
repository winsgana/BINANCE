# Usa la imagen oficial de PHP con Apache
FROM php:8.0-apache

# Copia todos los archivos del proyecto al directorio de Apache
COPY . /var/www/html/

# Asigna los permisos adecuados a los archivos
RUN chown -R www-data:www-data /var/www/html

# Expone el puerto 80 para acceder a la aplicación
EXPOSE 80

# Arranca Apache en primer plano
CMD ["apache2-foreground"]
