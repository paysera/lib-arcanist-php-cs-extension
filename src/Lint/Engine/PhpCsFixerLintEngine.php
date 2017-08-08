<?php

final class PhpCsFixerLintEngine extends \ArcanistLintEngine
{
    const PHP_CS_FILE = '.php_cs';
    const SRC_DIRECTORY = 'src/';
    const BINARY_FILE = 'bin/php-cs-fixer';

    private $folderExclusions = [
        'Tests/',
        'Test/',
    ];

    public function buildLinters()
    {
        $paths = $this->getPaths();
        $properPaths = [];

        /** @var \ArcanistConfigurationManager $configManager */
        $configManager = $this->getConfigurationManager();

        $fixerPaths = $configManager->getConfigFromAnySource('lint.php_cs_fixer.fix_paths', [self::SRC_DIRECTORY]);
        $binaryPath = $configManager->getConfigFromAnySource('lint.php_cs_fixer.php_cs_binary', self::BINARY_FILE);

        foreach ($paths as $key => $path) {
            if (!file_exists($this->getFilePathOnDisk($path))) {
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

        $phpCsFile = $configManager->getConfigFromAnySource('lint.php_cs_fixer.php_cs_file', self::PHP_CS_FILE);

        $linterConfiguration = new LinterConfiguration();
        $linterConfiguration
            ->setBinaryFile($binaryPath)
            ->setPhpCsFile($phpCsFile)
            ->setPaths($properPaths)
        ;

        return [new PhpCsFixerLinter($linterConfiguration)];
    }

    /**
     * @param array $paths
     *
     * @return string[]
     */
    private function pregQuotePaths(array $paths)
    {
        foreach ($paths as $key => $path) {
            $paths[$key] = preg_quote($path, '#');
        }

        return $paths;
    }
}
