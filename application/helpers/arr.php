<?php

/**
 * Array helper
 */
class arr
{
    /**
     * Counts dimension of array
     * 
     * @param array $input
     * 
     * @return int
     */
    public static function dimension($input)
    {
        if (is_array(reset($array)))
        {
            $return = self::dimension(reset($array)) + 1;
        }

        else
        {
            $return = 1;
        }

        return $return;
    }

    /**
    * Removes empty values from array
    * 
    * @param array $arr
    * @param bool $recursive
    * 
    * @return array
    */
    public static function remove_empty_values($arr = array(), $recursive = false)
    {
        if(!is_array($arr))
        {
            if(!strval($arr) !== "0" && !$arr)
            {
                return NULL;
            }
            else
            {
                return $arr;
            }
        }

        for($i = 0; $i < count($arr); $i++)
        {
            if($recursive && is_array($arr[$i]))
            {
                $arr[$i] = self::remove_empty_values($arr[$i]);
            }
            
            $arr[$i] = trim($arr[$i]);
            
            if($arr[$i] === '' || $arr[$i] === NULL)
            {
                unset($arr[$i]);
            }
        }
        
        return $arr;
    }
}