<?php

declare(strict_types=1);

// Style du code PHP : règles Symfony, imports triés, syntaxe PHP 8.3.
// Installation : composer require --dev friendsofphp/php-cs-fixer
// Vérification : vendor/bin/php-cs-fixer fix --dry-run --diff   ·   Correction : vendor/bin/php-cs-fixer fix
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude('Migrations');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PHP83Migration' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder);
