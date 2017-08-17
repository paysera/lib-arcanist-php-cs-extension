<?php

class LintMessageBuilder
{
    const CHANGE_NOTATION_REGEX = '#^(%s)(?:\s|[a-zA-Z]|$)#';

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
                        if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $item) {
                            break 2;
                        }
                        $matchedInformational++;
                    }
                    if ($matchedInformational === count($diffPart['informational'])) {
                        $i += $matchedInformational;
                        if (isset($diffPart['removals'])) {
                            $matchedRemovals = 0;
                            foreach ($diffPart['removals'] as $key => $removal) {
                                $realLine = $this->removeChangeNotationChar($removal, '-');
                                if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $realLine) {
                                    break 2;
                                }
                                $matchedRemovals++;
                            }
                            if ($matchedRemovals === count($diffPart['removals'])) {
                                $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                                $i += $matchedRemovals - 1;
                                array_shift($diffParts);
                                break 1;
                            }
                        } elseif (isset($diffPart['additions'])) {
                            $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                            array_shift($diffParts);
                            break 1;
                        }
                    }
                } elseif (isset($diffPart['removals'])) {
                    $matchedRemovals = 0;
                    foreach ($diffPart['removals'] as $key => $removal) {
                        $realLine = $this->removeChangeNotationChar($removal, '-');
                        if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $realLine) {
                            break 2;
                        }
                        $matchedRemovals++;
                    }
                    if ($matchedRemovals === count($diffPart['removals'])) {
                        $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                        $i += $matchedRemovals - 1;
                        array_shift($diffParts);
                        break 1;
                    }
                }
            }
        }

        if (count($diffParts) > 0) {
            $message = new \ArcanistLintMessage();
            $message->setName(implode(', ', $fixData['appliedFixers']));
            $message->setPath($path);
            $message->setCode('PHP_CS_FIXER');
            $message->setSeverity(\ArcanistLintSeverity::SEVERITY_WARNING);
            $message->setDescription(sprintf(
                "Lint engine was unable to extract exact line number\n"
                . "Please consider applying these changes:\n```%s```",
                $fixData['diff']
            ));

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * @param string $string
     * @param string $char
     * @return string
     */
    private function removeChangeNotationChar($string, $char)
    {
        return trim(preg_replace(
            sprintf(self::CHANGE_NOTATION_REGEX, preg_quote($char, '#')),
            '',
            $string
        ));
    }

    /**
     * @param string $string
     * @param string $char
     * @return bool
     */
    private function isChangeNotationChar($string, $char)
    {
        return preg_match(
                sprintf(self::CHANGE_NOTATION_REGEX, preg_quote($char, '#')), $string
            ) === 1;
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
            $parts[$key] = array_map('trim', explode("\n", trim($part)));
        }

        $parts = $this->splitCombinedDiffs($parts);

        foreach ($parts as $key => $lines) {
            foreach ($lines as $line) {
                if ($this->isChangeNotationChar($line, '-')) {
                    $diffParts[$key]['removals'][] = $line;
                } elseif ($this->isChangeNotationChar($line, '+')) {
                    $diffParts[$key]['additions'][] = $line;
                } else {
                    $diffParts[$key]['informational'][] = $line;
                }
            }
        }

        $diffParts = array_filter($diffParts, function ($item) {
            if (
                isset($item['informational'])
                && (!isset($item['removals']) && !isset($item['additions']))
            ) {
                return false;
            }
            return true;
        });

        return $diffParts;
    }

    private function splitCombinedDiffs(array $parts)
    {
        foreach ($parts as $key => $lines) {
            $removals = 0;
            $lastRemovalNo = 0;
            $additions = 0;
            $lastAdditionNo = 0;
            foreach ($lines as $no => $line) {
                if ($this->isChangeNotationChar($line, '-')) {
                        $removals++;
                        $lastRemovalNo = $no + 1;
                } elseif ($this->isChangeNotationChar($line, '+')) {
                    $additions++;
                    $lastAdditionNo = $no + 1;
                } else {
                    if ($additions !== 0) {
                        $part1 = array_slice($lines, 0, $lastAdditionNo);
                        $part2 = array_slice($lines, $lastAdditionNo);
                        array_splice($parts, $key,1, [$part1, $part2]);
                        return $this->splitCombinedDiffs($parts);
                    }
                    if ($removals !== 0) {
                        $part1 = array_slice($lines, 0, $lastRemovalNo);
                        $part2 = array_slice($lines, $lastRemovalNo);
                        array_splice($parts, $key,1, [$part1, $part2]);
                        return $this->splitCombinedDiffs($parts);
                    }
                }
            }
        }

        return $parts;
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
