<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;

$config = RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withCache(__DIR__ . '/.tools/cache/rector')
    ->withParallel(120, 16, 16)
    ->withSets([
        SetList::PHP_74,
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PHP_82,
        SetList::TYPE_DECLARATION,

        // PHPUnit
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,

        // Safe
        'vendor/thecodingmachine/safe/rector-migrate.php'
    ])
    ->withImportNames(true, true, true, true)
    ->withSkip([
        Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class,
        Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector::class,
        Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector::class,
        Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector::class,
    ]);

if (file_exists(__DIR__ . '/rector.override.php')) {
    $override = include(__DIR__ . '/rector.override.php');

    if (!is_callable($override)) {
        throw new RuntimeException('The rector.override.php file must return a callable.');
    }

    $config = $override($config);

    if (!$config instanceof RectorConfigBuilder) {
        throw new RuntimeException(
            'The rector.override.php file must return a callable that returns RectorConfigBuilder.'
        );
    }
}

return $config;
