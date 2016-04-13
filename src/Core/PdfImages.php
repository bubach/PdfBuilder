<?php

namespace PdfBuilder\Core;

use PdfBuilder\Exception\PdfException;

class PdfImages {

    /**
     * @var array
     */
    public $images = array();

    /**
     * @var PdfOutput
     */
    protected $_pdfOutput;

    /**
     * Construct output instance
     *
     * @param  PdfOutput $pdfOutput
     */
    public function __construct(PdfOutput $pdfOutput)
    {
        $this->_pdfOutput = $pdfOutput;
    }

    /**
     * Extract info from a JPEG file
     *
     * @param  $file
     * @throws PdfException
     * @return array
     */
    public function parseJpg($file)
    {
        $a = getimagesize($file);

        if (!$a) {
            throw new PdfException('Missing or incorrect image file: '.$file);
        }
        if ($a[2] != 2) {
            throw new PdfException('Not a JPEG file: '.$file);
        }

        if (!isset($a['channels']) || $a['channels'] == 3) {
            $colspace = 'DeviceRGB';
        } elseif($a['channels'] == 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }

        $bpc  = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);

        return array(
            'w'    => $a[0],
            'h'    => $a[1],
            'cs'   => $colspace,
            'bpc'  => $bpc,
            'f'    => 'DCTDecode',
            'data' => $data
        );
    }

    /**
     * Extract info from a PNG file
     *
     * @param  $file
     * @throws PdfException
     * @return array
     */
    public function parsePng($file)
    {
        $f = fopen($file, 'rb');
        if (!$f) {
            throw new PdfException('Can\'t open image file: '.$file);
        }
        $info = $this->_parsePngStream($f, $file);
        fclose($f);
        return $info;
    }

    /**
     * Parse PNG stream
     *
     * @param  $f
     * @param  $file
     * @return array
     * @throws PdfException
     */
    protected function _parsePngStream($f, $file)
    {
        if ($this->_readStream($f, 8) != chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10)) {
            throw new PdfException('Not a PNG file: '.$file);
        }

        $this->_readStream($f, 4);
        if ($this->_readStream($f, 4) != 'IHDR') {
            throw new PdfException('Incorrect PNG file: '.$file);
        }

        $w   = $this->_readInt($f);
        $h   = $this->_readInt($f);
        $bpc = ord($this->_readStream($f, 1));

        if ($bpc > 8) {
            throw new PdfException('16-bit depth not supported: '.$file);
        }

        $ct = ord($this->_readStream($f, 1));
        if ($ct == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            throw new PdfException('Unknown color type: '.$file);
        }

        if (ord($this->_readStream($f, 1)) != 0) {
            throw new PdfException('Unknown compression method: '.$file);
        }
        if (ord($this->_readStream($f, 1)) != 0) {
            throw new PdfException('Unknown filter method: '.$file);
        }
        if (ord($this->_readStream($f, 1)) != 0) {
            throw new PdfException('Interlacing not supported: '.$file);
        }
        $this->_readStream($f, 4);
        $dp = '/Predictor 15 /Colors '.($colspace == 'DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;

        $pal  = '';
        $trns = '';
        $data = '';

        do {
            $n    = $this->_readInt($f);
            $type = $this->_readStream($f, 4);

            if ($type == 'PLTE') {
                $pal = $this->_readStream($f, $n);
                $this->_readStream($f, 4);
            } elseif ($type == 'tRNS') {
                $t = $this->_readStream($f, $n);

                if ($ct == 0) {
                    $trns = array(ord(substr($t, 1, 1)));
                } elseif($ct==2) {
                    $trns = array(ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1)));
                } else {
                    $pos = strpos($t, chr(0));

                    if ($pos !== false) {
                        $trns = array($pos);
                    }
                }
                $this->_readStream($f, 4);
            } elseif($type == 'IDAT') {
                $data .= $this->_readStream($f, $n);
                $this->_readStream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->_readStream($f,$n+4);
            }
        } while($n);

        if ($colspace == 'Indexed' && empty($pal)) {
            throw new PdfException('Missing palette in '.$file);
        }
        $info = array(
            'w'    => $w,
            'h'    => $h,
            'cs'   => $colspace,
            'bpc'  => $bpc,
            'f'    => 'FlateDecode',
            'dp'   => $dp,
            'pal'  => $pal,
            'trns' => $trns
        );

        if ($ct >= 4) {
            if (!function_exists('gzuncompress')) {
                throw new PdfException('Zlib not available, can\'t handle alpha channel: '.$file);
            }
            $data  = gzuncompress($data);
            $color = '';
            $alpha = '';

            if ($ct == 4) {
                $len = 2 * $w;

                for ($i = 0; $i < $h; $i++) {
                    $pos    = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line   = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                $len = 4 * $w;

                for ($i = 0; $i < $h; $i++) {
                    $pos    = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line   = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);

            if ($this->_pdfOutput->getDocument()->pdfVersion < '1.4') {
                $this->_pdfOutput->getDocument()->pdfVersion = '1.4';
            }
        }
        $info['data'] = $data;
        return $info;
    }

    /**
     * Read n bytes from stream
     *
     * @param  $f
     * @param  $n
     * @return string
     * @throws PdfException
     */
    protected function _readStream($f, $n)
    {
        $res = '';

        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                throw new PdfException('Error while reading stream');
            }
            $n   -= strlen($s);
            $res .= $s;
        }

        if ($n > 0) {
            throw new PdfException('Unexpected end of stream');
        }

        return $res;
    }

    /**
     * Read a 4-byte integer from stream
     *
     * @param  $f
     * @return mixed
     */
    protected function _readInt($f)
    {
        $a = unpack('Ni',$this->_readStream($f, 4));
        return $a['i'];
    }

    /**
     * Extract info from a GIF file (via PNG conversion)
     *
     * @param  $file
     * @return array
     * @throws PdfException
     */
    public function parseGif($file)
    {
        if (!function_exists('imagepng')) {
            throw new PdfException('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            throw new PdfException('GD has no GIF read support');
        }

        $im = imagecreatefromgif($file);
        if (!$im) {
            throw new PdfException('Missing or incorrect image file: '.$file);
        }

        imageinterlace($im,0);
        $f = @fopen('php://temp', 'rb+');

        if ($f) {
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            fwrite($f,$data);
            rewind($f);
            $info = $this->_parsePngStream($f, $file);
            fclose($f);
        } else {
            $tmp = tempnam('.', 'gif');

            if (!$tmp) {
                throw new PdfException('Unable to create a temporary file');
            }
            if (!imagepng($im, $tmp)) {
                throw new PdfException('Error while saving to temporary file');
            }

            imagedestroy($im);
            $info = $this->parsePng($tmp);
            unlink($tmp);
        }

        return $info;
    }

    /**
     *  Output images
     */
    public function putImages()
    {
        foreach(array_keys($this->images) as $file) {
            $this->_putImage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    /**
     * Output one image
     *
     * @param $info
     */
    protected function _putImage(&$info)
    {
        $outputter = $this->_pdfOutput;

        $outputter->newObj();
        $info['n'] = $outputter->getPdfObjects();

        $outputter->out('<</Type /XObject');
        $outputter->out('/Subtype /Image');
        $outputter->out('/Width '.$info['w']);
        $outputter->out('/Height '.$info['h']);

        if ($info['cs'] == 'Indexed') {
            $outputter->out('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal']) / 3 - 1).' '.($outputter->getPdfObjects() + 1).' 0 R]');
        } else {
            $outputter->out('/ColorSpace /'.$info['cs']);

            if ($info['cs'] == 'DeviceCMYK') {
                $outputter->out('/Decode [1 0 1 0 1 0 1 0]');
            }
        }

        $outputter->out('/BitsPerComponent '.$info['bpc']);

        if (isset($info['f'])) {
            $outputter->out('/Filter /'.$info['f']);
        }
        if (isset($info['dp'])) {
            $outputter->out('/DecodeParms <<'.$info['dp'].'>>');
        }

        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';

            for ($i =0; $i < count($info['trns']); $i++) {
                $trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
            }

            $outputter->out('/Mask ['.$trns.']');
        }

        if (isset($info['smask'])) {
            $outputter->out('/SMask '.($this->n+1).' 0 R');
        }
        $outputter->out('/Length '.strlen($info['data']).'>>');
        $outputter->putStream($info['data']);
        $outputter->out('endobj');

        if (isset($info['smask'])) {
            $dp    = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
            $smask = array(
                'w'    => $info['w'],
                'h'    => $info['h'],
                'cs'   => 'DeviceGray',
                'bpc'  => 8,
                'f'    => $info['f'],
                'dp'   => $dp,
                'data' => $info['smask']
            );
            $this->_putImage($smask);
        }

        if ($info['cs'] == 'Indexed') {
            $filter = ($outputter->getDocument()->getDoCompress()) ? '/Filter /FlateDecode ' : '';
            $pal    = ($outputter->getDocument()->getDoCompress()) ? gzcompress($info['pal']) : $info['pal'];

            $outputter->newObj();
            $outputter->out('<<'.$filter.'/Length '.strlen($pal).'>>');
            $outputter->putStream($pal);
            $outputter->out('endobj');
        }
    }

} 