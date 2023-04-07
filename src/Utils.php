<?php

declare(strict_types=1);

namespace Tsiura\MqttClient;

class Utils
{
    /**
     * Return FALSE if not match, TRUE if no placeholders (+), or array of matched placeholders (+)
     * @param string $topic
     * @param string $subscription
     * @return bool|array
     */
    public static function checkMatch(string $topic, string $subscription): bool|array
    {
        $topicLen = strlen($topic);
        $subLen = strlen($subscription);

        $subPos = 0;
        $topicPos = 0;

        $match = [];

        while ($subPos < $subLen && $topicPos < $topicLen) {
            if ($subscription[$subPos] === $topic[$topicPos]) {
                if ($topicPos === $topicLen - 1) {
                    /* Check for e.g. foo matching foo/# */
                    if ($subPos === $subLen - 3 && $subscription[$subPos + 1] === '/' && $subscription[$subPos + 2] === '#') {
                        return true;
                    }
                }
                $subPos++;
                $topicPos++;
                if (($subPos === $subLen && $topicPos === $topicLen)
                    || ($topicPos === $topicLen && $subPos === $subLen - 1 && $subscription[$subPos] === '+')
                ) {
                    return empty($match) ? true : $match;
                }
            } else {
                if ($subscription[$subPos] === '+') {
                    $part = '';
                    $subPos++;
                    while ($topicPos < $topicLen && $topic[$topicPos] != '/') {
                        $part .= $topic[$topicPos];
                        $topicPos++;
                    }
                    if (!empty($part)) {
                        $match[] = $part;
                    }
                    if ($topicPos === $topicLen && $subPos === $subLen) {
                        return !empty($match) ? $match : true;
                    }
                } elseif ($subscription[$subPos] === '#') {
                    if ($subPos + 1 !== $subLen) {
                        return false;
                    } else {
                        return !empty($match) ? $match : true;
                    }
                } else {
                    return false;
                }
            }
        }

        if ($topicPos < $topicLen || $subPos < $subLen) {
            return false;
        }

        return !empty($match) ? $match : true;
    }
}
