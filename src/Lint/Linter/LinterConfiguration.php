<?php

class LinterConfiguration
{
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
