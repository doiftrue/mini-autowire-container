define php_run
	@mkdir -p "$(CURDIR)/tmp"
	@printf '%s\n' 'opcache.enable=1' 'opcache.enable_cli=1' > "$(CURDIR)/tmp/opcache.ini"
	@status=0; \
	docker run --rm $(1) --name LITEWIRE_DI__php -w /app \
		--user "$$(id -u):$$(id -g)" \
		-v "$(CURDIR):/app" \
		-v "$(CURDIR)/tmp/opcache.ini:/usr/local/etc/php/conf.d/opcache.ini:ro" \
		chialab/php-pcov:8.5 sh -c "$2" || status=$$?; \
	exit $$status
endef

php.connect:
	$(call php_run, -it, sh)

composer:
	$(call php_run,, composer  $(filter-out $@,$(MAKECMDGOALS)))
composer.install:
	$(call php_run,, composer install)
composer.update:
	$(call php_run,, composer update)

phpunit:
	$(call php_run, -e WP_LINE="$(WP_LINE)", composer run phpunit -- --colors=always)

coverage:
	$(call php_run,, composer run coverage -- --colors=always --coverage-html tmp/litewire-coverage)

phpstan:
	$(call php_run,, composer run phpstan -- --memory-limit=1G)

benchmark:
	$(call php_run,, composer install --working-dir=benchmarks --no-interaction --prefer-dist --no-progress && composer run --working-dir=benchmarks benchmark)

# make php.run code='echo "Hello World!\n";'
php.run:
	@if [ -z "$(strip $(value code))" ]; then \
		echo 'Use: make php.run code='\''echo "Hello World!"'\'''; \
		exit 1; \
	fi
	$(file >tmp/.phprun.php,<?php)
	$(file >>tmp/.phprun.php,$(value code))
	@status=0; \
	$(call php_run, , php tmp/.phprun.php) || status=$$?; \
	rm -f tmp/.phprun.php; \
	exit $$status
