PHP-Video-Upload-Chain is a simple tol for processing uploaded videos in order to obtain the desired formats, sizes, qualities, preview images, thumbnails, and animations. 

The script is based on [PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg) and requires a local installation of ffmpeg, lame, and libaacs-dev. 

On Debian or Ubuntu these packages can be installed in the following way:
`sudo apt-get install ffmpeg lame libaacs-dev`

All PHP-related dependencies should be installed with *composer*.

**System features**
* converts the uploaded video as mp4 and webm that are usable will all major browsers
* generates an animated gif including a given number of still images
* generates preview images per second

**TODO**
* convert different frame sizes and bitrates
* add a water mark to every video
* create audio wave form of the video


