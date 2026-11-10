<?php

namespace App\Support;

final class SkillNameNormalizer
{
    public static function normalize(string $name): string
    {
        $value = mb_strtolower(trim($name));
        // Drop punctuation / symbols; keep letters, numbers, and spaces.
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function displayName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /**
     * Lightweight similarity for "Did you mean?" suggestions.
     */
    public static function isSimilar(string $a, string $b): bool
    {
        $left = self::normalize($a);
        $right = self::normalize($b);
        if ($left === '' || $right === '') {
            return false;
        }
        if ($left === $right) {
            return true;
        }

        similar_text($left, $right, $percent);
        if ($percent >= 82) {
            return true;
        }

        $distance = levenshtein(
            mb_substr($left, 0, 255),
            mb_substr($right, 0, 255)
        );

        $maxLen = max(mb_strlen($left), mb_strlen($right));

        return $maxLen > 0 && ($distance / $maxLen) <= 0.25;
    }
}
