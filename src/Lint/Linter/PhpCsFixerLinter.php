<?php

class PhpCsFixerLinter extends \ArcanistExternalLinter
{
    const CURRENT_CODE = "\033[31m";
    const REPLACEMENT_CODE = "\033[32m";
    const UNMODIFIED_CODE = "\033[0m";
    const FIXERS = "\033[36m";

    /**
     * @var array
     */
    private $defaultFlags = [
        '--verbose',
        '--dry-run',
        '--diff',
        '--format=json',
        '--using-cache=no',
    ];

    /**
     * @var LinterConfiguration
     */
    private $configuration;

    /**
     * @param LinterConfiguration $configuration
     */
    public function __construct(LinterConfiguration $configuration = null)
    {
        if ($configuration === null) {
            $configuration = new LinterConfiguration();
        }

        $this->configuration = $configuration;
        $this->setPaths($this->configuration->getPaths());
    }

    public function getLinterName()
    {
        return 'PhpCsFixerLinter';
    }

    public function getInfoName()
    {
        return 'PHP_CS_FIXER Linter';
    }

    public function getInfoURI()
    {
        return 'https://github.com/FriendsOfPHP/PHP-CS-Fixer';
    }

    public function getInfoDescription()
    {
        return pht(
            'The PHP Coding Standards Fixer tool fixes most issues in your code' .
            'when you want to follow the PHP coding standards as defined in' .
            'the PSR-1 and PSR-2 documents and many more.'
        );
    }

    public function getLinterConfigurationName()
    {
        return 'php-cs-fixer';
    }

    public function getMandatoryFlags()
    {
        return ['fix'];
    }

    public function getInstallInstructions()
    {
        return 'By installing this package, you\'ve already installed all dependencies!';
    }

    public function getDefaultFlags()
    {
        return array_merge(
            $this->defaultFlags,
            [sprintf('--config=%s', $this->configuration->getPhpCsFile())]
        );
    }

    public function getDefaultBinary()
    {
        return $this->configuration->getBinaryFile();
    }

    public function getVersion()
    {
        list($stdout) = execx('%C --version', $this->getExecutableCommand());

        $matches = null;
        if (preg_match('#PHP CS Fixer (.*)#i', $stdout, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function parseLinterOutput($path, $err, $stdout, $stderr)
    {
        $json = phutil_json_decode($stdout);
        $messages = [];
        foreach ($json['files'] as $fix) {
            $message = new \ArcanistLintMessage();
            $message->setName($fix['name']);
            $message->setPath($path);

            $diffArray = explode("\n", $fix['diff']);
            foreach ($diffArray as &$diff) {
                if (preg_match('#^-#', $diff)) {
                    $diff = self::CURRENT_CODE . $diff;
                } elseif (preg_match('#^\+#', $diff)) {
                    $diff = self::REPLACEMENT_CODE . $diff;
                } else {
                    $diff = self::UNMODIFIED_CODE . $diff;
                }
            }
            $message->setDescription(
                "Applied fixers: \n" . self::FIXERS . implode("\n", $fix['appliedFixers']) . "\n\n"
                . implode("\n", $diffArray)
            );
            $message->setSeverity(\ArcanistLintSeverity::SEVERITY_WARNING);
            $messages[] = $message;
        }

        return $messages;
    }

    protected function getPathArgumentForLinterFuture($path)
    {
        $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();

        return str_replace($root . '/', '', $path);
    }
}
