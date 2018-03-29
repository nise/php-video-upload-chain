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


class Upload {
        
    function __construct() { 
        $this->result = [
        "upload_max_filesize" => $this->convertPHPSizeToBytes( ini_get('upload_max_filesize') ),
        "post_max_size" => $this->convertPHPSizeToBytes( ini_get('post_max_size') ),
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
        $this->MAX_SIZE = $this->getMaximumFileUploadSize(); // 200MB = 200 * 1024 * 1024; 

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
                $res['size'] = $this->FILES['size'][$key];
                $res['type'] = $this->FILES['type'][$key];
                $res['error'] = '';
                $res['duration'] = $this->getVideoDuration($tmp_name);//"$this->TMP_DIR/$name");
                $res['error'] .= $this->convertVideos($tmp_name, $n);
                // generate gif
                

                $this->extractImages($tmp_name, $res['duration'], 4, $n);

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
            //	echo $e->getMessage();
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
        echo $error;
              return $error;
        /*
        if (!move_uploaded_file(
            $this->TMP_DIR . '/' . $name . '.webm', 
            $this->UPLOAD_DIR . '/' . $name . '.webm'
        )){
            $error .= 'Could not move webm video';
        }*/
          
        // move still image and gif animation
        
        //echo $error;
        //return $error;
    }


    /**
     * Converts a given video file into an webm and mp4 file
     */
    function convertVideos($filename, $name){
        // initialize
        require_once 'vendor/autoload.php';
        $ffmpeg = FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout'          => 360000, // The timeout for the underlying process
            'ffmpeg.threads'   => 16,   // The number of threads that FFMpeg should use
        ));
        
        $ffmpeg = FFMpeg\FFMpeg::create();

        // open video
        $video = $ffmpeg->open( $filename );
       
        $formatx264 = new FFMpeg\Format\Video\X264();
        //$formatx264->setAudioCodec("libmp3lame"); // libvorbis  libmp3lame libfaac
        //$formatwebm = new FFMpeg\Format\Video\WebM();
        //$formatwebm->setAudioCodec("libmp3lame");
        $formatx264->on('progress', function ($audio, $format, $percentage) {
            //echo "$percentage % transcoded";
        });
        
        $video
            ->save($formatx264, $this->TMP_DIR . '/' . $name . '.mp4')
            //->save(new FFMpeg\Format\Video\WMV(), 'export-wmv.wmv')
            //->save($formatwebm, $this->TMP_DIR . '/export-webm.webm');

        // bug: https://github.com/PHP-FFMpeg/PHP-FFMpeg/issues/453
        //$video->filters()->extractMultipleFrames(FFMpeg\Filters\Video\ExtractMultipleFramesFilter::FRAMERATE_EVERY_10SEC, $stillstarget.'test/')->synchronize()->save(new FFMpeg\Format\Video\X264(), 'new.jpg');
        
        
         /*$video
            ->filters()
            ->resize(new FFMpeg\Coordinate\Dimension(320, 240))
            ->synchronize();
        // run scheduler 
        
        $sch = Crunz\Schedule;
        $schedule = new Schedule();
        $schedule
            ->run('/usr/bin/php script.php')
            ->dailyAt('13:30')
            ->description('Copying the project directory');
        return $schedule;
        */
            
        // convert video
        /*
        $webm = new FFMpeg\Format\Video\WebM(); 
        $mp4 = new FFMpeg\Format\Video\X264(); 
        
        // be called once at the beginning and after the job is done
        $webm->on('progress', function ($video, $webm, $percentage) {
            echo "$percentage % transcoded";
        });
        */
        //$format->setAudioCodec("libfaac");
            //->setKiloBitrate(1000)
            //->setAudioChannels(2)
            //->setAudioKiloBitrate(256);
         //$video
            //->save($webm, $videotarget . $filename . '.webm')
            //->save($mp4, $videotarget . $filename . '.mp4')
            ;
        return;
    }


    /**
     * Extracts a given number of still images from a video
     */
    function extractImages( $videofile, $duration, $n, $name ){
        require_once 'vendor/autoload.php'; 
        $ffmpeg = FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout'          => 360000, // The timeout for the underlying process
            'ffmpeg.threads'   => 16,   // The number of threads that FFMpeg should use
        ));  

        $video = $ffmpeg->open( $videofile );
        
        if(is_dir($this->TMP_DIR) && is_writable($this->TMP_DIR)){
            // generate gif animation
            $video 
                ->gif(FFMpeg\Coordinate\TimeCode::fromSeconds(0), new FFMpeg\Coordinate\Dimension(320, 240), 10)
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.gif');
            // generate thumbnail
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(round($duration/2)))
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.jpg');
            
            // generate preview images for every second
            // FRAMERATE_EVERY_SEC: 2, 5, 10, 30, 60 
            //$video->filters()
              //  ->extractMultipleFrames(FFMpeg\Filters\Video\ExtractMultipleFramesFilter::FRAMERATE_EVERY_SEC, $this->TMP_DIR.'/thumbnail-.jpg')
               // ->synchronize();
                
            //$video
            for($i=0; $i < $duration; $i++){
                $video
                    ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($i))
                    ->save( $this->TMP_DIR . '/preview-' . $name . '-' . $i . '.jpg' );        
            }/**/
        }
    }

    /**
     * Determines the video duration
     */
    function getVideoDuration($filename){
        require_once 'vendor/autoload.php'; 
        $ffprobe = FFMpeg\FFProbe::create(); 
        return round( $ffprobe->format( $filename )->get('duration') );
    }
    
    
    /**
    * This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
    * 
    * @param string $sSize
    * @return integer The value in bytes
    */
    function convertPHPSizeToBytes($sSize)
    {
        //
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
    function getMaximumFileUploadSize(){  
        return min($this->convertPHPSizeToBytes(ini_get('post_max_size')), $this->convertPHPSizeToBytes(ini_get('upload_max_filesize'))+1 );
    }  
        
}



$obj = new Upload();

?>
