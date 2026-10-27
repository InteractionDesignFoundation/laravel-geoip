<?php declare(strict_types=1);

use IxDFCodingStandard\PhpCsFixer\Config;

/** @see https://mlocati.github.io/php-cs-fixer-configurator */
return Config::create(__DIR__, ruleOverrides: [
    'final_class' => false,
    'final_public_method_for_abstract_class' => false,
    'mb_str_functions' => false,
    // The package supports PHP 8.3: keep parentheses around `new` expressions (`new Foo()->bar()` is PHP 8.4+).
    'new_expression_parentheses' => ['use_parentheses' => true],
    // Adding `: void` to protected methods of non-final classes would break subclasses that override them.
    'void_return' => false,
]);
