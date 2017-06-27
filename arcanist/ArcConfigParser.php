<?php

namespace Paysera\Arcanist;

use Symfony\Component\Filesystem\Filesystem;

class ArcConfigParser
{
    const LOAD = 'vendor/paysera/lib-arcanist-php-cs-extension/src/';
    const LINT_ENGINE = 'PhpCsFixerLintEngine';

    public static function parseArcConfig()
    {
        $fileSystem = new Filesystem();
        $arcConfigFilename = '.arcconfig';

        if ($fileSystem->exists($arcConfigFilename)) {
            $localJsonArray = json_decode(file_get_contents($arcConfigFilename), true);
            if (!isset($localJsonArray['load']) && !isset($localJsonArray['lint.engine'])) {
                $localJsonArray['load'] = [self::LOAD];
                $localJsonArray['lint.engine'] = self::LINT_ENGINE;
                file_put_contents($arcConfigFilename, stripslashes(json_encode($localJsonArray, JSON_PRETTY_PRINT)));
            }
        }
    }
}
