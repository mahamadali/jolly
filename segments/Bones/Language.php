<?php

namespace Bones;

use Bones\Skeletons\Supporters\AutoMethodMap;

class Language extends AutoMethodMap
{
    protected $translations_dir = 'locker/translations';

    public function __trans($word = '', $data = [])
    {
        if (Str::empty($word)) {
            return $word;
        }

        return $this->transWord($word, (!empty(session()->getLanguage())) ? session()->getLanguage() : setting('app.default_lang', 'en'), $data);
    }

    public function __transWord($word, $language, $data = [])
    {
        if (Str::empty($word))
            return $word;

        if (empty(trim($language)))
            $language = (!empty(session()->getLanguage())) ? session()->getLanguage() : setting('app.default_lang', 'en');
        
        if (!file_exists($this->translations_dir .'/'.$language.'.php'))
            $language = setting('app.default_lang', 'en');

        $translated = findFileVariableByKey($this->translations_dir, $language . '.' . $word, $word);
        
        return $this->__mapWithData($translated, $data);
    }

    public function __mapWithData($translated, $data)
    {
        if (!empty($data)) {
            foreach ($data as $varName => $varValue) {
                $translated = preg_replace('/{{\s+'.$varName.'\s+}}/', '{{'.$varName.'}}', $translated);
                preg_match('/{{'.$varName.'}}/i', $translated, $placeholderMatches);
                if (!empty($placeholderMatches) && !empty($placeholderMatches[0])) {

                    $placeholder = Str::removeWords($placeholderMatches[0], ['{{', '}}']);

                    if (Str::isInUpperCase($placeholder)) {
                        $varValue = strtoupper($varValue);
                    } else if (Str::isInLowerCase($placeholder)) {
                        $varValue = strtolower($varValue);
                    } else if (Str::isCapitalized($placeholder)) {
                        $varValue = ucfirst($varValue);
                    }

                    $translated = Str::multiReplace($translated, ['{{' . $varName . '}}'], [$varValue]);
                }
            }
        }

        return $translated;
    }

}