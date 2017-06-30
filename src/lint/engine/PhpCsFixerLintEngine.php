<?php

final class PhpCsFixerLintEngine extends ArcanistLintEngine
{
    const PHP_CS = '.php_cs';
    const SRC_DIRECTORY = 'src/';
    private $folderExclusions = [
        'Tests/',
        'Test/',
    ];

    private static function getDefaultFixerDirectories()
    {
        return [
            self::SRC_DIRECTORY,
        ];
    }

    public function buildLinters()
    {
        $linters = [];
        $paths = $this->getPaths();
        $properPaths = [];

        $fixerPaths = $this->getConfigurationManager()->getConfigFromAnySource('lint.fixer_paths');
        if (empty($fixerPaths)) {
            $fixerPaths = self::getDefaultFixerDirectories();
        }

        foreach ($paths as $key => $path) {
            if (!Filesystem::pathExists($this->getFilePathOnDisk($path))) {
                unset($paths[$key]);
            }

            if (
                preg_match('#' . implode('|', $this->pregQuotePaths($fixerPaths)) . '#i', $path)
                && preg_match('#\.(php)$#', $path)
                && preg_match('#' . implode('|', $this->pregQuotePaths($this->folderExclusions)) . '#i', $path) === 0
            ) {
                $properPaths[] = $path;
            }
        }

        /** @var ArcanistConfigurationManager $phpCsFile */
        $phpCsFile = $this->getConfigurationManager()->getConfigFromAnySource('lint.php_cs_file');
        $linter = new PhpCsFixerLinter($phpCsFile ? $phpCsFile : self::PHP_CS);
        $linter->setPaths($properPaths);
        $linters[] = $linter;

        return $linters;
    }

    /**
     * @param array $paths
     * @return string[]
     */
    private function pregQuotePaths($paths)
    {
        foreach ($paths as &$path) {
            $path = preg_quote($path, '#');
        }
        return $paths;
    }
}
