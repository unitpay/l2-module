<?php

class Logger
{
    private static $_instance = null;
    private $pathToDir = 'log';
    private $fileName =  'log.txt';
    private $filePath;

    static public function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        if (!file_exists($this->pathToDir)) {
            mkdir($this->pathToDir, 0755);
        }
        $this->filePath = $this->pathToDir . '/' . $this->fileName;
    }

    public function writeString($text, $header = null ){

        $handle = fopen($this->filePath, 'a');
        if ($handle){
            if (!is_null($header)) {
                fwrite($handle, $header . PHP_EOL);
            }
            fwrite($handle, $text . PHP_EOL);
            fclose($handle);
        }

    }

    public function writeArray($arr, $header = null){
        $handle = fopen($this->filePath, 'a');
        if ($handle){
            if (!is_null($header)){
                fwrite($handle, $header . PHP_EOL);
            }
            foreach ($arr as $k=>$v){
                fwrite($handle, $k . '=>' . $v . PHP_EOL);
            }
            fclose($handle);
        }
    }

}