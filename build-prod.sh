docker run -it --rm -e CSS_BOOTSWATCH=lumen -e CSS_PRISM=ghcolors -v $(pwd):/app sandrokeil/bookdown:develop docs/bookdown.prod.json
docker run -it --rm -v $(pwd):/app prooph/php:7.2-cli php docs/front.php
