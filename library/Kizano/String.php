<?php

class Kizano_String
{

    /**
     *  Strips ALL excess whitespace from strings.
     *  @param string       The string to strip.
     *  @throws Kizano_Exception
     *  @return string
     */
    public static function strip_whitespace($string)
    {
        if (empty($string)) return null;
        if (!is_string($string)) {
            throw new Kizano_Exception(sprintf(
                '%s::%s(): Expecting string. Received `%s\'',
                __CLASS__,
                __FUNCTION__,
                getType($string)
            ));
            return false;
        }
        return trim(preg_replace('/\s+/i', chr(32), $string));
    }
}

