<?php

class LintMessageBuilder
{
    /**
     * @param string $path
     * @param array $fixData
     * @return \ArcanistLintMessage[]
     */
    public function buildLintMessages($path, array $fixData)
    {
        $diffParts = $this->extractDiffParts($fixData['diff']);
        $rows = array_map('trim', file($path));

        $messages = [];
        for ($i = 0; $i < count($rows); $i++) {
            foreach ($diffParts as $diffPart) {
                if (isset($diffPart['informational'])) {
                    $matchedInformational = 0;
                    foreach ($diffPart['informational'] as $key => $item) {
                        if ($rows[$i + $key] !== $item) {
                            break 2;
                        }
                        $matchedInformational++;
                    }
                    if ($matchedInformational === count($diffPart['informational'])) {
                        $i += $matchedInformational;
                        if (isset($diffPart['removals'])) {
                            $matchedRemovals = 0;
                            foreach ($diffPart['removals'] as $key => $removal) {
                                if ($rows[$i + $key] !== trim(ltrim($removal, '-'))) {
                                    break 1;
                                }
                                $matchedRemovals++;
                            }
                            if ($matchedRemovals === count($diffPart['removals'])) {
                                $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                                $i += $matchedRemovals;
                                array_shift($diffParts);
                                break 1;
                            }
                        } elseif (isset($diffPart['additions'])) {
                            $matchedAdditions = 0;
                            foreach ($diffPart['additions'] as $key => $removal) {
                                if ($rows[$i + $key] !== trim(ltrim($removal, '+'))) {
                                    break 1;
                                }
                                $matchedAdditions++;
                            }
                            if ($matchedAdditions === count($diffPart['additions'])) {
                                $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                                $i += $matchedAdditions;
                                array_shift($diffParts);
                                break 1;
                            }
                        }
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * @param $diff
     * @return array[removals, additions, informational]
     */
    private function extractDiffParts($diff)
    {
        $diffParts = [];
        $parts = explode('@@ @@', $diff);
        array_shift($parts);
        $parts = array_values($parts);

        foreach ($parts as $key => $part) {
            $lines = array_map('trim', explode("\n", trim($part)));
            foreach ($lines as $line) {
                if (strpos($line, '-') === 0) {
                    $diffParts[$key]['removals'][] = $line;
                } elseif (strpos($line, '+') === 0) {
                    $diffParts[$key]['additions'][] = $line;
                } else {
                    if (!isset($diffParts[$key]['additions'])) {
                        $diffParts[$key]['informational'][] = $line;
                    }
                }
            }
        }

        return $diffParts;
    }

    private function createLintMessage($path, array $diffPart, $line, array $fixData)
    {
        $message = new \ArcanistLintMessage();
        $message->setName(implode(', ', $fixData['appliedFixers']));
        $message->setPath($path);
        $message->setCode('PHP_CS_FIXER');
        $message->setLine($line);
        $message->setSeverity(\ArcanistLintSeverity::SEVERITY_WARNING);

        $description = ["Please consider applying these changes:\n```"];
        if (isset($diffPart['removals'])) {
            $removals = array_map(
                function ($item) { return '- ' . trim(ltrim($item, '-')); },
                $diffPart['removals']
            );
            $description = array_merge($description, $removals);
        }
        if (isset($diffPart['additions'])) {
            $additions = array_map(
                function ($item) { return '+ ' . trim(ltrim($item, '+')); },
                $diffPart['additions']
            );
            $description = array_merge($description, $additions);
        }
        $description[] = '```';

        $message->setDescription(implode("\n", $description));

        return $message;
    }
}
