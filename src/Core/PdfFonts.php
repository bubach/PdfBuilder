<?php

namespace PdfBuilder\Core;

use PdfBuilder\Exception\PdfException;

class PdfFonts {

    /**
     * @var array
     */
    public $fonts = array();

    /**
     * @var array
     */
    protected $_fontFiles = array();

    /**
     * @var array
     */
    protected $_diffs = array();

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
     * Output fonts and font data
     */
    public function putFonts()
    {
        $output = $this->_pdfOutput;
        $nf     = $output->getPdfObjects();

        foreach ($this->_diffs as $diff) {
            $output->newObj();
            $output->out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$diff.']>>');
            $output->out('endobj');
        }

        $this->_outputFontFiles();

        foreach ($this->fonts as $k => $font) {
            $type = isset($font['type']) ? $font['type'] : 'Core';
            $name = isset($font['name']) ? $font['name'] : '';

            if ($type == 'Core') {
                $this->_outputCoreFont($k, $name);
            } elseif ($type == 'Type1' || $type == 'TrueType') {
                $this->_outputTrueTypeFont($k, $font, $name, $type, $nf);
            } else if ($type == 'TTF') {
                $this->_outputTtfFont($k, $font);
            } else {
                $this->fonts[$k]['n'] = $output->getPdfObjects() + 1;
                $mtd = 'put'.strtolower($type);
                if (!method_exists($this->_pdfOutput->getDocument(), $mtd)) {
                    throw new PdfException("Unsupported font type: ".$type);
                }
                $this->$mtd($font);
            }
        }
    }

    /**
     * TrueType embedded SUBSETS or FULL
     */
    protected function _outputTtfFont($k, $font)
    {
        $output = $this->_pdfOutput;

        $this->fonts[$k]['n'] = $output->getPdfObjects()+1;

        $ttf      = new TTFontFile();
        $fontname = 'MPDFAA'.'+'.$font['name'];
        $subset   = $font['subset'];

        unset($subset[0]);
        $ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
        $ttfontsize   = strlen($ttfontstream);
        $fontstream   = gzcompress($ttfontstream);
        $codeToGlyph  = $ttf->codeToGlyph;
        unset($codeToGlyph[0]);

        // Type0 Font
        // A composite font - a font composed of other fonts, organized hierarchically
        $output->newObj();
        $output->out('<</Type /Font');
        $output->out('/Subtype /Type0');
        $output->out('/BaseFont /'.$fontname.'');
        $output->out('/Encoding /Identity-H');
        $output->out('/DescendantFonts ['.($output->getPdfObjects() + 1).' 0 R]');
        $output->out('/ToUnicode '.($output->getPdfObjects() + 2).' 0 R');
        $output->out('>>');
        $output->out('endobj');

        // CIDFontType2
        // A CIDFont whose glyph descriptions are based on TrueType font technology
        $output->newObj();
        $output->out('<</Type /Font');
        $output->out('/Subtype /CIDFontType2');
        $output->out('/BaseFont /'.$fontname.'');
        $output->out('/CIDSystemInfo '.($output->getPdfObjects() + 2).' 0 R');
        $output->out('/FontDescriptor '.($output->getPdfObjects() + 3).' 0 R');

        if (isset($font['desc']['MissingWidth'])) {
            $output->out('/DW '.$font['desc']['MissingWidth'].'');
        }

        $this->_putTTfontwidths($font, $ttf->maxUni);

        $output->out('/CIDToGIDMap '.($output->getPdfObjects() + 4).' 0 R');
        $output->out('>>');
        $output->out('endobj');

        // ToUnicode
        $output->newObj();
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
        $output->out('<</Length '.(strlen($toUni)).'>>');
        $output->putStream($toUni);
        $output->out('endobj');

        // CIDSystemInfo dictionary
        $output->newObj();
        $output->out('<</Registry (Adobe)');
        $output->out('/Ordering (UCS)');
        $output->out('/Supplement 0');
        $output->out('>>');
        $output->out('endobj');

        // Font descriptor
        $output->newObj();
        $output->out('<</Type /FontDescriptor');
        $output->out('/FontName /'.$fontname);

        foreach ($font['desc'] as $kd => $v) {
            if ($kd == 'Flags') {
                $v = $v | 4;
                $v = $v & ~32;
            }
            $output->out(' /'.$kd.' '.$v);
        }

        $output->out('/FontFile2 '.($output->getPdfObjects() + 2).' 0 R');
        $output->out('>>');
        $output->out('endobj');

        // Embed CIDToGIDMap
        // A specification of the mapping from CIDs to glyph indices
        $cidtogidmap = '';
        $cidtogidmap = str_pad('', 256*256*2, "\x00");

        foreach ($codeToGlyph as $cc => $glyph) {
            $cidtogidmap[$cc*2] = chr($glyph >> 8);
            $cidtogidmap[$cc*2 + 1] = chr($glyph & 0xFF);
        }

        $cidtogidmap = gzcompress($cidtogidmap);

        $output->newObj();
        $output->out('<</Length '.strlen($cidtogidmap).'');
        $output->out('/Filter /FlateDecode');
        $output->out('>>');
        $output->putStream($cidtogidmap);
        $output->out('endobj');

        //Font file
        $output->newObj();
        $output->out('<</Length '.strlen($fontstream));
        $output->out('/Filter /FlateDecode');
        $output->out('/Length1 '.$ttfontsize);
        $output->out('>>');
        $output->putStream($fontstream);
        $output->out('endobj');
        unset($ttf);
    }

    /**
     * Additional Type1 or TrueType font
     *
     * @param $k
     * @param $font
     * @param $name
     * @param $type
     * @param $nf
     */
    protected function _outputTrueTypeFont($k, $font, $name, $type, $nf)
    {
        $output = $this->_pdfOutput;

        $this->fonts[$k]['n'] = $output->getPdfObjects() + 1;
        $output->newObj();
        $output->out('<</Type /Font');
        $output->out('/BaseFont /'.$name);
        $output->out('/Subtype /'.$type);
        $output->out('/FirstChar 32 /LastChar 255');
        $output->out('/Widths '.($output->getPdfObjects() + 1).' 0 R');
        $output->out('/FontDescriptor '.($output->getPdfObjects() + 2).' 0 R');

        if ($font['enc']) {
            if (isset($font['diff'])) {
                $output->out('/Encoding '.($nf + $font['diff']).' 0 R');
            } else {
                $output->out('/Encoding /WinAnsiEncoding');
            }
        }
        $output->out('>>');
        $output->out('endobj');

        $output->newObj();
        $cw = &$font['cw'];
        $s  = '[';

        for ($i = 32; $i <= 255; $i++) {
            $s .= $cw[chr($i)].' ';
        }

        $output->out($s.']');
        $output->out('endobj');

        $output->newObj();
        $s = '<</Type /FontDescriptor /FontName /'.$name;

        foreach ($font['desc'] as $k => $v) {
            $s .= ' /'.$k.' '.$v;
        }

        $file = $font['file'];
        if ($file) {
            $s .= ' /FontFile'.($type=='Type1' ? '' : '2').' '.$this->_fontFiles[$file]['n'].' 0 R';
        }
        $output->out($s.'>>');
        $output->out('endobj');
    }

    /**
     * Output standard font
     *
     * @param $k
     * @param $name
     */
    private function _outputCoreFont($k, $name)
    {
        $output = $this->_pdfOutput;

        $this->fonts[$k]['n'] = $output->getPdfObjects() + 1;
        $output->newObj();
        $output->out('<</Type /Font');
        $output->out('/BaseFont /'.$name);
        $output->out('/Subtype /Type1');

        if ($name != 'Symbol' && $name != 'ZapfDingbats') {
            $output->out('/Encoding /WinAnsiEncoding');
        }

        $output->out('>>');
        $output->out('endobj');
    }

    /**
     * Output external font files
     *
     * @throws \PdfBuilder\Exception\PdfException
     */
    protected function _outputFontFiles()
    {
        $output = $this->_pdfOutput;

        foreach ($this->_fontFiles as $file => $info) {
            if (!isset($info['type']) || $info['type'] != 'TTF') {
                $output->newObj();
                $this->_fontFiles[$file]['n'] = $output->getPdfObjects();
                $font = '';
                $f    = fopen($output->getDocument()->getFontPath().$file, 'rb', 1);

                if (!$f) {
                    throw new PdfException("Font file not found");
                }

                while (!feof($f)) {
                    $font .= fread($f, 8192);
                }
                fclose($f);

                $compressed = (substr($file, -2) == '.z');

                if (!$compressed && isset($info['length2'])) {
                    $header = (ord($font[0]) == 128);

                    if ($header) {
                        $font = substr($font, 6);
                    }
                    if ($header && ord($font[$info['length1']]) == 128) {
                        $font = substr($font, 0, $info['length1']).substr($font, $info['length1'] + 6);
                    }
                }

                $output->out('<</Length '.strlen($font));
                if ($compressed) {
                    $output->out('/Filter /FlateDecode');
                }
                $output->out('/Length1 '.$info['length1']);
                if (isset($info['length2'])) {
                    $output->out('/Length2 '.$info['length2'].' /Length3 0');
                }
                $output->out('>>');
                $output->putStream($font);
                $output->out('endobj');
            }
        }
    }

    /**
     * Load a font definition file from the font directory
     *
     * @param $font
     * @throws \PdfBuilder\Exception\PdfException
     * @return array
     */
    protected function _loadfont($font)
    {
        $path = $this->_pdfOutput->getDocument()->getFontPath();
        include($path.$font);
        $a = get_defined_vars();

        if (!isset($a['name'])) {
            throw new PdfException("Could not include font definition file");
        }
        return $a;
    }

    /**
     * @param $font
     * @param $maxUni
     */
    protected function _putTTfontwidths(&$font, $maxUni)
    {
        $output = $this->_pdfOutput;

        if (file_exists($font['unifilename'].'.cw127.php')) {
            include($font['unifilename'].'.cw127.php') ;
            $startcid = 128;
        } else {
            $rangeid   = 0;
            $range     = array();
            $prevcid   = -2;
            $prevwidth = -1;
            $interval  = false;
            $startcid  = 1;
        }
        $cwlen = $maxUni + 1;

        for ($cid = $startcid; $cid < $cwlen; $cid++) {
            if ($cid == 128 && (!file_exists($font['unifilename'].'.cw127.php'))) {
                if (is_writable(dirname($output->getDocument()->getFontPath().'Unifonts/x'))) {
                    $fh    = fopen($font['unifilename'].'.cw127.php',"wb");
                    $cw127 = '<?php'."\n";
                    $cw127.='$rangeid   = '.$rangeid.";\n";
                    $cw127.='$prevcid   = '.$prevcid.";\n";
                    $cw127.='$prevwidth = '.$prevwidth.";\n";

                    if ($interval) {
                        $cw127 .= '$interval = true'.";\n";
                    } else {
                        $cw127 .= '$interval = false'.";\n";
                    }

                    $cw127 .= '$range='.var_export($range, true).";\n";
                    fwrite($fh,$cw127,strlen($cw127));
                    fclose($fh);
                }
            }

            if ($font['cw'][$cid*2] == "\00" && $font['cw'][$cid*2+1] == "\00") {
                continue;
            }

            $width = (ord($font['cw'][$cid*2]) << 8) + ord($font['cw'][$cid*2+1]);

            if ($width == 65535) {
                $width = 0;
            }
            if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
                continue;
            }
            if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
                if ($cid == ($prevcid + 1)) {
                    if ($width == $prevwidth) {
                        if ($width == $range[$rangeid][0]) {
                            $range[$rangeid][] = $width;
                        } else {
                            array_pop($range[$rangeid]);

                            $rangeid           = $prevcid;
                            $range[$rangeid]   = array();
                            $range[$rangeid][] = $prevwidth;
                            $range[$rangeid][] = $width;
                        }
                        $interval = true;
                        $range[$rangeid]['interval'] = true;
                    } else {
                        if ($interval) {
                            $rangeid           = $cid;
                            $range[$rangeid]   = array();
                            $range[$rangeid][] = $width;
                        }
                        else { $range[$rangeid][] = $width; }
                        $interval = false;
                    }
                } else {
                    $rangeid           = $cid;
                    $range[$rangeid]   = array();
                    $range[$rangeid][] = $width;
                    $interval          = false;
                }
                $prevcid   = $cid;
                $prevwidth = $width;
            }
        }

        $prevk   = -1;
        $nextk   = -1;
        $prevint = false;

        foreach ($range as $k => $ws) {
            $cws = count($ws);

            if (($k == $nextk) && (!$prevint) && ((!isset($ws['interval'])) || ($cws < 4))) {
                if (isset($range[$k]['interval'])) {
                    unset($range[$k]['interval']);
                }
                $range[$prevk] = array_merge($range[$prevk], $range[$k]);
                unset($range[$k]);
            } else {
                $prevk = $k;
            }

            $nextk = $k + $cws;
            if (isset($ws['interval'])) {
                if ($cws > 3) {
                    $prevint = true;
                } else {
                    $prevint = false;
                }
                unset($range[$k]['interval']);
                --$nextk;
            } else {
                $prevint = false;
            }
        }
        $w = '';

        foreach ($range as $k => $ws) {
            if (count(array_count_values($ws)) == 1) {
                $w .= ' '.$k.' '.($k + count($ws) - 1).' '.$ws[0];
            } else {
                $w .= ' '.$k.' [ '.implode(' ', $ws).' ]' . "\n";
            }
        }

        $output->out('/W ['.$w.' ]');
    }

    /**
     * Add a TrueType, OpenType or Type1 font
     *
     * @param $family
     * @param string $style
     * @param string $file
     * @param bool $uni
     */
    public function addFont($family, $style = '', $file = '', $uni = false) //helvetica, B
    {
        $family    = strtolower($family);
        $style     = strtoupper($style);
        $outputter = $this->_pdfOutput;
        $document  = $outputter->getDocument();
        $fontPath  = $document->getFontPath();

        if ($style == 'IB') {
            $style = 'BI';
        }
        if ($file == '') {
            if ($uni) {
                $file = str_replace(' ', '', $family) . strtolower($style) . '.ttf';
            } else {
                $file = str_replace(' ', '', $family) . strtolower($style) . '.php';
            }
        }

        $fontkey = $family.$style;

        if (isset($this->fonts[$fontkey])) {
            return;
        }

        if ($uni) {

            if (defined("_SYSTEM_TTFONTS") && file_exists(_SYSTEM_TTFONTS.$file )) {
                $ttffilename = _SYSTEM_TTFONTS.$file;
            } else {
                $ttffilename = $fontPath.'Unifonts/'.$file;
            }

            $unifilename  = $fontPath.'Unifonts/'.strtolower(substr($file ,0,(strpos($file ,'.'))));
            $name         = '';
            $originalsize = 0;
            $ttfstat      = @stat($ttffilename);

            if (file_exists($unifilename.'.mtx.php')) {
                include($unifilename.'.mtx.php');
            }

            if (!isset($type) || !isset($name) || $originalsize != $ttfstat['size']) {
                $ttffile = $ttffilename;

                $ttf = new TTFontFile();
                $ttf->getMetrics($ttffile);

                $cw   = $ttf->charWidths;
                $name = preg_replace('/[ ()]/','',$ttf->fullName);
                $desc = array(
                    'Ascent'       => round($ttf->ascent),
                    'Descent'      => round($ttf->descent),
                    'CapHeight'    => round($ttf->capHeight),
                    'Flags'        => $ttf->flags,
                    'FontBBox'     => '['.round($ttf->bbox[0])." ".round($ttf->bbox[1])." ".round($ttf->bbox[2])." ".round($ttf->bbox[3]).']',
                    'ItalicAngle'  => $ttf->italicAngle,
                    'StemV'        => round($ttf->stemV),
                    'MissingWidth' => round($ttf->defaultWidth)
                );

                $up = round($ttf->underlinePosition);
                $ut = round($ttf->underlineThickness);

                $originalsize = $ttfstat['size']+0;
                $type         = 'TTF';

                // Generate metrics .php file
                $s  = '<?php'."\n";
                $s .= '$name         = \''.$name."';\n";
                $s .= '$type         = \''.$type."';\n";
                $s .= '$desc         = '.var_export($desc,true).";\n";
                $s .= '$up           = '.$up.";\n";
                $s .= '$ut           = '.$ut.";\n";
                $s .= '$ttffile      = \''.$ttffile."';\n";
                $s .= '$originalsize = '.$originalsize.";\n";
                $s .= '$fontkey      = \''.$fontkey."';\n";

                if (is_writable(dirname($this->_pdfOutput->getDocument()->getFontPath().'Unifonts/'.'x'))) {
                    $fh = fopen($unifilename.'.mtx.php', "w");
                    fwrite($fh, $s, strlen($s));
                    fclose($fh);
                    $fh = fopen($unifilename.'.cw.dat', "wb");
                    fwrite($fh,$cw,strlen($cw));
                    fclose($fh);
                    @unlink($unifilename.'.cw127.php');
                }
                unset($ttf);
            } else {
                $cw = @file_get_contents($unifilename.'.cw.dat');
            }

            $i            = count($this->fonts) + 1;
            $aliasNbPages = $this->_pdfOutput->getDocument()->getAliasNbPages();

            if (!empty($aliasNbPages)) {
                $sbarr = range(0, 57);
            } else {
                $sbarr = range(0, 32);
            }

            $this->fonts[$fontkey] = array(
                'i'           => $i,
                'type'        => $type,
                'name'        => $name,
                'desc'        => $desc,
                'up'          => $up,
                'ut'          => $ut,
                'cw'          => $cw,
                'ttffile'     => $ttffile,
                'fontkey'     => $fontkey,
                'subset'      => $sbarr,
                'unifilename' => $unifilename
            );

            $this->_fontFiles[$fontkey] = array('length1' => $originalsize, 'type' => "TTF", 'ttffile' => $ttffile);
            $this->_fontFiles[$file] = array('type' => "TTF");
            unset($cw);
        } else {
            $info      = $this->_loadfont($file);
            $info['i'] = count($this->fonts) + 1;

            if (!empty($info['diff'])) {
                $n = array_search($info['diff'], $this->_diffs);

                if (!$n) {
                    $n = count($this->_diffs)+1;
                    $this->_diffs[$n] = $info['diff'];
                }

                $info['diffn'] = $n;
            }
            if (!empty($info['file'])) {
                if ($info['type'] == 'TrueType') {
                    $this->_fontFiles[$info['file']] = array('length1' => $info['originalsize']);
                } else {
                    $this->_fontFiles[$info['file']] = array('length1' => $info['size1'], 'length2' => $info['size2']);
                }
            }
            $this->fonts[$fontkey] = $info;
        }
    }
}
