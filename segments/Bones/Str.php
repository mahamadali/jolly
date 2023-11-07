<?php

namespace Bones;

class Str
{
    public static function array_change_key_case_recursive($arr, $case = CASE_LOWER)
    {
        if (is_array($arr)) {
            return array_map(function ($item) use ($case) {
                if (is_array($item))
                    $item = self::array_change_key_case_recursive($item, $case);
                return $item;
            }, array_change_key_case($arr, $case));
        }
    }

    /**
     * Get character at position from string
     * 
     * @param string $string to get character from
     * @param int $index position of character to find
     * 
     * @return string returned character at specific position
     */
    public static function charAt(string $string, int $index = -1): string
    {
        if ($index == -1) return $index;
        return (string) substr($string, $index, 1);
    }

    /**
     * Get last character from string
     * 
     * @param string $string to get character from
     * 
     * @return string returned character at specific position
     */
    public static function lastChar(string $string): string
    {
        if (empty($string)) return (string) $string;
        return (string) mb_substr($string, -1);
    }

    /**
     * Check string starts with given prefix
     * 
     * @param string $string
     * @param string $prefix
     * @param bool   $strictAtCase
     * 
     * @return bool true if starts with given prefix else false
     */
    public static function startsWith(string $string, string $prefix, bool $strictAtCase = false): bool
    {
        if (!$strictAtCase) {
            $string = strtolower($string);
            $prefix = strtolower($prefix);
        }
        return substr($string, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Check string is in uppercase
     *
     * @param string $string 
     */
    public static function isInUpperCase($string) {
        return ctype_upper($string);
    }

    /**
     * Check string is in lowercase
     *
     * @param string $string 
     */
    public static function isInLowerCase($string) {
        return ctype_lower($string);
    }

    /**
     * Check string is in lowercase
     *
     * @param string $string 
     */
    public static function isCapitalized($string) {
        return ( Str::isInUpperCase(Str::charAt($string, 0)) && Str::isInLowerCase(Str::removeCharAt($string, 0)) );
    }


    /**
     * Check string ends with given prefix
     * 
     * @param string $string
     * @param string $suffix
     * 
     * @return bool true if ends with given suffix else false
     */
    public static function endsWith(string $string, string $suffix): bool
    {
        if (!strlen($suffix))
            return true;
        return substr($string, -strlen($suffix)) === $suffix;
    }

    /**
     * Check string contains given substring
     * 
     * @param string $string
     * @param string $substring
     * @param bool   $strictAtCase
     * 
     * @return bool true if contains substring else false
     */
    public static function contains(string $string, string $substring, bool $strictAtCase = false): bool
    {
        if (!($strictAtCase)) { 
            $string = strtolower($string);
            $substring = strtolower($substring);
        }
        return strpos($string, $substring) !== FALSE;
    }

    /**
     * Check string contains given words
     * 
     * @param string $string
     * @param array $words
     * @param bool   $strictAtCase
     * 
     * @return bool true if contains any word from given words else false
     */
    public static function containsWord(string $string, array $words = [], bool $strictAtCase = false): bool
    {
        if (!($strictAtCase)) { 
            $string = strtolower($string);
        }

        foreach ($words as $word) {
            
            if (!($strictAtCase))
                $word = strtolower($word);
            
            if (Str::contains($string, $word, $strictAtCase))
                return true;
        }

        return false;
        
    }

    /**
     * Replace part of the string with search and replace
     * 
     * @param string $string to replace
     * @param array $wordsToSearch to replace
     * @param array $wordsToReplaceWith to replace with
     * @param bool $withCase to replace with the same case of the word
     * 
     * @return string replaced string
     */
    public static function multiReplace(string $string, array $wordsToSearch, array $wordsToReplaceWith, bool $withCase = null): string
    {
        if (empty($string)) return $string;

        foreach ($wordsToSearch as $wordIndex => $wordToSearch) {
            if (!empty($withCase)) {
                switch ($withCase) {
                    case 'UPPER':
                        $wordsToReplaceWith[$wordIndex] = strtoupper($wordsToReplaceWith[$wordIndex]);
                        break;
                    case 'LOWER':
                        $wordsToReplaceWith[$wordIndex] = strtolower($wordsToReplaceWith[$wordIndex]);
                        break;
                    default:
                        break;
                }
            }

            $string = str_ireplace($wordToSearch, (!empty($wordsToReplaceWith[$wordIndex]) ? $wordsToReplaceWith[$wordIndex] : ''), $string);
        }
        return $string;
    }

    /**
     * Remove matched word from a string
     * 
     * @param string $string to replace
     * @param string $word to remove
     * 
     * @return string replaced string
     */
    public static function remove(string $string, string $word): string
    {
        if (empty($string)) return $string;
        
        return str_ireplace($word, '', $string);
    }

    /**
     * Remove remove character from specific index
     * 
     * @param string $string
     * @param int $character index
     * 
     * @return string string
     */
    public static function removeCharAt(string $string, int $index = -1): string
    {
        return substr($string, 0, $index++) . substr($string, $index);
    }

    /**
     * Remove matched words from a string
     * 
     * @param string $string to replace
     * @param array $words to remove
     * 
     * @return string replaced string
     */
    public static function removeWords(string $string, array $words): string
    {
        return Str::multiReplace($string, $words, []);
    }

    /**
     * Check string is empty
     * 
     * @param $string
     * 
     * @return bool true if empty string else false
     */
    public static function empty($string): bool
    {
        if ($string === null) return true;
        return trim($string) === '';
    }

    /**
     * convert string to camel-case
     * 
     * @param $string
     * 
     * @return string camel-case format
     */
    public static function camelize(string $string): string
    {
        return $string = preg_replace_callback(
            "/(^|[a-z])([A-Z])/",
            function ($m) {
                return strtolower(strlen($m[1]) ? "$m[1]_$m[2]" : "$m[2]");
            },
            $string
        );
    }

    /**
     * convert string to snake-case
     * 
     * @param $string
     * 
     * @return string snake-case format
     */
    public static function decamelize(string $string): string
    {
        return $string = preg_replace_callback(
            "/(^|_)([a-z])/",
            function ($m) {
                return strtoupper("$m[2]");
            },
            $string
        );
    }

    /**
     * Pluralizes a word if quantity is not one.
     *
     * @param string $singular Singular form of word
     * @param string $plural Plural form of word; function will attempt to deduce plural form from singular if not provided
     * @return string Pluralized word if quantity is not one, otherwise singular
     */
    public static function pluralize($singular, $plural = null)
    {
        if (!strlen($singular)) return $singular;
        if ($plural !== null) return $plural;

        $last_letter = strtolower($singular[strlen($singular) - 1]);
        switch ($last_letter) {
            case 'y':
                return substr($singular, 0, -1) . 'ies';
            case 's':
                return $singular . 'es';
            default:
                return $singular . 's';
        }
    }

    /**
     * Singular a word with basic pluralize rules.
     *
     * @param string $plural Pluralized form of word
     * @return string Singular word
     */
    public static function singular($plural)
    {
        if (Str::endsWith($plural, 'ies')) {
            return substr($plural, 0, -3) . 'y';
        } else if (Str::endsWith($plural, 'es')) {
            return substr($plural, 0, -2);
        } else if (Str::endsWith($plural, 's')) {
            return substr($plural, 0, -1);
        }

        return $plural;
    }

    /**
     * Check string is valid email address
     * 
     * @param string $email
     * 
     * @return bool
     */
    public static function isEmail(string $email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Remove enclosed quotes from string
     * 
     * @param string $email
     * 
     * @return string
     */
    public static function removeQuotes(string $string)
    {
        if (Str::startsWith($string, '"') || Str::startsWith($string, "'")) {
            $string = str_replace(Str::charAt($string, 0), '', $string);
        }

        if (Str::endsWith($string, '"') || Str::endsWith($string, "'")) {
            $string = str_replace(Str::charAt($string, ( strlen($string) - 1) ), '', $string);
        }

        return $string;
    }

    /**
     * Generate slug from string
     * 
     * @param string $string
     * @param string $glue_with
     * 
     * @return string slug
     */
    public static function toSlug($string, $glue_with = '-')
    {
        $delimiter = $glue_with;

        $string = mb_convert_encoding((string) $string, 'UTF-8', mb_list_encodings());
        $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $delimiter, $string);
        $string = preg_replace('/(' . preg_quote($delimiter, '/') . '){2,}/', '$1', $string);
        $string = trim($string, $delimiter);

        return strtolower($string);
    }

    /**
     * Convert string to title case
     * 
     * @param string $string
     * 
     * @return string $string with title case
     */
    public static function toTitleCase($string)
    {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Split string by uppercase letter
     * 
     * @param string $string
     * 
     * @return array
     */
    public static function ucsplit($string)
    {
        return preg_split('/(?=[A-Z])/', $string);
    }

    /**
     * Convert mixed string to readable
     * 
     * @param string $slug
     * 
     * @return string readable $string
     */
    public static function toReadable($string)
    {
        $readableChunks = explode(' ', $string);

        if (count($readableChunks) > 1) {
            $readableChunks = array_map([static::class, 'toTitleCase'], $readableChunks);
        } else {
            $readableChunks = array_map([static::class, 'toTitleCase'], $readableChunks);
        }

        $readable = Str::multiReplace(implode('_', $readableChunks), ['-', '_', ' '], ['_', '_', '_']);

        return trim(implode(' ', array_filter(explode('_', $readable))));
        
    }

    /**
     * Convert array|object to json
     * 
     * @param array|object $set
     * 
     * @return string json
     */
    public static function toJson($set)
    {
        return json_encode($set);
    }

    /**
     * Check string is valid json
     * 
     * @param string $string
     * 
     * @return bool
     */
    public static function isJson($string)
    {
        return is_object(json_decode($string));
    }

    /**
     * Convert about any english textual datetime description into a Unix timestamp
     * 
     * @param string $date
     * 
     * @return string timestamp
     */
    public static function toTimestamp($date)
    {
        return strtotime($date);
    }

    /**
     * Check string is a valid Unix timestamp
     * 
     * @param string $string
     * 
     * @return bool
     */
    public static function isTimestamp($string)
    {
        return ((string) (int) $string === $string)
            && ($string <= PHP_INT_MAX)
            && ($string >= ~PHP_INT_MAX);
    }

    /**
     * Replace multiple white spaces to single whitespace in a string
     * 
     * @param string $string
     * 
     * @return string
     */
    public static function replaceMultiWhitespaceToSingle($string)
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    /**
     * Check string is base64 encoded
     * 
     * @param string $string
     * 
     * @return bool
     */
    public static function isBase64Encoded($string)
    {
        return base64_encode(base64_decode($string, true)) !== $string;
    }

}