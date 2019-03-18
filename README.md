# event-machine-docs
Docs for Event Event Engine

**Please note: Docs are work in progress. They are copied from Event Machine and might contain outdated or wrong information until fully migrated!**

Documentation is [in the docs tree](docs/), and can be compiled using [bookdown](http://bookdown.io) and [Docker](https://www.docker.com/).

```bash
$ docker run --rm -it -v $(pwd):/app prooph/composer:7.2
$ docker run -it --rm -e CSS_BOOTSWATCH=lumen -e CSS_PRISM=ghcolors -v $(pwd):/app sandrokeil/bookdown:develop docs/bookdown.json
$ docker run -it --rm -v $(pwd):/app prooph/php:7.2-cli php docs/front.php
$ docker run -it --rm -p 8080:8080 -v $(pwd):/app php:7.2-cli php -S 0.0.0.0:8080 -t /app/docs/html
```

## Powered by prooph software

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/blob/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Engine is maintained by the [prooph software team](http://prooph-software.de/). The source code of Event Engine
is open sourced along with an API documentation and a [Getting Started Tutorial](#). Prooph software offers commercial support and workshops
for Event Engine as well as for the [prooph components](http://getprooph.org/).

If you are interested in this offer or need project support please [get in touch](http://getprooph.org/#get-in-touch)
