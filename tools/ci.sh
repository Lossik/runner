#!/usr/bin/env bash
# tools/ci.sh - CI script for lossik/runner. Runs in the repository root inside a php:<patch>-cli container:
#   docker run --rm -v "$PWD:/app" -w /app php:8.2.35-cli bash tools/ci.sh
# Step 1 is the only guard against the unpatched PHP2Shell (composer.json requires just "php": "^8.2", so Composer would accept even 8.2.0).
# DEPS=lowest installs the lowest allowed nette/* versions instead of the highest (the dev tools stay at the latest).
set -euo pipefail

# 1) PHP_VERSION against the minimum of its branch; anything outside 8.2-8.5 is an error
php -r '
$min = ["8.2" => "8.2.35", "8.3" => "8.3.36", "8.4" => "8.4.27", "8.5" => "8.5.12"];
$branch = PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;
if (!isset($min[$branch])) { fwrite(STDERR, "PHP $branch is not supported (8.2-8.5 only)\n"); exit(1); }
if (version_compare(PHP_VERSION, $min[$branch], "<")) { fwrite(STDERR, "PHP " . PHP_VERSION . " is vulnerable to PHP2Shell, need >= " . $min[$branch] . "\n"); exit(1); }
echo "PHP " . PHP_VERSION . " ok (>= " . $min[$branch] . ")\n";'

# 2) tools in the container: git + unzip (Composer dist), Composer phar with a verified SHA-384
if ! command -v unzip >/dev/null || ! command -v git >/dev/null; then
	apt-get update -qq && apt-get install -y -qq git unzip >/dev/null
fi
if ! command -v composer >/dev/null; then
	expected="$(curl -fsS https://composer.github.io/installer.sig)"
	curl -fsS https://getcomposer.org/installer -o /tmp/composer-setup.php
	php -r 'exit(hash_file("sha384", "/tmp/composer-setup.php") === $argv[1] ? 0 : 1);' "$expected"
	php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer --version="${COMPOSER_VERSION:-2.10.2}"
fi
export COMPOSER_ALLOW_SUPERUSER=1

composer validate --strict
composer install --no-interaction --no-progress --prefer-dist
if [ "${DEPS:-highest}" = lowest ]; then
	# Only the runtime dependencies drop to the lowest version. nette/finder 2.5 (pulled in by di 3.0.x) is abandoned, so the audit only reports it.
	composer update 'nette/*' --prefer-lowest --prefer-stable -W --no-interaction --no-progress
	composer audit --abandoned=report
else
	composer audit
fi
composer check-platform-reqs
composer dump-autoload --strict-psr -o -q
vendor/bin/phpcs
if [ "${DEPS:-highest}" != lowest ]; then vendor/bin/phpstan analyse --no-progress; fi
vendor/bin/phpunit
