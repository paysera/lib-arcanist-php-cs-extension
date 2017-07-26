<?php

namespace Paysera\Arcanist;

use Symfony\Component\Filesystem\Filesystem;

class ArcConfigParser
{
    const LOAD = 'vendor/paysera/lib-arcanist-php-cs-extension/src/';
    const LINT_ENGINE = 'PhpCsFixerLintEngine';
    const DEFAULT_DIRECTORY = 'src/';

    public static function parseArcConfig()
    {
        $fileSystem = new Filesystem();
        $arcConfigFilename = '.arcconfig';

        if ($fileSystem->exists($arcConfigFilename)) {
            $localJsonArray = json_decode(file_get_contents($arcConfigFilename), true);
            if (!isset($localJsonArray['load'])) {
                $localJsonArray['load'] = [self::LOAD];
            }

            if (!isset($localJsonArray['lint.engine'])) {
                $localJsonArray['lint.engine'] = self::LINT_ENGINE;
            }

            if (!isset($localJsonArray['lint.fixer_paths'])) {
                $localJsonArray['lint.fixer_paths'] = [self::DEFAULT_DIRECTORY];
            }
            file_put_contents($arcConfigFilename, stripslashes(json_encode($localJsonArray, JSON_PRETTY_PRINT)));
        } else {
            $localJsonArray['load'] = [self::LOAD];
            $localJsonArray['lint.engine'] = self::LINT_ENGINE;
            $localJsonArray['lint.fixer_paths'] = [self::DEFAULT_DIRECTORY];
        }

        file_put_contents($arcConfigFilename, stripslashes(json_encode($localJsonArray, JSON_PRETTY_PRINT)));
    }
}
