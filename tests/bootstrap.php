<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Les limiteurs de débit (config/packages/rate_limiter.yaml) gardent leur compteur dans le cache : on le vide au début de chaque
// exécution des tests, sinon deux exécutions rapprochées s'additionnent (ex. 5 envois de plan par e-mail toutes les 10 minutes).
if ('test' === ($_SERVER['APP_ENV'] ?? null)) {
    (new Symfony\Component\Filesystem\Filesystem())->remove([dirname(__DIR__).'/var/cache/test/pools', dirname(__DIR__).'/var/share/test/pools']);
}
