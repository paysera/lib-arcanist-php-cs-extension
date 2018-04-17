<?php

class LinterConfiguration
{
    const SRC_DIRECTORY = 'src/';
    const PHP_CS_FILE = '.php_cs';
    const BINARY_FILE = 'bin/php-cs-fixer';

    /**
     * @var string
     */
    private $binaryFile;

    /**
     * @var string
     */
    private $phpCsFile;

    /**
     * @var string[]
     */
    private $paths;

    public function __construct()
    {
        $this->paths = [];
        $this->binaryFile = self::BINARY_FILE;
        $this->phpCsFile = self::PHP_CS_FILE;
    }

    /**
     * @return string
     */
    public function getBinaryFile()
    {
        return $this->binaryFile;
    }

    /**
     * @param string $binaryFile
     *
     * @return $this
     */
    public function setBinaryFile($binaryFile)
    {
        $this->binaryFile = $binaryFile;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhpCsFile()
    {
        return $this->phpCsFile;
    }

    /**
     * @param string $phpCsFile
     *
     * @return $this
     */
    public function setPhpCsFile($phpCsFile)
    {
        $this->phpCsFile = $phpCsFile;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * @param string[] $paths
     *
     * @return $this
     */
    public function setPaths($paths)
    {
        $this->paths = $paths;

        return $this;
    }
}
