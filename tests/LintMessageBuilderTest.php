<?php

namespace Paysera\Tests;

use PHPUnit\Framework\TestCase;

class LintMessageBuilderTest extends TestCase
{
    /**
     * @var \LintMessageBuilder
     */
    private $lintMessageBuilder;

    protected function setUp()
    {
        $this->lintMessageBuilder = new \LintMessageBuilder();
    }

    /**
     * @dataProvider dataProviderTestBuildsLintMessages
     *
     * @param string $path
     * @param array $lintResult
     * @param int $messagesCount
     */
    public function testBuildsLintMessages($path, $lintResult, $messagesCount)
    {
        $messages = $this->lintMessageBuilder->buildLintMessages($path, $lintResult['files'][0]);
        $this->assertCount($messagesCount, $messages);
    }

    public function dataProviderTestBuildsLintMessages()
    {
        return [
            [
                __DIR__ . '/diff/simple-diff.php',
                json_decode(file_get_contents(__DIR__ . '/diff/simple-diff.json'), true),
                5
            ]
        ];
    }
}
