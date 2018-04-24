<?php 

class Util{

    function __construct() { 
        // something
    }

    
    /**
    * This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
    * 
    * @param string $sSize
    * @return integer The value in bytes
    */
    public static function convertPHPSizeToBytes($sSize){

        $sSuffix = strtoupper(substr($sSize, -1));
        if (!in_array($sSuffix,array('P','T','G','M','K'))){
            return (int)$sSize;  
        } 
        $iValue = substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
                // Fallthrough intended
            case 'T':
                $iValue *= 1024;
                // Fallthrough intended
            case 'G':
                $iValue *= 1024;
                // Fallthrough intended
            case 'M':
                $iValue *= 1024;
                // Fallthrough intended
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (int)$iValue;
    }


    /**
     * Returns the maximum size for being uploaded
     */
    public function getMaximumFileUploadSize(){  
        return min($this->convertPHPSizeToBytes(ini_get('post_max_size')), $this->convertPHPSizeToBytes(ini_get('upload_max_filesize'))+1 );
    }  

}

?>