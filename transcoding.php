<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache'); 

include 'util.php';

class Transcoding {
    
    /**
     * 
     */
    function __construct($tmp_name, $duration, $name) { 
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

       $this->convertVideos($tmp_name, $name); 
       $this->extractImages($tmp_name, $duration, 4, $name);

    }
    

    /**
     * 
     */
    function send_message($id, $message, $progress) {
        $d = array('message' => $message , 'progress' => $progress);
        
        echo "id: $id" . PHP_EOL;
        echo "data: " . json_encode($d) . PHP_EOL;
        echo PHP_EOL;
        
        ob_flush();
        flush();
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
        $this->send_message(111, 'Started transcoding', 0);
        $formatx264 = new FFMpeg\Format\Video\X264();
        $formatx264->setAudioCodec("libmp3lame"); // libvorbis  libmp3lame libfaac
        $formatx264->on('progress', function ($audio, $format, $percentage) {
            $this->send_message($percentage, 'x264' , $percentage); 
        });
        $formatwebm = new FFMpeg\Format\Video\WebM();
        $formatwebm->setAudioCodec("libvorbis");
        $formatwebm->on('progress', function ($audio, $format, $percentage) {
            $this->send_message($percentage, 'x264' , $percentage); 
        });
        
        $video->save($formatx264, $this->TMP_DIR . '/' . $name . '.mp4');
        $video->save($formatwebm, $this->TMP_DIR . '/' . $name . '.webm');

        // bug: https://github.com/PHP-FFMpeg/PHP-FFMpeg/issues/453
        //$video->filters()->extractMultipleFrames(FFMpeg\Filters\Video\ExtractMultipleFramesFilter::FRAMERATE_EVERY_10SEC, $stillstarget.'test/')->synchronize()->save(new FFMpeg\Format\Video\X264(), 'new.jpg');
        
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
            'ffmpeg.threads'   => 16   // The number of threads that FFMpeg should use
        ));  

        $video = $ffmpeg->open( $videofile );
        
        if(is_dir($this->TMP_DIR) && is_writable($this->TMP_DIR)){
            // generate gif animation
            $video 
                ->gif(FFMpeg\Coordinate\TimeCode::fromSeconds(0), new FFMpeg\Coordinate\Dimension(320, 240), 10)
                /*->on('progress', function ($audio, $format, $percentage) {
                    $this->send_message($percentage, 'gif' , $percentage); 
                })*/
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.gif');
            $this->send_message($percentage, 'animation' , $percentage);

            // generate thumbnail
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(round($duration/2)))
                /*->on('progress', function ($audio, $format, $percentage) {
                    $this->send_message($percentage, 'thumbnail' , $percentage); 
                })*/
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.jpg');
            $this->send_message($percentage, 'thumbnail' , 100); 
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
                 $this->send_message($percentage, 'preview', round($i / $duration));    
            }
        }else{
            return $this->TMP_DIR . ' does not exist or is not writable';
        }

        return;
    }

}

// initialize class
if(isset($_GET['location']) && isset($_GET['duration']) && isset($_GET['name'])){
    $obj = new Transcoding($_GET['location'], $_GET['duration'], $_GET['name']);
}else{
    echo "GET parameters missing";
}

?>