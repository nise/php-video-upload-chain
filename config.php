<?php
return array(
 'host' => 'http://'.$_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'],
 'host_path' => $_SERVER['DOCUMENT_ROOT'] . "/videos/test2",
 'upload_dir' => $_SERVER['DOCUMENT_ROOT'] . '/videos',
 'tmp_dir' => $_SERVER['DOCUMENT_ROOT'] . '/videos/tmp',
 'stills_dir' => $_SERVER['DOCUMENT_ROOT'] . '/videos/tmp', // '/moodle/mod/videodatabase/images/stills/',
 'ffmpeg' => '/usr/bin/ffmpeg',
 'ffprobe' => '/usr/bin/ffprobe'
);
?>