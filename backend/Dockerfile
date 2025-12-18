# Sử dụng PHP kèm Apache web server
FROM php:8.1-apache

# Cài đặt thư viện để kết nối MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy toàn bộ code trong folder backend vào web server
COPY . /var/www/html/

# Mở cổng 80 để truy cập
EXPOSE 80