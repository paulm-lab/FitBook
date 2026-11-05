<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

/*
 * Clés JWT des tests : générées au premier lancement dans var/jwt-test/ (jamais versionnées),
 * pour que la suite tourne sur un clone neuf sans configuration manuelle.
 */
(static function (): void {
    $dir = dirname(__DIR__).'/var/jwt-test';
    if (is_file($dir.'/private.pem') && is_file($dir.'/public.pem')) {
        return;
    }

    // Sous Windows, OpenSSL a besoin d'un openssl.cnf : celui livré avec PHP, s'il existe.
    $config = [];
    $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($cnf)) {
        $config['config'] = $cnf;
    }

    $key = openssl_pkey_new($config + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($key === false || !openssl_pkey_export($key, $private, $_SERVER['JWT_PASSPHRASE'] ?? '', $config)) {
        throw new RuntimeException('Impossible de générer les clés JWT de test : '.openssl_error_string());
    }

    is_dir($dir) || mkdir($dir, 0775, true);
    file_put_contents($dir.'/private.pem', $private);
    file_put_contents($dir.'/public.pem', openssl_pkey_get_details($key)['key']);
})();
