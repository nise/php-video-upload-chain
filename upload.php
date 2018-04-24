<?php
/**
 * 
 * author: niels.seidel@nise81.com, 2017
 * htaccess file:
 * php_value post_max_size 16M
 * php_value upload_max_filesize 6M
 * todo: 
 * - include this routines in moodle
 */
header('Content-Type: text/plain; charset=utf-8');

include 'util.php';

class Upload {
        
    function __construct() { 
        $this->util = new Util();
        $this->result = [
        "upload_max_filesize" => $this->util->convertPHPSizeToBytes( ini_get('upload_max_filesize') ),
        "post_max_size" => $this->util->convertPHPSizeToBytes( ini_get('post_max_size') ),
        "files" => array(),
        "total-size" => 0,
        "error" => ''
        ];

        $this->HOST = 'http://'.$_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT']; // https xxx
        $this->HOST_PATH = $_SERVER['DOCUMENT_ROOT'] . "/videos/test2";
        $this->UPLOAD_DIR = $_SERVER['DOCUMENT_ROOT'] . '/videos';
        $this->TMP_DIR = $_SERVER['DOCUMENT_ROOT'] . '/videos/tmp';
        $this->STILLS_DIR = $_SERVER['DOCUMENT_ROOT'] . '/moodle/mod/videodatabase/images/stills/';

        if (!isset($_FILES['videofiles']['error']) || is_array($_FILES['videofiles']['error']) ) { // upfile
            //throw new RuntimeException('Invalid parameters.');
        }

        //if(isset($_POST['completeupload'])){}

        if( isset($_GET['completeupload']) && isset($_GET['duration'])){
            $this->moveFiles($_GET['completeupload'], $_GET['duration']);
        }else{
            //$this->moveFiles($_GET['completeupload']);
            $this->upload();
        }
    }


    /**
     * 
     */
    function upload(){
        
        $this->FILES = $_FILES['videofiles'];
        //$MIMES = array('jpg' => 'image/jpeg','png' => 'image/png','gif' => 'image/gif' );
        $this->MIMES = array('mp4' => 'video/mp4','webm' => 'video/webm' );
        $this->MAX_SIZE = $this->util->getMaximumFileUploadSize(); // 200MB = 200 * 1024 * 1024; 

       try {
        
        
        $error = 0;

        switch ($this->FILES['error']) {
            case UPLOAD_ERR_OK:
                $error = UPLOAD_ERR_OK;
                break;
            case UPLOAD_ERR_NO_FILE:
                //throw new RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
                //$error = UPLOAD_ERR_INI_SIZE;
                //break;
            case UPLOAD_ERR_FORM_SIZE:
                    //throw new RuntimeException('Exceeded filesize limit.');
            default:
                    //throw new RuntimeException('Unknown errors.');
        }
            

        for($key=0; $key < sizeof($this->FILES["name"]); $key++) {
                
            if ($error == 0) {
                $date = date_create();
                $timestamp = date_timestamp_get($date);
                $tmp_name = $this->FILES["tmp_name"][$key];
                $name = $timestamp . '-' . basename($this->FILES["name"][$key]);
                $n = preg_replace('/\\.[^.\\s]{3,4}$/', '', $name );
                $res = [];		      
                $res['location'] = "$this->UPLOAD_DIR/$name";
                $res['name'] = $name;
                $res['tmp_location'] = $this->TMP_DIR . '/' . $name;
                $res['name_clean'] = $n;
                $res['size'] = $this->FILES['size'][$key];
                $res['type'] = $this->FILES['type'][$key];
                $res['error'] = '';
                $res['duration'] = $this->getVideoDuration($tmp_name);//"$this->TMP_DIR/$name");
                
                // conversion & extraction
                //$res['error'] .= $this->convertVideos($tmp_name, $n); // function should return errors. xxx
                //$res['error'] .= $this->extractImages($tmp_name, $res['duration'], 4, $n);

                // check mime
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
            
                if ((int)$this->FILES['size'][$key] > $this->MAX_SIZE ) { // check file size
                    $res['error'] .= "File size for ".$name. " (".$this->FILES['size'][$key].") is greater then ".$this->MAX_SIZE;
                
                } else if (false === $ext = array_search( finfo_file($finfo, $tmp_name), $this->MIMES, true)){
                    $res['error'] .= "Invalid file format " .finfo_file($finfo, $tmp_name);
                
                } else if (!move_uploaded_file( $tmp_name, $this->TMP_DIR . '/' . $name )){
                    $res['error'] .= "Could not move file ". $name ." to ".$this->TMP_DIR.'/'.$name;
    
                }
                
                $res['location'] = "$this->HOST_PATH/$name";
                $this->result['files'][(int)$key] = $res;
                $this->result['total-size'] = $this->result['total-size'] + $this->FILES['size'][$key];
                
                $this->result['error'] = $res['error'];
                }
            }
            
            if($this->result['total-size'] > $this->result['post_max_size']){
                $this->result['error'] .= 'Max post size too high '. $this->result["total-size"] . ' of max '. $result["post_max_size"] ;
            }
            echo json_encode($this->result);
        } catch (RuntimeException $e) {
            echo json_encode($e->getMessage());
        }

    }


