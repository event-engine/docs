docker run -it --rm -e CSS_BOOTSWATCH=lumen -e CSS_PRISM=ghcolors -v $(pwd):/app sandrokeil/bookdown:develop docs/bookdown.json
docker run -it --rm -v $(pwd):/app prooph/php:7.2-cli php docs/front.php
docker run -it --rm -p 8080:8080 -v $(pwd):/app php:7.2-cli php -S 0.0.0.0:8080 -t /app/docs/html
