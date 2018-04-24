<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache'); 

include 'util.php';

class Transcoding {
    
    /**
     * Class constructor
     */
    function __construct($tmp_name, $duration, $name) { 
        $this->util = new Util();
        $this->config = include 'config.php';
        
        $this->HOST = $this->config['host'];
        $this->HOST_PATH = $this->config['host_path'];
        $this->UPLOAD_DIR = $this->config['upload_dir'];
        $this->TMP_DIR = $this->config['tmp_dir'];
        $this->STILLS_DIR = $this->config['stills_dir'];
        
        $this->convertVideos($tmp_name, $name); 
        $this->extractImages($tmp_name, $duration, 4, $name);
    }
    

    /**
     * Send server site event messages and progress information to the client.
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
        $formatx264 = new FFMpeg\Format\Video\X264();
        $formatx264->setAudioCodec("libmp3lame"); // libvorbis  libmp3lame libfaac
        $formatx264->on('progress', function ($audio, $format, $percentage) {
            $percentage = is_numeric($percentage) ? $percentage : 0;
            $this->send_message($percentage, 'x264' , $percentage); 
        });
        $formatwebm = new FFMpeg\Format\Video\WebM();
        $formatwebm->setAudioCodec("libvorbis");
        $formatwebm->on('progress', function ($audio, $format, $percentage) {
            $percentage = is_numeric($percentage) ? $percentage : 0;
            $this->send_message($percentage, 'x264' , $percentage); 
        });
        
        $video->save($formatx264, $this->TMP_DIR . '/' . $name . '.mp4');
        $video->save($formatwebm, $this->TMP_DIR . '/' . $name . '.webm');
        
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
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.gif');
            $this->send_message('img-ani', 'animation', 100);

            // generate thumbnail
            $video
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(round($duration/2)))
                ->save( $this->TMP_DIR . '/still-' . $name . '_comp.jpg');
            $this->send_message('img-thumb', 'thumbnail', 100); 
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
                 $this->send_message('img', 'preview', round( ($i / $duration) * 100));    
            }
            $this->send_message('img', 'preview', 100);    
        }else{
            return $this->TMP_DIR . ' does not exist or is not writable';
        }
    }

}

// initialize class
if(isset($_GET['location']) && isset($_GET['duration']) && isset($_GET['name'])){
    $obj = new Transcoding($_GET['location'], $_GET['duration'], $_GET['name']);
}else{
    echo "GET: parameters missing";
}

?>