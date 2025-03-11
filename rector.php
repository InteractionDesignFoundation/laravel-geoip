<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_81,
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::TYPE_DECLARATION,
        \Rector\Set\ValueObject\SetList::PRIVATIZATION,
        \Rector\PHPUnit\Set\PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_110,
    ])
    ->withRules([
        \Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class,
        \Rector\CodingStyle\Rector\ArrowFunction\StaticArrowFunctionRector::class,
        \Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector::class,
        \Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class,
        \Rector\CodingStyle\Rector\Closure\StaticClosureRector::class,

    ])
    ->withParallel();
