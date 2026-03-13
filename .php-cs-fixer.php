<?php declare(strict_types=1);

use IxDFCodingStandard\PhpCsFixer\Config;

/** @see https://mlocati.github.io/php-cs-fixer-configurator */
return Config::create(__DIR__, ruleOverrides: [
    'final_class' => false,
    'final_public_method_for_abstract_class' => false,
    'mb_str_functions' => false,
]);
