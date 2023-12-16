<?php

namespace Bones;

class JollyManager
{
    protected $console;
    protected $endpoint = 'https://raw.githubusercontent.com/mahamadali/jolly/main/';
    protected $directory_structure = [];
    protected $file_urls = [];
    protected $special_files = ['/commander', '/readme', '/.htaccess'];

    public function __construct($console)
    {
        $this->console = $console;
    }

    public function setDirectoryStructure()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint . 'locker/system/app-structure.json');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $directory_structure = curl_exec($ch);
        curl_close($ch);

        $this->directory_structure = json_decode($directory_structure, true);
    }

    public function generateFileUrls($a, $keys = array())
    {
        if (!is_array($a)) {
            $this->file_urls[] = $this->endpoint . implode('/', $keys) . ((in_array('/' . $a, $this->special_files)) ? $a : '/' . $a );
            return;
        }

        foreach ($a as $k => $v) {
            if (is_numeric($k))
                $newkeys = $keys;
            else
                $newkeys = array_merge($keys, array($k));

            $this->generateFileUrls($v, $newkeys);
        }
    }

    public function update()
    {
        $this->console->showMsgAndContinue('[SELF-UPDATE] process will update app base source code from cloud repository. Are you sure want to proceed?' . PHP_EOL, [], 'info');

        if (!$this->console->confirm('Enter Y for [Yes] or N for [No]: ')) {
            $this->console->throwError('[SELF-UPDATE] process aborted by user' . PHP_EOL, [], 'error');
        }

        $this->console->showMsgAndContinue('Fetching latest directory structure from cloud: This may take a while' . PHP_EOL, [], 'info');
        $this->setDirectoryStructure();
        $this->generateFileUrls($this->directory_structure);
        $this->console->showMsgAndContinue('Update process started. Abortion of process may lead to corrupted code!' . PHP_EOL, [], 'warning');
        $this->proceed();
        $this->console->showMsgAndContinue('Congratulations! Your code is now upto date' . PHP_EOL, [], 'success');
    }

    public function parseSpecialFiles($path)
    {
        if (in_array($path, $this->special_files))
            return ltrim($path, '/');
        
        return $path;
    }

    public function proceed()
    {
        foreach ($this->file_urls as $url) {
            $path = Str::removeWords($url, [$this->endpoint]);
            $file_content = $this->getFileContent($url);
            $path = $this->parseSpecialFiles($path);
            if (file_exists($path)) {
                $this->console->showMsgAndContinue('Updating ' . $path . PHP_EOL, [], 'warning');
                file_put_contents($path, $file_content);
            } else {
                $path = $this->createFile($path);
                $this->console->showMsgAndContinue('Creating ' . $path . PHP_EOL, [], 'important');
                file_put_contents($path, $file_content);
            }
            $this->console->showMsgAndContinue($path . ' successfully updated'. PHP_EOL, [], 'success');
        }
    }

    public function createFile($path)
    {
        if (in_array($path, $this->special_files)) {
            $path = ltrim($path, '/');
        } else {
            if (!file_exists(dirname($path)))
                mkdir(dirname($path), 0644, true);
        }

        $f = fopen($path, 'w');
        fclose($f);

        return $path;
    }

    public function getFileContent($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $file_content = curl_exec($ch);
        curl_close($ch);
        return $file_content;
    }

}