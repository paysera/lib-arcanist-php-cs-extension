<?php

namespace Paysera\Composer;

use Composer\Config;
use Composer\Script\Event;

class ArcConfigParser
{
    const LOAD = 'vendor/paysera/lib-arcanist-php-cs-extension/src/';
    const LINT_ENGINE = 'PhpCsFixerLintEngine';
    const CONFIG_FILE = '.arcconfig';
    const LINT_FILE = '.arclint';

    public static function parseArcConfig(Event $event)
    {
        $phpCsBinary = $event->getComposer()->getConfig()
                ->get('bin-dir', Config::RELATIVE_PATHS) . '/php-cs-fixer';

        if (!file_exists($phpCsBinary)) {
            $phpCsBinary = 'php-cs-fixer';
        }

        $parsedConfig = self::parseAndPrepareArcConfig($phpCsBinary);

        if (!file_exists(self::LINT_FILE)) {
            self::createOrUpdateArcLint($parsedConfig);
        }

        $parsedConfig = self::cleanArcConfig($parsedConfig);

        file_put_contents(
            self::CONFIG_FILE,
            stripslashes(json_encode($parsedConfig, JSON_PRETTY_PRINT))
        );
    }

    private static function parseAndPrepareArcConfig($phpCsBinary)
    {
        $arcConfig = [];
        if (file_exists(self::CONFIG_FILE)) {
            $arcConfig = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }

        if (!isset($arcConfig['load'])) {
            $arcConfig['load'] = [self::LOAD];
        } elseif (!in_array(self::LOAD, $arcConfig['load'], true)) {
            $arcConfig['load'][] = self::LOAD;
        }

        if (!isset($arcConfig['lint.engine'])) {
            $arcConfig['lint.engine'] = self::LINT_ENGINE;
        }

        if (!isset($arcConfig['lint.php_cs_fixer.fix_paths'])) {
            $arcConfig['lint.php_cs_fixer.fix_paths'] = [\LinterConfiguration::SRC_DIRECTORY];
        }

        if (!isset($arcConfig['lint.php_cs_fixer.php_cs_binary'])) {
            $arcConfig['lint.php_cs_fixer.php_cs_binary'] = $phpCsBinary;
        }

        if (!isset($arcConfig['lint.php_cs_fixer.php_cs_file'])) {
            $arcConfig['lint.php_cs_fixer.php_cs_file'] = \LinterConfiguration::PHP_CS_FILE;
        }

        if (!isset($arcConfig['lint.php_cs_fixer.unified_diff_format'])) {
            $arcConfig['lint.php_cs_fixer.unified_diff_format'] = false;
        }

        return $arcConfig;
    }

    private static function createOrUpdateArcLint($parsedConfig)
    {
        $arcLint = [];
        if (file_exists(self::LINT_FILE)) {
            $arcLint = json_decode(file_get_contents(self::LINT_FILE), true);
        }

        if (!isset($arcLint['linters']['php-cs-fixer']['type'])) {
            $arcLint['linters']['php-cs-fixer']['type'] = 'php-cs-fixer';
        }
        if (!isset($arcLint['linters']['php-cs-fixer']['bin'])) {
            $arcLint['linters']['php-cs-fixer']['bin'] = $parsedConfig['lint.php_cs_fixer.php_cs_binary'];
        }
        if (!isset($arcLint['linters']['php-cs-fixer']['fix_paths'])) {
            $arcLint['linters']['php-cs-fixer']['fix_paths'] = $parsedConfig['lint.php_cs_fixer.fix_paths'];
        }
        if (!isset($arcLint['linters']['php-cs-fixer']['php_cs_file'])) {
            $arcLint['linters']['php-cs-fixer']['php_cs_file'] = $parsedConfig['lint.php_cs_fixer.php_cs_file'];
        }
        if (!isset($arcLint['linters']['php-cs-fixer']['unified_diff_format'])) {
            $arcLint['linters']['php-cs-fixer']['unified_diff_format'] = $parsedConfig['lint.php_cs_fixer.unified_diff_format'];
        }

        file_put_contents(self::LINT_FILE, stripslashes(json_encode($arcLint, JSON_PRETTY_PRINT)));
    }

    private static function cleanArcConfig($parsedConfig)
    {
        unset($parsedConfig['lint.php_cs_fixer.fix_paths']);
        unset($parsedConfig['lint.php_cs_fixer.php_cs_binary']);
        unset($parsedConfig['lint.php_cs_fixer.php_cs_file']);
        unset($parsedConfig['lint.php_cs_fixer.unified_diff_format']);

        if (isset($parsedConfig['lint.engine']) && $parsedConfig['lint.engine'] === self::LINT_ENGINE) {
            unset($parsedConfig['lint.engine']);
        }

        return $parsedConfig;
    }
}
