<?php

declare(strict_types=1);

// PHPUnit bootstrap for NextLedger.
// We don't load Nextcloud's full server stack here — these are pure unit tests
// that exercise PHP services using only the entities + the horstoeko/zugferd
// library. If you add tests that need OCP, run them inside Nextcloud via
//   docker compose exec --user www-data nextcloud \
//     php /var/www/html/custom_apps/nextledger/vendor/bin/phpunit

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run 'composer install' inside apps/nextledger first.\n");
    exit(1);
}
require $autoload;

// Provide the small set of OCP entity stubs we need so that Db\Entity
// classes can be instantiated outside a Nextcloud server.
if (!class_exists(\OCP\AppFramework\Db\Entity::class, false)) {
    require __DIR__ . '/Stubs/OcpEntity.php';
}
