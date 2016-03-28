<?php

namespace PdfBuilder\Core;

class PdfFonts {


    function _putfonts()
    {
        $nf=$this->n;
        foreach($this->diffs as $diff)
        {
            // Encodings
            $this->_newobj();
            $this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$diff.']>>');
            $this->_out('endobj');
        }
        foreach($this->FontFiles as $file=>$info)
        {
            if (!isset($info['type']) || $info['type']!='TTF') {
                // Font file embedding
                $this->_newobj();
                $this->FontFiles[$file]['n']=$this->n;
                $font='';
                $f=fopen($this->_getfontpath().$file,'rb',1);
                if(!$f)
                    $this->Error('Font file not found');
                while(!feof($f))
                    $font.=fread($f,8192);
                fclose($f);
                $compressed=(substr($file,-2)=='.z');
                if(!$compressed && isset($info['length2']))
                {
                    $header=(ord($font[0])==128);
                    if($header)
                    {
                        // Strip first binary header
                        $font=substr($font,6);
                    }
                    if($header && ord($font[$info['length1']])==128)
                    {
                        // Strip second binary header
                        $font=substr($font,0,$info['length1']).substr($font,$info['length1']+6);
                    }
                }
                $this->_out('<</Length '.strlen($font));
                if($compressed)
                    $this->_out('/Filter /FlateDecode');
                $this->_out('/Length1 '.$info['length1']);
                if(isset($info['length2']))
                    $this->_out('/Length2 '.$info['length2'].' /Length3 0');
                $this->_out('>>');
                $this->_putstream($font);
                $this->_out('endobj');
            }
        }
        foreach($this->fonts as $k=>$font)
        {
            // Font objects
            //$this->fonts[$k]['n']=$this->n+1;
            $type = $font['type'];
            $name = $font['name'];
            if($type=='Core')
            {
                // Standard font
                $this->fonts[$k]['n']=$this->n+1;
                $this->_newobj();
                $this->_out('<</Type /Font');
                $this->_out('/BaseFont /'.$name);
                $this->_out('/Subtype /Type1');
                if($name!='Symbol' && $name!='ZapfDingbats')
                    $this->_out('/Encoding /WinAnsiEncoding');
                $this->_out('>>');
                $this->_out('endobj');
            }
            elseif($type=='Type1' || $type=='TrueType')
            {
                // Additional Type1 or TrueType font
                $this->fonts[$k]['n']=$this->n+1;
                $this->_newobj();
                $this->_out('<</Type /Font');
                $this->_out('/BaseFont /'.$name);
                $this->_out('/Subtype /'.$type);
                $this->_out('/FirstChar 32 /LastChar 255');
                $this->_out('/Widths '.($this->n+1).' 0 R');
                $this->_out('/FontDescriptor '.($this->n+2).' 0 R');
                if($font['enc'])
                {
                    if(isset($font['diff']))
                        $this->_out('/Encoding '.($nf+$font['diff']).' 0 R');
                    else
                        $this->_out('/Encoding /WinAnsiEncoding');
                }
                $this->_out('>>');
                $this->_out('endobj');
                // Widths
                $this->_newobj();
                $cw=&$font['cw'];
                $s='[';
                for($i=32;$i<=255;$i++)
                    $s.=$cw[chr($i)].' ';
                $this->_out($s.']');
                $this->_out('endobj');
                // Descriptor
                $this->_newobj();
                $s='<</Type /FontDescriptor /FontName /'.$name;
                foreach($font['desc'] as $k=>$v)
                    $s.=' /'.$k.' '.$v;
                $file=$font['file'];
                if($file)
                    $s.=' /FontFile'.($type=='Type1' ? '' : '2').' '.$this->FontFiles[$file]['n'].' 0 R';
                $this->_out($s.'>>');
                $this->_out('endobj');
            }
            // TrueType embedded SUBSETS or FULL
            else if ($type=='TTF') {
                $this->fonts[$k]['n']=$this->n+1;
                require_once($this->_getfontpath().'unifont/ttfonts.php');
                $ttf = new TTFontFile();
                $fontname = 'MPDFAA'.'+'.$font['name'];
                $subset = $font['subset'];
                unset($subset[0]);
                $ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
                $ttfontsize = strlen($ttfontstream);
                $fontstream = gzcompress($ttfontstream);
                $codeToGlyph = $ttf->codeToGlyph;
                unset($codeToGlyph[0]);

                // Type0 Font
                // A composite font - a font composed of other fonts, organized hierarchically
                $this->_newobj();
                $this->_out('<</Type /Font');
                $this->_out('/Subtype /Type0');
                $this->_out('/BaseFont /'.$fontname.'');
                $this->_out('/Encoding /Identity-H');
                $this->_out('/DescendantFonts ['.($this->n + 1).' 0 R]');
                $this->_out('/ToUnicode '.($this->n + 2).' 0 R');
                $this->_out('>>');
                $this->_out('endobj');

                // CIDFontType2
                // A CIDFont whose glyph descriptions are based on TrueType font technology
                $this->_newobj();
                $this->_out('<</Type /Font');
                $this->_out('/Subtype /CIDFontType2');
                $this->_out('/BaseFont /'.$fontname.'');
                $this->_out('/CIDSystemInfo '.($this->n + 2).' 0 R');
                $this->_out('/FontDescriptor '.($this->n + 3).' 0 R');
                if (isset($font['desc']['MissingWidth'])){
                    $this->_out('/DW '.$font['desc']['MissingWidth'].'');
                }

                $this->_putTTfontwidths($font, $ttf->maxUni);

                $this->_out('/CIDToGIDMap '.($this->n + 4).' 0 R');
                $this->_out('>>');
                $this->_out('endobj');

                // ToUnicode
                $this->_newobj();
                $toUni = "/CIDInit /ProcSet findresource begin\n";
                $toUni .= "12 dict begin\n";
                $toUni .= "begincmap\n";
                $toUni .= "/CIDSystemInfo\n";
                $toUni .= "<</Registry (Adobe)\n";
                $toUni .= "/Ordering (UCS)\n";
                $toUni .= "/Supplement 0\n";
                $toUni .= ">> def\n";
                $toUni .= "/CMapName /Adobe-Identity-UCS def\n";
                $toUni .= "/CMapType 2 def\n";
                $toUni .= "1 begincodespacerange\n";
                $toUni .= "<0000> <FFFF>\n";
                $toUni .= "endcodespacerange\n";
                $toUni .= "1 beginbfrange\n";
                $toUni .= "<0000> <FFFF> <0000>\n";
                $toUni .= "endbfrange\n";
                $toUni .= "endcmap\n";
                $toUni .= "CMapName currentdict /CMap defineresource pop\n";
                $toUni .= "end\n";
                $toUni .= "end";
                $this->_out('<</Length '.(strlen($toUni)).'>>');
                $this->_putstream($toUni);
                $this->_out('endobj');

                // CIDSystemInfo dictionary
                $this->_newobj();
                $this->_out('<</Registry (Adobe)');
                $this->_out('/Ordering (UCS)');
                $this->_out('/Supplement 0');
                $this->_out('>>');
                $this->_out('endobj');

                // Font descriptor
                $this->_newobj();
                $this->_out('<</Type /FontDescriptor');
                $this->_out('/FontName /'.$fontname);
                foreach($font['desc'] as $kd=>$v) {
                    if ($kd == 'Flags') { $v = $v | 4; $v = $v & ~32; }	// SYMBOLIC font flag
                    $this->_out(' /'.$kd.' '.$v);
                }
                $this->_out('/FontFile2 '.($this->n + 2).' 0 R');
                $this->_out('>>');
                $this->_out('endobj');

                // Embed CIDToGIDMap
                // A specification of the mapping from CIDs to glyph indices
                $cidtogidmap = '';
                $cidtogidmap = str_pad('', 256*256*2, "\x00");
                foreach($codeToGlyph as $cc=>$glyph) {
                    $cidtogidmap[$cc*2] = chr($glyph >> 8);
                    $cidtogidmap[$cc*2 + 1] = chr($glyph & 0xFF);
                }
                $cidtogidmap = gzcompress($cidtogidmap);
                $this->_newobj();
                $this->_out('<</Length '.strlen($cidtogidmap).'');
                $this->_out('/Filter /FlateDecode');
                $this->_out('>>');
                $this->_putstream($cidtogidmap);
                $this->_out('endobj');

                //Font file
                $this->_newobj();
                $this->_out('<</Length '.strlen($fontstream));
                $this->_out('/Filter /FlateDecode');
                $this->_out('/Length1 '.$ttfontsize);
                $this->_out('>>');
                $this->_putstream($fontstream);
                $this->_out('endobj');
                unset($ttf);
            }
            else
            {
                // Allow for additional types
                $this->fonts[$k]['n'] = $this->n+1;
                $mtd='_put'.strtolower($type);
                if(!method_exists($this,$mtd))
                    $this->Error('Unsupported font type: '.$type);
                $this->$mtd($font);
            }
        }
    }

    function _putTTfontwidths(&$font, $maxUni) {
        if (file_exists($font['unifilename'].'.cw127.php')) {
            include($font['unifilename'].'.cw127.php') ;
            $startcid = 128;
        }
        else {
            $rangeid = 0;
            $range = array();
            $prevcid = -2;
            $prevwidth = -1;
            $interval = false;
            $startcid = 1;
        }
        $cwlen = $maxUni + 1;

        // for each character
        for ($cid=$startcid; $cid<$cwlen; $cid++) {
            if ($cid==128 && (!file_exists($font['unifilename'].'.cw127.php'))) {
                if (is_writable(dirname($this->_getfontpath().'unifont/x'))) {
                    $fh = fopen($font['unifilename'].'.cw127.php',"wb");
                    $cw127='<?php'."\n";
                    $cw127.='$rangeid='.$rangeid.";\n";
                    $cw127.='$prevcid='.$prevcid.";\n";
                    $cw127.='$prevwidth='.$prevwidth.";\n";
                    if ($interval) { $cw127.='$interval=true'.";\n"; }
                    else { $cw127.='$interval=false'.";\n"; }
                    $cw127.='$range='.var_export($range,true).";\n";
                    $cw127.="?>";
                    fwrite($fh,$cw127,strlen($cw127));
                    fclose($fh);
                }
            }
            if ($font['cw'][$cid*2] == "\00" && $font['cw'][$cid*2+1] == "\00") { continue; }
            $width = (ord($font['cw'][$cid*2]) << 8) + ord($font['cw'][$cid*2+1]);
            if ($width == 65535) { $width = 0; }
            if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) { continue; }
            if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
                if ($cid == ($prevcid + 1)) {
                    if ($width == $prevwidth) {
                        if ($width == $range[$rangeid][0]) {
                            $range[$rangeid][] = $width;
                        }
                        else {
                            array_pop($range[$rangeid]);
                            // new range
                            $rangeid = $prevcid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $prevwidth;
                            $range[$rangeid][] = $width;
                        }
                        $interval = true;
                        $range[$rangeid]['interval'] = true;
                    } else {
                        if ($interval) {
                            // new range
                            $rangeid = $cid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $width;
                        }
                        else { $range[$rangeid][] = $width; }
                        $interval = false;
                    }
                } else {
                    $rangeid = $cid;
                    $range[$rangeid] = array();
                    $range[$rangeid][] = $width;
                    $interval = false;
                }
                $prevcid = $cid;
                $prevwidth = $width;
            }
        }
        $prevk = -1;
        $nextk = -1;
        $prevint = false;
        foreach ($range as $k => $ws) {
            $cws = count($ws);
            if (($k == $nextk) AND (!$prevint) AND ((!isset($ws['interval'])) OR ($cws < 4))) {
                if (isset($range[$k]['interval'])) { unset($range[$k]['interval']); }
                $range[$prevk] = array_merge($range[$prevk], $range[$k]);
                unset($range[$k]);
            }
            else { $prevk = $k; }
            $nextk = $k + $cws;
            if (isset($ws['interval'])) {
                if ($cws > 3) { $prevint = true; }
                else { $prevint = false; }
                unset($range[$k]['interval']);
                --$nextk;
            }
            else { $prevint = false; }
        }
        $w = '';
        foreach ($range as $k => $ws) {
            if (count(array_count_values($ws)) == 1) { $w .= ' '.$k.' '.($k + count($ws) - 1).' '.$ws[0]; }
            else { $w .= ' '.$k.' [ '.implode(' ', $ws).' ]' . "\n"; }
        }
        $this->_out('/W ['.$w.' ]');
    }


    function AddFont($family, $style='', $file='', $uni=false)
    {
        // Add a TrueType, OpenType or Type1 font
        $family = strtolower($family);
        $style = strtoupper($style);
        if($style=='IB')
            $style='BI';
        if($file=='') {
            if ($uni) {
                $file = str_replace(' ','',$family).strtolower($style).'.ttf';
            }
            else {
                $file = str_replace(' ','',$family).strtolower($style).'.php';
            }
        }
        $fontkey = $family.$style;
        if(isset($this->fonts[$fontkey]))
            return;

        if ($uni) {
            if (defined("_SYSTEM_TTFONTS") && file_exists(_SYSTEM_TTFONTS.$file )) { $ttffilename = _SYSTEM_TTFONTS.$file ; }
            else { $ttffilename = $this->_getfontpath().'unifont/'.$file ; }
            $unifilename = $this->_getfontpath().'unifont/'.strtolower(substr($file ,0,(strpos($file ,'.'))));
            $name = '';
            $originalsize = 0;
            $ttfstat = stat($ttffilename);
            if (file_exists($unifilename.'.mtx.php')) {
                include($unifilename.'.mtx.php');
            }
            if (!isset($type) ||  !isset($name) || $originalsize != $ttfstat['size']) {
                $ttffile = $ttffilename;
                require_once($this->_getfontpath().'unifont/ttfonts.php');
                $ttf = new TTFontFile();
                $ttf->getMetrics($ttffile);
                $cw = $ttf->charWidths;
                $name = preg_replace('/[ ()]/','',$ttf->fullName);

                $desc= array('Ascent'=>round($ttf->ascent),
                    'Descent'=>round($ttf->descent),
                    'CapHeight'=>round($ttf->capHeight),
                    'Flags'=>$ttf->flags,
                    'FontBBox'=>'['.round($ttf->bbox[0])." ".round($ttf->bbox[1])." ".round($ttf->bbox[2])." ".round($ttf->bbox[3]).']',
                    'ItalicAngle'=>$ttf->italicAngle,
                    'StemV'=>round($ttf->stemV),
                    'MissingWidth'=>round($ttf->defaultWidth));
                $up = round($ttf->underlinePosition);
                $ut = round($ttf->underlineThickness);
                $originalsize = $ttfstat['size']+0;
                $type = 'TTF';
                // Generate metrics .php file
                $s='<?php'."\n";
                $s.='$name=\''.$name."';\n";
                $s.='$type=\''.$type."';\n";
                $s.='$desc='.var_export($desc,true).";\n";
                $s.='$up='.$up.";\n";
                $s.='$ut='.$ut.";\n";
                $s.='$ttffile=\''.$ttffile."';\n";
                $s.='$originalsize='.$originalsize.";\n";
                $s.='$fontkey=\''.$fontkey."';\n";
                $s.="?>";
                if (is_writable(dirname($this->_getfontpath().'unifont/'.'x'))) {
                    $fh = fopen($unifilename.'.mtx.php',"w");
                    fwrite($fh,$s,strlen($s));
                    fclose($fh);
                    $fh = fopen($unifilename.'.cw.dat',"wb");
                    fwrite($fh,$cw,strlen($cw));
                    fclose($fh);
                    @unlink($unifilename.'.cw127.php');
                }
                unset($ttf);
            }
            else {
                $cw = @file_get_contents($unifilename.'.cw.dat');
            }
            $i = count($this->fonts)+1;
            if(!empty($this->AliasNbPages))
                $sbarr = range(0,57);
            else
                $sbarr = range(0,32);
            $this->fonts[$fontkey] = array('i'=>$i, 'type'=>$type, 'name'=>$name, 'desc'=>$desc, 'up'=>$up, 'ut'=>$ut, 'cw'=>$cw, 'ttffile'=>$ttffile, 'fontkey'=>$fontkey, 'subset'=>$sbarr, 'unifilename'=>$unifilename);

            $this->FontFiles[$fontkey]=array('length1'=>$originalsize, 'type'=>"TTF", 'ttffile'=>$ttffile);
            $this->FontFiles[$file]=array('type'=>"TTF");
            unset($cw);
        }
        else {
            $info = $this->_loadfont($file);
            $info['i'] = count($this->fonts)+1;
            if(!empty($info['diff']))
            {
                // Search existing encodings
                $n = array_search($info['diff'],$this->diffs);
                if(!$n)
                {
                    $n = count($this->diffs)+1;
                    $this->diffs[$n] = $info['diff'];
                }
                $info['diffn'] = $n;
            }
            if(!empty($info['file']))
            {
                // Embedded font
                if($info['type']=='TrueType')
                    $this->FontFiles[$info['file']] = array('length1'=>$info['originalsize']);
                else
                    $this->FontFiles[$info['file']] = array('length1'=>$info['size1'], 'length2'=>$info['size2']);
            }
            $this->fonts[$fontkey] = $info;
        }
    }
    function SetFont($family, $style='', $size=0)
    {
        // Select a font; size given in points
        if($family=='')
            $family = $this->FontFamily;
        else
            $family = strtolower($family);
        $style = strtoupper($style);
        if(strpos($style,'U')!==false)
        {
            $this->underline = true;
            $style = str_replace('U','',$style);
        }
        else
            $this->underline = false;
        if($style=='IB')
            $style = 'BI';
        if($size==0)
            $size = $this->FontSizePt;
        // Test if font is already selected
        if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
            return;
        // Test if font is already loaded
        $fontkey = $family.$style;
        if(!isset($this->fonts[$fontkey]))
        {
            // Test if one of the core fonts
            if($family=='arial')
                $family = 'helvetica';
            if(in_array($family,$this->CoreFonts))
            {
                if($family=='symbol' || $family=='zapfdingbats')
                    $style = '';
                $fontkey = $family.$style;
                if(!isset($this->fonts[$fontkey]))
                    $this->AddFont($family,$style);
            }
            else
                $this->Error('Undefined font: '.$family.' '.$style);
        }
        // Select it
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        $this->CurrentFont = &$this->fonts[$fontkey];
        if ($this->fonts[$fontkey]['type']=='TTF') { $this->unifontSubset = true; }
        else { $this->unifontSubset = false; }
        if($this->page>0)
            $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
    }

    function SetFontSize($size)
    {
        // Set font size in points
        if($this->FontSizePt==$size)
            return;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        if($this->page>0)
            $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
    }

} 