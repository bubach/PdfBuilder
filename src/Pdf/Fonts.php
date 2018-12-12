<?php
namespace PdfBuilder\Pdf;

class Fonts
{

    /**
     * read the AFM (also core fonts are stored as .AFM) to calculate character width, height, descender and the FontBBox.
     *
     * @param string $fontpath - path of then *.afm font file
     */
    private function readAFM($fontpath)
    {
        // AFM is always ANSI - no chance for unicode
        $this->IsUnicode = false;
        $this->props['isUnicode'] = $this->IsUnicode;

        $file = file($fontpath);
        foreach ($file as $row) {
            $row = trim($row);
            $pos = strpos($row, ' ');
            if ($pos) {
                // then there must be some keyword
                $key = substr($row, 0, $pos);
                switch ($key) {
                    case 'FontName':
                    case 'FullName':
                    case 'FamilyName':
                    case 'Weight':
                    case 'ItalicAngle':
                    case 'IsFixedPitch':
                    case 'CharacterSet':
                    case 'UnderlinePosition':
                    case 'UnderlineThickness':
                    case 'Version':
                    case 'EncodingScheme':
                    case 'CapHeight':
                    case 'XHeight':
                    case 'Ascender':
                    case 'Descender':
                    case 'StdHW':
                    case 'StdVW':
                    case 'StartCharMetrics':
                        $this->props[$key] = trim(substr($row, $pos));
                        break;
                    case 'FontBBox':
                        $this->props[$key] = explode(' ', trim(substr($row, $pos)));
                        break;
                    case 'C':
                        // C 39 ; WX 222 ; N quoteright ; B 53 463 157 718 ;
                        // use preg_match instead to improve performace
                        // IMPORTANT: if "L i fi ; L l fl ;" is required preg_match must be amended
                        $r = preg_match('/C (-?\d+) ; WX (-?\d+) ; N (\w+) ; B (-?\d+) (-?\d+) (-?\d+) (-?\d+) ;/', $row, $m);
                        if ($r == 1) {
                            //$dtmp = array('C'=> $m[1],'WX'=> $m[2], 'N' => $m[3], 'B' => array($m[4], $m[5], $m[6], $m[7]));
                            $c = (int) $m[1];
                            $n = $m[3];
                            $width = floatval($m[2]);

                            if ($c >= 0) {
                                if ($c != hexdec($n)) {
                                    $this->props['codeToName'][$c] = $n;
                                }
                                $this->props['C'][$c] = $width;
                                $this->props['C'][$n] = $width;
                            } else {
                                $this->props['C'][$n] = $width;
                            }

                            if (!isset($this->props['MissingWidth']) && $c == -1 && $n === '.notdef') {
                                $this->props['MissingWidth'] = $width;
                            }
                        }
                        break;
                }
            }
        }
    }

    /**
     * read the TTF font (can also contain unicode glyphs) to calculate widths, height and FontBBox
     * The TTF.php class from Thanos Efraimidis (4real.gr) is used to read the TTF binary natively.
     *
     * @param string $fontpath - path of the *.ttf font file
     */
    private function readTTF($fontpath)
    {
        // set unicode to all TTF fonts by default
        $this->IsUnicode = true;

        $ttf = new \TTF(file_get_contents($fontpath));

        $head = $ttf->unmarshalHead();
        $uname = $ttf->unmarshalName();
        $hhea = $ttf->unmarshalHhea();
        $post = $ttf->unmarshalPost(true);
        $maxp = $ttf->unmarshalMAXP();
        $cmap = $ttf->unmarshalCmap();

        $this->props = array(
            'isUnicode' => $this->IsUnicode,
            'ItalicAngle' => $post['italicAngle'],
            'UnderlineThickness' => $post['underlineThickness'],
            'UnderlinePosition' => $post['underlinePosition'],
            'IsFixedPitch' => ($post['isFixedPitch'] == 0) ? false : true,
            'Ascender' => $hhea['ascender'],
            'Descender' => $hhea['descender'],
            'LineGap' => $hhea['lineGap'],
            'FontName' => $uname['nameRecords'][2]['value'],
            'FamilyName' => $uname['nameRecords'][1]['value'],
        );

        // calculate the bounding box properly by using 'units per em' property
        $this->props['FontBBox'] = array(
            intval($head['xMin'] / ($head['unitsPerEm'] / 1000)),
            intval($head['yMin'] / ($head['unitsPerEm'] / 1000)),
            intval($head['xMax'] / ($head['unitsPerEm'] / 1000)),
            intval($head['yMax'] / ($head['unitsPerEm'] / 1000)),
        );
        $this->props['UnitsPerEm'] = $head['unitsPerEm'];

        $encodingTable = array();

        $hmetrics = $ttf->unmarshalHmtx($hhea['numberOfHMetrics'], $maxp['numGlyphs']);

        // get format 6 or format 4 as primary cmap table map glyph with character
        foreach ($cmap['tables'] as $v) {
            if (isset($v['format']) && $v['format'] == '4') {
                $encodingTable = $v;
                break;
            }
        }

        if ($encodingTable['format'] == '4') {
            $glyphsIndices = range(1, $maxp['numGlyphs']);
            $charToGlyph = array();

            $segCount = $encodingTable['segCount'];
            $endCountArray = $encodingTable['endCountArray'];
            $startCountArray = $encodingTable['startCountArray'];
            $idDeltaArray = $encodingTable['idDeltaArray'];
            $idRangeOffsetArray = $encodingTable['idRangeOffsetArray'];
            $glyphIdArray = $encodingTable['glyphIdArray'];

            for ($seg = 0; $seg < $segCount; ++$seg) {
                $endCount = $endCountArray[$seg];
                $startCount = $startCountArray[$seg];
                $idDelta = $idDeltaArray[$seg];
                $idRangeOffset = $idRangeOffsetArray[$seg];
                for ($charCode = $startCount; $charCode <= $endCount; ++$charCode) {
                    if ($idRangeOffset != 0) {
                        $j = $charCode - $startCount + $seg + $idRangeOffset / 2 - $segCount;
                        $gid0 = $glyphIdArray[$j];
                    } else {
                        $gid0 = $idDelta + $charCode;
                    }
                    $gid0 %= 65536;
                    if (in_array($gid0, $glyphsIndices)) {
                        $charToGlyph[sprintf('%d', $charCode)] = $gid0;
                    }
                }
            }

            $cidtogid = str_pad('', 256 * 256 * 2, "\x00");

            $this->props['C'] = array();
            foreach ($charToGlyph as $char => $glyphIndex) {
                $m = \TTF::getHMetrics($hmetrics, $hhea['numberOfHMetrics'], $glyphIndex);

                // calculate the correct char width by dividing it with 'units per em'
                $this->props['C'][$char] = intval($m[0] / ($head['unitsPerEm'] / 1000));

                // TODO: check if this mapping also works for non-unicode TTF fonts
                if ($char >= 0 && $char < 0xFFFF && $glyphIndex) {
                    $cidtogid[$char * 2] = chr($glyphIndex >> 8);
                    $cidtogid[$char * 2 + 1] = chr($glyphIndex & 0xFF);
                }
            }
        } else {
            Cpdf::DEBUG('Font file does not contain format 4 cmap', Cpdf::DEBUG_MSG_WARN, Cpdf::$DEBUGLEVEL);
        }

        $this->props['CIDtoGID'] = base64_encode($cidtogid);
    }
}