    /**
     * Moves the uploaded and generated files from the temporal folder to its final destination
     */
    function moveFiles($name, $duration){
        $error = '';
        // move videos
        if (is_dir($this->TMP_DIR) && is_writable($this->TMP_DIR)) {
            if (is_dir($this->UPLOAD_DIR) && is_writable($this->UPLOAD_DIR)) {
                $move = rename($this->TMP_DIR . '/' . $name . '.mp4', $this->UPLOAD_DIR . '/' . $name . '.mp4');
                if($move == false){
                    $error .= 'Could not move mp4 video '. $this->UPLOAD_DIR . '/' . $name . '.mp4';
                }

                $move = rename($this->TMP_DIR . '/' . $name . '.webm', $this->UPLOAD_DIR . '/' . $name . '.webm');
                if($move == false){
                    $error .= 'Could not move webm video '. $this->UPLOAD_DIR . '/' . $name . '.webm';
                }
            }else {
                $error .=  "The upload directory does not exist or is not writable.";
            } 
            if (is_dir($this->STILLS_DIR) && is_writable($this->STILLS_DIR)) {
                if (!rename( 
                    $this->TMP_DIR . '/still-' . $name . '_comp.jpg',
                    $this->STILLS_DIR . '/still-' . $name . '_comp.jpg'
                )){
                    $error .= 'Could not move thumbnail:' . $this->TMP_DIR . '/still-' . $name . '_comp.jpg';
                }

                if (!rename( 
                    $this->TMP_DIR . '/still-' . $name . '_comp.gif',
                    $this->STILLS_DIR . '/still-' . $name . '_comp.gif'
                )){
                    $error .= 'Could not move gif animation '.$name;
                }

                for($i=0; $i < $duration; $i++){
                    if (!rename( 
                        $this->TMP_DIR . '/preview-' . $name . '-'. $i .'.jpg',
                        $this->STILLS_DIR . '/preview-' . $name . '-'. $i .'.jpg'
                    )){
                        $error .= 'Could not move preview '.$name;
                    }
                }

            }else{
                $error .= $this->STILLS_DIR . 'is not writable or existing';
            }
            
        }else {
            $error .=  "The temporary (tmp) directory does not exist or is not writable.";
        }
        
        return $error;
    }


    /**
     * Determines the video duration
     */
    function getVideoDuration($filename){
        require_once 'vendor/autoload.php'; 
        $ffprobe = FFMpeg\FFProbe::create(); 
        return round( $ffprobe->format( $filename )->get('duration') );
    }
   
}

$obj = new Upload();

?>
