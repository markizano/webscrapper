<?php

/**
 *  Quick filesystem extension.
 */
class Kizano_File
{

    /**
     *  file_get_contents() with a gZip handler.
     *  @param file     The file to obtain.
     *  @return string
     */
    public static function gz_get_contents($file)
    {
        if (!is_string($file)) {
            throw new Kizano_Exception(sprintf(
                "%s::%s(): Expected string. Received `%s'",
                __CLASS__,
                __FUNCTION__,
                getType($file)
            ));
        }
        $result = null;
        $f = gzOpen($file, 'rb');
        while (!fEOF($f)) {
            $result .= fGetc($f);
        }
        fClose($f);
        return $result;
    }

    /**
     *  file_put_contents() with a gZip handler.
     *  @param filename     The file to place the contents.
     *  @param contents     The content to store.
     *  @return void
     */
    public static function gz_put_contents($filename, $contents = null)
    {
        if (!is_string($filename)) {
            throw new Kizano_Exception(sprintf(
                "%s::%s(): Expected \$filename string. Received `%s'",
                __CLASS__,
                __FUNCTION__,
                getType($filename)
            ));
        }
        if (!is_string($contents)) {
            throw new Kizano_Exception(sprintf(
                "%s::%s(): Expected \$contents string. Received `%s'",
                __CLASS__,
                __FUNCTION__,
                getType($contents)
            ));
        }
        $f = gzOpen($filename, 'wb');
        fWrite($f, $contents);
        fClose($f);
    }
}

