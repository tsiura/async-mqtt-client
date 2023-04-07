psalm:
	php ./vendor/bin/psalm --threads=1 --show-info=false

phpstan:
	php ./vendor/bin/phpstan analyze --no-interaction --ansi

phpcs:
	php ./vendor/bin/phpcs --standard=PSR2 --colors -n ./src/ ./tests/

phpcbf:
	php ./vendor/bin/phpcbf --standard=PSR2 ./src/ ./tests/

test:
	php ./vendor/bin/phpunit --no-coverage ./tests/