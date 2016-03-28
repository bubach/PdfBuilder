<?php

namespace PdfBuilder\Core;

class PdfImages {

    function _parsejpg($file)
    {
        // Extract info from a JPEG file
        $a = getimagesize($file);
        if(!$a)
            $this->Error('Missing or incorrect image file: '.$file);
        if($a[2]!=2)
            $this->Error('Not a JPEG file: '.$file);
        if(!isset($a['channels']) || $a['channels']==3)
            $colspace = 'DeviceRGB';
        elseif($a['channels']==4)
            $colspace = 'DeviceCMYK';
        else
            $colspace = 'DeviceGray';
        $bpc = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);
        return array('w'=>$a[0], 'h'=>$a[1], 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'DCTDecode', 'data'=>$data);
    }

    function _parsepng($file)
    {
        // Extract info from a PNG file
        $f = fopen($file,'rb');
        if(!$f)
            $this->Error('Can\'t open image file: '.$file);
        $info = $this->_parsepngstream($f,$file);
        fclose($f);
        return $info;
    }

    function _parsepngstream($f, $file)
    {
        // Check signature
        if($this->_readstream($f,8)!=chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10))
            $this->Error('Not a PNG file: '.$file);

        // Read header chunk
        $this->_readstream($f,4);
        if($this->_readstream($f,4)!='IHDR')
            $this->Error('Incorrect PNG file: '.$file);
        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord($this->_readstream($f,1));
        if($bpc>8)
            $this->Error('16-bit depth not supported: '.$file);
        $ct = ord($this->_readstream($f,1));
        if($ct==0 || $ct==4)
            $colspace = 'DeviceGray';
        elseif($ct==2 || $ct==6)
            $colspace = 'DeviceRGB';
        elseif($ct==3)
            $colspace = 'Indexed';
        else
            $this->Error('Unknown color type: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Unknown compression method: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Unknown filter method: '.$file);
        if(ord($this->_readstream($f,1))!=0)
            $this->Error('Interlacing not supported: '.$file);
        $this->_readstream($f,4);
        $dp = '/Predictor 15 /Colors '.($colspace=='DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;

        // Scan chunks looking for palette, transparency and image data
        $pal = '';
        $trns = '';
        $data = '';
        do
        {
            $n = $this->_readint($f);
            $type = $this->_readstream($f,4);
            if($type=='PLTE')
            {
                // Read palette
                $pal = $this->_readstream($f,$n);
                $this->_readstream($f,4);
            }
            elseif($type=='tRNS')
            {
                // Read transparency info
                $t = $this->_readstream($f,$n);
                if($ct==0)
                    $trns = array(ord(substr($t,1,1)));
                elseif($ct==2)
                    $trns = array(ord(substr($t,1,1)), ord(substr($t,3,1)), ord(substr($t,5,1)));
                else
                {
                    $pos = strpos($t,chr(0));
                    if($pos!==false)
                        $trns = array($pos);
                }
                $this->_readstream($f,4);
            }
            elseif($type=='IDAT')
            {
                // Read image data block
                $data .= $this->_readstream($f,$n);
                $this->_readstream($f,4);
            }
            elseif($type=='IEND')
                break;
            else
                $this->_readstream($f,$n+4);
        }
        while($n);

        if($colspace=='Indexed' && empty($pal))
            $this->Error('Missing palette in '.$file);
        $info = array('w'=>$w, 'h'=>$h, 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'FlateDecode', 'dp'=>$dp, 'pal'=>$pal, 'trns'=>$trns);
        if($ct>=4)
        {
            // Extract alpha channel
            if(!function_exists('gzuncompress'))
                $this->Error('Zlib not available, can\'t handle alpha channel: '.$file);
            $data = gzuncompress($data);
            $color = '';
            $alpha = '';
            if($ct==4)
            {
                // Gray image
                $len = 2*$w;
                for($i=0;$i<$h;$i++)
                {
                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.)./s','$1',$line);
                    $alpha .= preg_replace('/.(.)/s','$1',$line);
                }
            }
            else
            {
                // RGB image
                $len = 4*$w;
                for($i=0;$i<$h;$i++)
                {
                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.{3})./s','$1',$line);
                    $alpha .= preg_replace('/.{3}(.)/s','$1',$line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            if($this->PDFVersion<'1.4')
                $this->PDFVersion = '1.4';
        }
        $info['data'] = $data;
        return $info;
    }

    function _readstream($f, $n)
    {
        // Read n bytes from stream
        $res = '';
        while($n>0 && !feof($f))
        {
            $s = fread($f,$n);
            if($s===false)
                $this->Error('Error while reading stream');
            $n -= strlen($s);
            $res .= $s;
        }
        if($n>0)
            $this->Error('Unexpected end of stream');
        return $res;
    }

    function _readint($f)
    {
        // Read a 4-byte integer from stream
        $a = unpack('Ni',$this->_readstream($f,4));
        return $a['i'];
    }

    function _parsegif($file)
    {
        // Extract info from a GIF file (via PNG conversion)
        if(!function_exists('imagepng'))
            $this->Error('GD extension is required for GIF support');
        if(!function_exists('imagecreatefromgif'))
            $this->Error('GD has no GIF read support');
        $im = imagecreatefromgif($file);
        if(!$im)
            $this->Error('Missing or incorrect image file: '.$file);
        imageinterlace($im,0);
        $f = @fopen('php://temp','rb+');
        if($f)
        {
            // Perform conversion in memory
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            fwrite($f,$data);
            rewind($f);
            $info = $this->_parsepngstream($f,$file);
            fclose($f);
        }
        else
        {
            // Use temporary file
            $tmp = tempnam('.','gif');
            if(!$tmp)
                $this->Error('Unable to create a temporary file');
            if(!imagepng($im,$tmp))
                $this->Error('Error while saving to temporary file');
            imagedestroy($im);
            $info = $this->_parsepng($tmp);
            unlink($tmp);
        }
        return $info;
    }


    function _putimages()
    {
        foreach(array_keys($this->images) as $file)
        {
            $this->_putimage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    function _putimage(&$info)
    {
        $this->_newobj();
        $info['n'] = $this->n;
        $this->_out('<</Type /XObject');
        $this->_out('/Subtype /Image');
        $this->_out('/Width '.$info['w']);
        $this->_out('/Height '.$info['h']);
        if($info['cs']=='Indexed')
            $this->_out('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal'])/3-1).' '.($this->n+1).' 0 R]');
        else
        {
            $this->_out('/ColorSpace /'.$info['cs']);
            if($info['cs']=='DeviceCMYK')
                $this->_out('/Decode [1 0 1 0 1 0 1 0]');
        }
        $this->_out('/BitsPerComponent '.$info['bpc']);
        if(isset($info['f']))
            $this->_out('/Filter /'.$info['f']);
        if(isset($info['dp']))
            $this->_out('/DecodeParms <<'.$info['dp'].'>>');
        if(isset($info['trns']) && is_array($info['trns']))
        {
            $trns = '';
            for($i=0;$i<count($info['trns']);$i++)
                $trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
            $this->_out('/Mask ['.$trns.']');
        }
        if(isset($info['smask']))
            $this->_out('/SMask '.($this->n+1).' 0 R');
        $this->_out('/Length '.strlen($info['data']).'>>');
        $this->_putstream($info['data']);
        $this->_out('endobj');
        // Soft mask
        if(isset($info['smask']))
        {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
            $smask = array('w'=>$info['w'], 'h'=>$info['h'], 'cs'=>'DeviceGray', 'bpc'=>8, 'f'=>$info['f'], 'dp'=>$dp, 'data'=>$info['smask']);
            $this->_putimage($smask);
        }
        // Palette
        if($info['cs']=='Indexed')
        {
            $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
            $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
            $this->_newobj();
            $this->_out('<<'.$filter.'/Length '.strlen($pal).'>>');
            $this->_putstream($pal);
            $this->_out('endobj');
        }
    }

} 