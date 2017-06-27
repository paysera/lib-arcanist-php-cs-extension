<?php

final class PhpCsFixerLintEngine extends ArcanistLintEngine
{
    const PHP_CS = '.php_cs';

    public function buildLinters()
    {
        $linters = [];
        $paths = $this->getPaths();
        $properPaths = [];

        foreach ($paths as $key => $path) {
            if (!Filesystem::pathExists($this->getFilePathOnDisk($path))) {
                unset($paths[$key]);
            }

            if (preg_match('/\.(php)$/', $path)) {
                $properPaths[] = $path;
            }
        }

        /** @var ArcanistConfigurationManager $config */
        $config = $this->getConfigurationManager()->getConfigFromAnySource('lint.php_cs_file');
        $linter = new PhpCsFixerLinter($config ? $config : self::PHP_CS);
        $linter->setPaths($properPaths);
        $linters[] = $linter;

        return $linters;
    }
}
