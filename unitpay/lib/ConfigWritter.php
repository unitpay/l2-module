<?php

class ConfigWritter
{
    private static $instance;
    private $parameters = array();
    private $newData = false;

    private function __construct()
    {
        $this->getConf();
    }

    public function getConf()
    {
        $this->parameters = include 'config.php';
    }

    public function setConf()
    {
        $data = '<?php return array(';
        foreach ($this->parameters as $key => $value) {
            $data .= "\n'".$key."' => '".$value."',";
        }
        $data .= "\n".'); ?>';

        if (!file_put_contents('config.php', $data)) {
            throw new Exception ('Не удалось произвести запись в config.php, проверьте права на запись для этого файла');
        }
    }

    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function setParameter($name, $value)
    {
        $this->newData = true;
        $this->parameters[$name] = $value;
    }

    function __destruct()
    {
        if ($this->newData) {
            $this->setConf();
        }
    }
    static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }

        return new self();
    }
}