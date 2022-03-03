<?php

/**
 * Class for loading and working with views
 * 
 * @author Jakub Juracka
 */
class View
{
    /**
     * Basic view path
     */
    private static $basic_path = APP_ROOT . 'view/';
    private static $shard_path = APP_ROOT . 'view/shards/';

    private $active_path = '';

    /**
     * Suffix of views
     */
    private $suffix = '.phtml';

    /** 
     * View name 
     * @var string
     */
    private $view_name;

    /**
     * Holds view data
     * 
     * @var array
     */
    private $data = [];


    /**
     * Constructor
     */
    function __construct($name, $suffix = null)
    {
        if($suffix)
        {
            $this->suffix = '.' . preg_replace('/^\s*\.+/', '', $suffix);
        }

        $name = preg_replace('/^\/+/', '', $name);

        // Check, if view exists
        if(file_exists(self::$basic_path . $name . $this->suffix))
        {
            $this->active_path = self::$basic_path;
        }
        else if(file_exists(self::$shard_path . $name . $this->suffix))
        {
            $this->active_path = self::$shard_path;
        }
        else
        {
            throw new Exception('Cannot create View instance. View not found.');
        }

        $this->view_name = $name;
    }

    /**
     * Sets view attributes
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Sets view attributes
     */
    public function __get($key)
    {
        if(isset($this->data[$key]))
        {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * Protects given param against XSS
     * 
     * @param string|array $output
     * 
     * @return string|array
     */
    private function protect($output = null)
    { 
        if($output === FALSE)
        {
            return 0;
        }

        if($output === TRUE)
        {
            return 1;
        }

        if (is_string($output))
        {
            return htmlspecialchars($output, ENT_QUOTES);
        }
        else if(is_array($output))
        {
            foreach($output as $k => $txt)
            {
                $output[$k] = $this->protect($txt);
            }
            return $output;
        }
        else
        {
            return $output;
        }
    }

    /**
     * Prints final view
     * 
     * @param bool $print - Print output?
     * 
     * @return string|null
     */
    public function render($print = TRUE)
    {
        extract($this->protect($this->data));
        extract($this->data, EXTR_PREFIX_ALL, "nonsec"); 

        if($print)
        {
            require($this->active_path . $this->view_name . $this->suffix);    
        }
        else
        {
            ob_start();
            require($this->active_path . $this->view_name . $this->suffix);
            $s = ob_get_contents();
            ob_end_clean();
            return $s;
        }
    }

    /**
     * Returns string of view
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->render(FALSE);
    }
}