FROM phpswoole/swoole:5.0.1-php8.1

COPY ./www/ /var/www/

WORKDIR /var/www/

ENTRYPOINT ["php", "/var/www/server.php", "start"]
