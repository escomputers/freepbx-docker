<?php

namespace Jasny\Audio;

/**
 * Plot a waveform
 */
class Waveform
{
    /**
     * Input track
     * @var Track
     */
    protected $track;

    /**
     * Image width
     * @var int
     */
    public $width = 1800;

    /**
     * Image height
     * @var int
     */
    public $height = 280;

    /**
     * Color of the graph
     * @var string
     */
    public $color = '000000';

    /**
     * Color of x axis
     * @var string
     */
    public $axis = null;

    /**
     * The max amplitute (y axis)
     * @var float
     */
    public $level;

    /**
     * The offset where to start (in seconds)
     * @var int
     */
    public $offset;

    /**
     * The duration to chart (in seconds)
     * @var int
     */
    public $duration;


    /**
     * Audio samples
     * @var array
     */
    protected $samples;

    /**
     * The length of the graph in seconds (x axis)
     * @var float
     */
    protected $length;


    /**
     * Class constructor
     *
     * @param Track|string $track     Input track
     * @param array        $settings  Associated array with settings
     */
    public function __construct($track, array $settings=array())
    {
        if (isset($settings['count']) && !isset($settings['width'])) $settings['width'] = $settings['count'];

        foreach ($settings as $key=>$value) {
            if (!property_exists($this, $key)) continue;
            $this->$key = $value;
        }

        $this->track = $track instanceof Track ? $track : new Track($track);
    }

    /**
     * Get the input audio track
     *
     * @return Track
     */
    public function getTrack()
    {
        return $this->track;
    }


    /**
     * Calculate the samples.
     *
     * @return array
     */
    protected function calc()
    {
        if (!file_exists($this->track)) throw new \Exception("File '{$this->track}' doesn't exist");

        $length = $this->track->getLength();
        $rate = null;
        $sample_count = $this->track->getSampleCount();

        $trim = null;
        if ($this->offset || $this->duration) {
            $offset = $this->offset >= 0 ? $this->offset : $length + $this->offset;
            $trim = $offset . ($this->duration ? " " . (float)$this->duration : '');

            $newlength = $this->duration ?: $length - $offset;
            $sample_count = floor(($newlength / $length) * $sample_count);
            $length = $newlength;
        }

        // Downsample to max 500 samples per pixel with a minimum sample rate of 4k/s
        if ($sample_count / $this->width > 500) {
            $rate = max(($this->width / $length) * 500, 4000);
            $sample_count = $rate * $length;
        }

        $this->length = $length;

        $this->samples = $this->calcExecute($sample_count, $trim, $rate);

        if (!isset($this->level)) {
            $this->level = max(-1 * min($this->samples), max($this->samples));
        }
    }

    /**
     * Calculate the samples
     *
     * @param int $sample_count
     * @param string $trim
     * @param float  $rate
     * @return array
     */
    protected function calcExecute($sample_count, $trim, $rate)
    {
        $track = escapeshellarg($this->track);
        if ($trim) $trim = "trim $trim";
        $resample = $rate ? "-r $rate" : '';
        $chunk_size = floor($sample_count / $this->width);

        $descriptorspec = array(
           1 => array("pipe", "w"), // stdout
           2 => array("pipe", "w")  // stderr
        );

        $sox = escapeshellcmd(Track::which('sox'));

        $handle = proc_open("$sox $track -t raw $resample -c 1 -e floating-point -L - $trim", $descriptorspec, $pipes);
        if (!$handle) throw new \Exception("Failed to get the samples using sox");

        $chunk = array();
        $samples = array();

        while ($data = fread($pipes[1], 4 * $chunk_size)) {
            $chunk = unpack('f*', $data);
            $chunk[] = 0;
            $samples[] = min($chunk);
            $samples[] = max($chunk);
        };

        $err = stream_get_contents($pipes[2]);

        $ret = proc_close($handle);
        if ($ret != 0) throw new \Exception("Sox command failed. " . trim($err));

        return $samples;
    }

    /**
     * Get the samples.
     *
     * @return array
     */
    public function getSamples()
    {
        if (!isset($this->samples)) $this->calc();
        return $this->samples;
    }

    /**
     * Get the length of the track.
     *
     * @return array
     */
    public function getLength()
    {
        if (!isset($this->length)) $this->calc();
        return $this->length;
    }

    /**
     * Get the level (max amplitude).
     *
     * @return array
     */
    public function getLevel()
    {
        if (!isset($this->level)) $this->calc();
        return $this->level;
    }


    /**
     * Plot the waveform
     *
     * @return resource
     */
    public function plot()
    {
        $this->getSamples();

        $im = imagecreatetruecolor($this->width, $this->height);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

        $center = ($this->height / 2);
        $scale = ($center / $this->level);
        $color = self::strToColor($im, $this->color);

        for ($i = 0, $n = count($this->samples); $i < $n-1; $i += 2) {
            $max = $center + (-1 * $this->samples[$i] * $scale);
            $min = $center + (-1 * $this->samples[$i+1] * $scale);

            imageline($im, $i / 2, $min, $i / 2, $max, $color);
        }

        if (!empty($this->axis)) {
            imageline($im, 0, $this->height / 2, $this->width, $this->height / 2, self::strToColor($im, $this->axis));
        }

        return $im;
    }

    /**
     * Create a gd color using a hexidecimal color notation
     *
     * @param resource $im
     * @param string   $color
     * @return int
     */
    protected static function strToColor($im, $color)
    {
        $color = ltrim($color, '#');

        if (strpos($color, ',') !== false) {
            list($red, $green, $blue, $opacity) = explode(',', $color) + array(3 => null);
        } else {
            $red = hexdec(substr($color, 0, 2));
            $green = hexdec(substr($color, 2, 2));
            $blue = hexdec(substr($color, 4, 2));
            $opacity = 1;
        }

        $alpha = round((1 - $opacity) * 127);
        return imagecolorallocatealpha($im, $red, $green, $blue, $alpha);
    }

    /**
     * Output the generated waveform
     *
     * @param string $format  Options: png or json
     */
    public function save($format='png',$filename='')
    {
        $fn = "save$format";
        if (!method_exists($this, $fn)) throw new \Exception("Unknown format '$format'");

        $this->$fn($filename);
    }

    /**
     * Output the generated waveform as PNG
     */
    protected function savePng($filename)
    {
        $im = $this->plot();
        imagepng($im,$filename);
    }

    /**
     * Output the generated waveform
     *
     * @param string $format  Options: png or json
     */
    public function output($format='png')
    {
        $fn = "output$format";
        if (!method_exists($this, $fn)) throw new \Exception("Unknown format '$format'");

        $this->$fn();
    }

    /**
     * Output the generated waveform as PNG
     */
    protected function outputPng()
    {
        $im = $this->plot();
        imagepng($im);
    }

    /**
     * Output the generated waveform as JSON
     */
    protected function outputJson()
    {
        return json_encode(array(
            'length'=>$this->getLength(),
            'level'=>$this->getLevel(),
            'samples'=>$this->getSamples()
        ));
    }
}
