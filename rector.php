<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_83)
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_83,
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::TYPE_DECLARATION,
        \Rector\Set\ValueObject\SetList::PRIVATIZATION,
        \Rector\PHPUnit\Set\PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ])
    ->withRules([
        \Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class,
        \Rector\CodingStyle\Rector\ArrowFunction\StaticArrowFunctionRector::class,
        \Rector\CodingStyle\Rector\Closure\StaticClosureRector::class,

    ])
    // Adding a return type to this protected method of a non-final class would break subclasses that override it.
    ->withSkip([
        \Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstAndStrictReturnsRector::class => [
            __DIR__.'/src/Services/MaxMindDatabase.php',
        ],
    ])
    ->withParallel();
