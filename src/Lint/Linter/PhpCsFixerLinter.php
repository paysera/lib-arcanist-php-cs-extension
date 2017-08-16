<?php

class PhpCsFixerLinter extends \ArcanistExternalLinter
{
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
     * @var LintMessageBuilder
     */
    private $lintMessageBuilder;

    /**
     * @param LinterConfiguration $configuration
     */
    public function __construct(LinterConfiguration $configuration = null)
    {
        if ($configuration === null) {
            $configuration = new LinterConfiguration();
        }

        $this->configuration = $configuration;
        $this->lintMessageBuilder = new LintMessageBuilder();

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
            $messages = array_merge($messages, $this->lintMessageBuilder->buildLintMessages($path, $fix));
        }

        return $messages;
    }

    public function shouldExpectCommandErrors()
    {
        return true;
    }

    protected function getPathArgumentForLinterFuture($path)
    {
        $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();

        return str_replace($root . '/', '', $path);
    }
}
