<?php

namespace Bones;

use Exception;
use Bones\FileDeleteError;
use Bones\FileNotFound;
use Bones\FileUploadError;
use Bones\InvalidFileTypeException;

class File
{

    /**
     * @var public property file
     */
    public $file;

    /**
     * Constructor
     * 
     * @param array $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Magic metod __get to get file properties
     */
    public function __get($param)
    {
        if (!empty($param)) {
            switch ($param) {
                case 'mimeType':
                    return mime_content_type($this->file['tmp_name']);
                    break;
                case 'fileSize':
                    return filesize($this->file['tmp_name']);
                    break;
                case 'origName':
                    return basename($this->file['name']);
                    break;
                case 'extension':
                    return pathinfo($this->file['name'], PATHINFO_EXTENSION);;
                    break;
                case 'tmp_name':
                    return $this->file['tmp_name'];
                    break;
                case 'dimensions':
                    if (!$this->isImage($this->file['tmp_name']))
                        throw new InvalidFileTypeException('Attempt to get dimensions failed. Possible reason: ' . $this->origName . ' is not an image');
                    list($width, $height) = getimagesize($this->file['tmp_name']);
                    return ['width' => $width, 'height' => $height];
                    break;
                default:
                    break;
            }
        }
        return '';
    }

    /**
     * Save file to destination
     * 
     * @param string $destination to save the file to
     * @param string $uploadAs(optional) to save the file as
     * 
     * @return bool true if file saved else throws FileUploadError exception
     */
    public function save(string $destination, string $uploadAs = '')
    {
        if (empty($uploadAs)) {
            $uploadAs = $this->origName;
        }
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }
        if (!move_uploaded_file($this->file['tmp_name'], $destination . '/' . $uploadAs)) {
            throw new FileUploadError('Could not move file ' . $this->file['tmp_name']);
        }
        return true;
    }

    /**
     * Remove directory and its files
     * 
     * @param string $dir
     * 
     */
    public static function removeDir($dir)
    {
        if (empty(trim($dir))) {
            throw new Exception('Directory with empty name can not be removed');
        }

        // Delete directory files [RECURSIVE APPROACH]
        $dirFiles = array_diff(scandir($dir), array('.', '..')); 
        foreach ($dirFiles as $file) { 
            (is_dir($dir . '/' . $file)) ? self::removeDir($dir .'/'. $file) : unlink($dir . '/' . $file); 
        }

        // Remove directory
        return rmdir($dir);
    }

    /**
     * Delete file from destination
     * 
     * @param string $filePath to delete
     * 
     * @return bool true if file deleted else throws FileDeleteError/FileNotFound exception
     */
    public static function delete(string $filePath, $mustExist = true)
    {
        if (empty($filePath)) {
            throw new FileDeleteError('Could not delete empty file');
        }
        if ($mustExist && !file_exists($filePath)) {
            throw new FileNotFound('Could not find the file ' . $filePath . ' to delete');
        }
        if (!unlink($filePath)) {
            throw new FileDeleteError('Could not delete file ' . $filePath);
        }
        return true;
    }

    /**
     * Check file is image
     * 
     * @param string $filePath
     * 
     * @return bool true if file is an image else false
     */
    public function isImage($filePath = '')
    {
        if (empty ($filePath)) $filePath = $this->file['tmp_name'];
        
        return (!empty($filePath) && is_array(getimagesize($filePath)));
    }

    /**
    * Get files from the directory
    *
    * @param string $directory
    *
    * @return array directory files
    */
    public static function dirFiles($dir = '', $with_chronological_order_by_name = false)
    {
        if (Str::endsWith($dir, DIRECTORY_SEPARATOR)) {
            $dir = $dir . DIRECTORY_SEPARATOR;
        }

        if (empty($dir)) {
            throw new Exception('Empty directory can not be traversed');
        }

        $files = array();
        foreach (scandir($dir, SCANDIR_SORT_DESCENDING) as $file) {
            if ($file !== '.' && $file !== '..') {
                preg_match('/\d{1,4}_\d{1,2}_\d{1,2}_\d{1,6}/', $file, $fileDateMatches);
                if (!empty($fileDateMatches) && !empty($fileDateMatches[0])) {
                    $files[$fileDateMatches[0]] = $dir . $file;
                } else {
                    $files[] = $dir . $file;
                }
            }
        }

        if ($with_chronological_order_by_name) {
            ksort($files);
        }

        return $files;
    }

}