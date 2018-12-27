<?php
namespace PdfBuilder\Font;

use PdfBuilder\Exception\PdfException;
use PdfBuilder\Stream\Stream;
use Exception;

class Parser extends Stream
{
    /**
     * @var array Font table information.
     */
    protected $fontTables = [];

    /**
     * @var int|bool Font table information for internal use.
     */
    protected $numberOfHMetrics, $numGlyphs, $indexToLocFormat, $cMapSegCount, $glyphNames;

    /**
     * @var Stream Holds subsetted font data
     */
    protected $subsetStream;

    /**
     * @var array
     */
    protected $subsettedChars, $subsettedGlyphs, $startCount, $endCount, $idDelta, $idRange = [];

    /**
     * Constants for index positions in
     * subsetted glyph's & font table's
     * array structures.
     */
    const WIDTH      = 0;
    const CHECKSUM   = 0;
    const OFFSET     = 1;
    const LENGTH     = 2;
    const DATA       = 3;
    const LSB        = 3;
    const COMPONENTS = 4;
    const NAME       = 5;
    const SSID       = 6;

    /**
     * @var array Generated font metrics data
     */
    public $fontInfo = [
        'FontName'           => null,
        'FamilyName'         => null,
        'StyleName'          => 'Regular',
        'Flags'              => 4,
        'Ascent'             => null,
        'Descent'            => null,
        'CapHeight'          => null,
        'StemV'              => null,
        'IsFixedPitch'       => null,
        'FontBBox'           => [],
        'ItalicAngle'        => null,
        'Bold'               => null,
        'MissingWidth'       => null,
        'UnitsPerEm'         => null,
        'UnderlinePosition'  => null,
        'UnderlineThickness' => null,
        'Encoding'           => null,
        'CharacterWidths'    => [],
        'ToUnicode'          => [0],
    ];

    /**
     * Constructor.
     *
     * @param  string    $filename  Name of TTF file to load
     * @throws Exception
     */
    public function __construct($filename)
    {
        $this->resource = fopen($filename, 'rb');

        if (!$this->resource) {
            throw new Exception('Font file not found!');
        }

        $this->parseTableDirectories();
    }

    /**
     * Parse the Table Directory
     *
     * @throws PdfException
     * @return $this
     */
    protected function parseTableDirectories()
    {
        if ($this->read(4) !== "\x00\x01\x00\x00") {
            throw new PdfException('Unrecognized file format');
        }

        $numTables = $this->readUShort();
        $this->skip(3 * 2);

        for ($i = 0; $i < $numTables; $i++) {
            $tag = $this->read(4);

            $this->fontTables[$tag] = [
                self::CHECKSUM => $this->read(4),
                self::OFFSET   => $this->readULong(),
                self::LENGTH   => $this->readULong()
            ];
            $this->setMarker($tag, $this->fontTables[$tag][self::OFFSET]);
        }

        return $this->parseHeadHheaMaxp();
    }

    /**
     * Parse the head table, general font information.
     *
     * @throws PdfException
     * @return $this
     */
    protected function parseHeadHheaMaxp()
    {
        $this->seek($this->getMarker('head') + 3 * 4);

        if ($this->readULong() != 0x5F0F3CF5) {
            throw new PdfException('Incorrect magic number');
        }

        $this->skip(2);
        $this->fontInfo['UnitsPerEm'] = $this->readUShort();

        $this->skip(2 * 8);
        $this->fontInfo['FontBBox'] = [
            $this->readShort(),
            $this->readShort(),
            $this->readShort(),
            $this->readShort(),
        ];

        $this->skip(3 * 2);
        $this->indexToLocFormat = $this->readShort();

        $this->seek($this->getMarker('hhea') + 4 + 15 * 2);
        $this->numberOfHMetrics = $this->readUShort();
        $this->seek($this->getMarker('maxp') + 4);
        $this->numGlyphs = $this->readUShort();

        return $this->parseCmap();
    }

    /**
     * Parse the cmap-table, unicode-character to glyph-id mapping.
     *
     * @throws PdfException
     * @return $this
     */
    protected function parseCmap()
    {
        $this->seek($this->getMarker('cmap') + 2);
        $numTables = $this->readUShort();

        for ($i = 0; $i < $numTables; $i++) {
            $platformId  = $this->readUShort();
            $encodingId  = $this->readUShort();
            $tableOffset = $this->readULong();

            if ($platformId == 3 && $encodingId == 1) {
                $this->seek($this->getMarker('cmap') + $tableOffset);
                $this->setMarker('cmap.offset');
                break;
            }
        }
        if ($this->offset == $this->getMarker('cmap') || $this->readUShort() != 4) {
            throw new PdfException('Unexpected sub-table format or missing Unicode encoding');
        }

        $this->skip(2 * 2);
        $this->cMapSegCount = $this->readUShort() / 2;

        foreach(['endCount' => 3 * 2, 'startCount' => 2, 'idDelta' => 0, 'idRange' => 0] as $name => $skip) {
            $this->skip($skip);
            $this->setMarker('cmap.' . $name);

            for ($i = 0; $i < $this->cMapSegCount; $i++) {
                $this->{$name}[$i] = ($name == 'idDelta' ? $this->readShort() : $this->readUShort());
            }
        }

        $this->setMarker('cmap.glyphIdArray');
        return $this->parseName();
    }


    /**
     * Parse general font information, 'name'-table.
     *
     * @throws PdfException
     * @return $this
     */
    protected function parseName()
    {
        $this->seek($this->getMarker('name') + 2);
        $count = $this->readUShort();
        $stringOffset = $this->readUShort();

        for ($i = 0; $i < $count; $i++) {
            $this->skip(3 * 2);
            $nameId = $this->readUShort();
            $length = $this->readUShort();
            $offset = $this->readUShort();

            if (in_array($nameId, [1, 2, 6])) {
                $position = $this->getOffset();
                $this->seek($this->getMarker('name') + $stringOffset + $offset);

                if ($nameId == 1) {
                    $this->fontInfo['FamilyName'] = $this->read($length);
                } elseif ($nameId == 2) {
                    $this->fontInfo['StyleName'] = $this->read($length);
                } elseif ($nameId == 6) {
                    $this->fontInfo['FontName'] = $this->read($length);
                }

                if (!empty($this->fontInfo['FamilyName']) && !empty($this->fontInfo['FontName'])) {
                    break;
                }
                $this->seek($position);
            }
        }

        if (empty($this->fontInfo['FontName'])) {
            throw new PdfException('PostScript name not found');
        }
        return $this->parseOS2();
    }

    /**
     * OS/2 table, contains line spacing, font style and weight.
     * Rudimentary fallback values for fonts without OS/2-table.
     *
     * @return $this
     */
    protected function parseOS2()
    {
        if (isset($this->fontTables['OS/2'])) {
            $this->seek('OS/2');
            $version = $this->readUShort();

            $this->skip(2);
            $usWeightClass = $this->readUShort();
            $this->skip(4 + 11 * 2 + 10 + 4 * 4 + 4);

            $fsSelection = $this->readUShort();
            $this->fontInfo['Bold'] = ($fsSelection & 32) != 0;

            $this->skip(2 * 2);
            $this->fontInfo['Ascent']    = $this->readShort();
            $this->fontInfo['Descent']   = $this->readShort();
            $this->fontInfo['CapHeight'] = 0;

            if ($version >= 2) {
                $this->skip(3 * 2 + 2 * 4 + 2);
                $this->fontInfo['CapHeight'] = $this->readShort();
            }
            if ($usWeightClass >= 600) {
                $this->fontInfo['Flags'] = $this->fontInfo['Flags'] | 262144;
            }
        } else {
            $usWeightClass = 500;
            $this->fontInfo['Ascent'] = ($this->fontInfo['FontBBox'][3] * (1000 / $this->fontInfo['UnitsPerEm']));
            $this->fontInfo['Descent'] = ($this->fontInfo['FontBBox'][1] * (1000 / $this->fontInfo['UnitsPerEm']));
            $this->fontInfo['CapHeight'] = $this->fontInfo['Ascent'];
        }

        $this->fontInfo['StemV'] = 50 + intval(pow(($usWeightClass / 65.0), 2));
        return $this->parsePost();
    }

    /**
     * Parse glyph names, 'post'-table.
     *
     * @return $this
     */
    protected function parsePost()
    {
        $this->seek('post');
        $this->glyphNames = ($this->readULong() == 0x20000) ? true : false;
        $this->fontInfo['ItalicAngle'] = $this->readShort();

        $this->skip(2);
        $this->fontInfo['UnderlinePosition']  = $this->readShort();
        $this->fontInfo['UnderlineThickness'] = $this->readShort();
        $this->fontInfo['IsFixedPitch'] = ($this->readULong() != 0);

        if ($this->fontInfo['ItalicAngle'] != 0) {
            $this->fontInfo['Flags'] = $this->fontInfo['Flags'] | 64;
        }
        if ($this->fontInfo['IsFixedPitch']) {
            $this->fontInfo['Flags'] = $this->fontInfo['Flags'] | 1;
        }
        return $this;
    }

    /**
     * Get combined glyph data from hmtx, loca, glyf & post-tables.
     *
     * @param  $index
     * @return array
     * @throws PdfException
     */
    public function getGlyph($index)
    {
        if ($index > $this->numGlyphs || $index < 0) {
            throw new PdfException('Glyph data not found for glyph Id: ' . $index);
        }
        $this->seek('hmtx');
        $this->skip(($index > $this->numberOfHMetrics ? $this->numberOfHMetrics * 4 : $index * 4));

        $advancedWith = $this->readUShort();
        $lsb = $this->readShort();

        if ($index > $this->numberOfHMetrics) {
            $this->skip(($index - $this->numberOfHMetrics) * 2);
            $lsb = $this->readShort();
        }

        $this->seek('loca');
        $itl = $this->indexToLocFormat;

        $this->skip($index * (($itl == 0) ? 2 : 4));
        $offset = (($itl == 0) ? $this->readUShort() * 2 : $this->readULong());
        $length = (($itl == 0) ? ($this->readUShort() * 2) - $offset : $this->readULong() - $offset);

        return [
            self::OFFSET     => $offset,
            self::LENGTH     => $length,
            self::WIDTH      => $advancedWith,
            self::LSB        => $lsb,
            self::COMPONENTS => $this->getGlyphComponents($offset),
            self::NAME       => $this->getGlyphName($index)
        ];
    }

    /**
     * Get the glyph components, font drawing points,
     * outlines, anti-aliasing information.
     *
     * @param  $offset
     * @return array
     */
    protected function getGlyphComponents($offset)
    {
        $this->seek($this->getMarker('glyf') + $offset);
        $components = [];

        if ($this->readShort() < 0) {
            $this->skip(4 * 2);
            $offset = 5 * 2;

            do {
                $flags = $this->readUShort();
                $components[$offset + 2] = $this->readUShort();

                $skip  = ($flags & 1) ? 4 : 2;
                $skip += ($flags & 8) ? 2 : 0;
                $skip += ($flags & 64) ? 4 : 0;
                $skip += ($flags & 128) ? 8 : 0;

                $this->skip($skip);

                $offset += 2 * 2 + $skip;
            } while ($flags & 32);
        }
        return $components;
    }

    /**
     * Get cmap data for specified character
     *
     * @param  $char
     * @return int
     */
    public function getCmapGlyphId($char)
    {
        $this->seek('cmap.glyphIdArray');

        for ($i = 0; $i < $this->cMapSegCount; $i++) {
            if ($this->idRange[$i] > 0) {
                $this->seek($this->getMarker('cmap.idRange') + 2 * $i + $this->idRange[$i]);
            }

            for ($c = $this->startCount[$i]; $c <= $this->endCount[$i]; $c++) {
                if ($c !== 0xFFFF) {
                    if ($this->idRange[$i] > 0) {
                        $gid = $this->readUShort();
                    }
                    if ($char == $c) {
                        $gid = (isset($gid) ? ($gid > 0 ? $gid + $this->idDelta[$i] : $gid): $c + $this->idDelta[$i]);
                        $gid = ($gid >= 65536) ? $gid - 65536 : $gid;

                        if ($gid > 0) {
                            return $gid;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get the glyph name
     *
     * @param  $glyphId
     * @return int|string
     */
    public function getGlyphName($glyphId)
    {
        $this->seek($this->getMarker('post') + 16 + (4 * 4) + 2 + (2 * $glyphId));
        $glyphNameIndex = $this->readUShort();
        $this->skip(2 * ($this->numGlyphs - $glyphId - 1));

        if ($glyphNameIndex >= 258) {
            $glyphNameIndex -= 258;

            for ($i = 0; $i < $this->numGlyphs; $i++) {
                if ($i == $glyphNameIndex) {
                    return $this->read($this->readByte());
                }
                $this->skip($this->readByte());
            }
        }
        return $glyphNameIndex;
    }

    /**
     * Subset font to characters from specified encoding.
     *
     * @param  string $encoding
     * @param  null   $stream
     * @return Stream
     */
    public function subsetFont($encoding = 'cp1252', $stream = null)
    {
        if (!$stream instanceof Stream) {
            $stream = new Stream();
        }
        $this->subsetStream = $stream;

        $this->addGlyph(0);
        $this->fontInfo['Encoding']     = $encoding;
        $this->fontInfo['MissingWidth'] = $this->subsettedGlyphs[0][self::WIDTH];

        foreach (range(0, 255) as $char) {
            $this->fontInfo['CharacterWidths'][$char] = $this->fontInfo['MissingWidth'];

            if ($encoding == 'cp1252' && in_array($char, [129, 141, 143, 144, 157])) {
                continue;
            }

            $unicode = mb_convert_encoding(chr($char), 'UTF-8', $encoding);
            $unicode = hexdec(bin2hex(iconv('UTF-8', 'UCS-2', $unicode)));

            if ($glyphId = $this->getCmapGlyphId($unicode)) {
                $this->subsettedChars[$unicode] = $glyphId;
                $this->addGlyph($glyphId);
                $this->fontInfo['CharacterWidths'][$char] = $this->subsettedGlyphs[$glyphId][self::WIDTH];
            }

            end($this->fontInfo['ToUnicode']);
            $lastKey = key($this->fontInfo['ToUnicode']);
            $last =& $this->fontInfo['ToUnicode'][$lastKey];

            if (!is_array($last) && $last == $unicode - 1) {
                $last = [$last, 2];
            } elseif (is_array($last) && $last[0] == $unicode - $last[1]) {
                $last[1]++;
            } else {
                $this->fontInfo['ToUnicode'][$char] = $unicode;
            }
        }

        ksort($this->subsettedChars);
        return $this->buildCmap();
    }

    /**
     * Add glyph to subsetted font
     *
     * @param  $id
     * @return void
     */
    public function addGlyph($id)
    {
        if (!isset($this->subsettedGlyphs[$id])) {
            $subsetCount = count($this->subsettedGlyphs);
            $this->subsettedGlyphs[$id] = $this->getGlyph($id);
            $this->subsettedGlyphs[$id][self::SSID] = $subsetCount;

            foreach ($this->subsettedGlyphs[$id][self::COMPONENTS] as $cid) {
                $this->addGlyph($cid);
            }
        }
    }

    /**
     * Build cmap-table, can probably be removed if
     * always using ToUnicode mappings in the PDF.
     *
     * @return Stream
     */
    protected function buildCmap()
    {
        $segments = [];
        $i = -1;

        foreach ($this->subsettedChars as $char => $unicode) {
            if (isset($segments[$i][1]) && $segments[$i][1] + 1 == $char) {
                $segments[$i][1]++;
            } else  {
                $segments[] = [$char, $char];
                $i++;
            }
        }

        $segments[]    = [0xFFFF, 0xFFFF];
        $glyphIdArray  = '';
        $segCount      = count($segments);
        $startCount    = $endCount = $idDelta = $idRangeOffset = [];

        for ($i = 0; $i < $segCount; $i++) {
            list($start, $end) = $segments[$i];

            $startCount[] = $start;
            $endCount[]   = $end;

            if ($start != $end) {
                $idDelta[] = 0;
                $idRangeOffset[] = strlen($glyphIdArray) + ($segCount - $i) * 2;

                for ($c = $start; $c <= $end; $c++) {
                    $glyphId = $this->subsettedChars[$c];
                    $ssid    = $this->subsettedGlyphs[$glyphId][self::SSID];
                    $glyphIdArray .= pack('n', $ssid);
                }
            } else {
                $idRangeOffset[] = $ssid = 0;
                if ($start < 0xFFFF) {
                    $glyphId = $this->subsettedChars[$start];
                    $ssid    = $this->subsettedGlyphs[$glyphId][self::SSID];
                }
                $idDelta[] = $ssid - $start;
            }
        }

        $searchRange   = 2 * (pow(2, floor(log($segCount, 2))));
        $entrySelector = log($searchRange / 2, 2);
        $rangeShift    = 2 * $segCount - $searchRange;
        $cmap = pack('nnnn', 2 * $segCount, $searchRange, $entrySelector, $rangeShift);

        foreach (['endCount', 'startCount', 'idDelta', 'idRangeOffset'] as $name) {
            foreach (${$name} as $val) {
                $cmap .= pack('n', $val);
            }
            $cmap = (($name == 'endCount') ? $cmap . pack('n', 0) : $cmap);
        }

        $cmap .= $glyphIdArray;
        $data  = pack('nnnnN', 0, 1, 3, 1, 12);
        $data .= pack('nnn', 4, 6 + strlen($cmap), 0);

        $this->setTable('cmap', $data . $cmap);
        return $this->buildHheaHmtxLocaMaxp();
    }

    /**
     * Build hhea, hmtx, loca & maxp-table
     *
     * @return Stream
     */
    protected function buildHheaHmtxLocaMaxp()
    {
        $count  = pack('n', count($this->subsettedGlyphs));
        $offset = 0;
        $hmtx   = $loca = '';

        foreach (['hhea' => 4 + 15 * 2, 'maxp' => 4] as $table => $skip) {
            $this->loadTable($table);
            $data = substr_replace($this->fontTables[$table][self::DATA], $count, $skip, 2);
            $this->setTable($table, $data);
        }
        foreach ((array)$this->subsettedGlyphs as $id => $glyph) {
            $hmtx   .= pack('nn', $glyph[self::WIDTH], $glyph[self::LSB]);
            $loca   .= ($this->indexToLocFormat == 0) ? pack('n', $offset / 2) : pack('N', $offset);
            $offset += $this->subsettedGlyphs[$id][self::LENGTH];
        }
        $this->setTable('hmtx', $hmtx);

        $loca .= ($this->indexToLocFormat == 0) ? pack('n', $offset / 2) : pack('N', $offset);
        $this->setTable('loca', $loca);

        return $this->buildGlyf();
    }


    /**
     * Build glyph-table
     *
     * @param  string $data
     * @return Stream
     */
    protected function buildGlyf($data = '')
    {
        foreach ((array)$this->subsettedGlyphs as $glyph) {
            $this->seek($this->getMarker('glyf') + $glyph[self::OFFSET]);
            $glyphData = ($glyph[self::LENGTH] > 0) ? $this->read($glyph[self::LENGTH]) : '';

            if (isset($glyph[self::COMPONENTS])) {
                foreach ($glyph[self::COMPONENTS] as $offset => $cid) {
                    $ssid = $this->subsettedGlyphs[$cid][self::SSID];
                    $glyphData = substr_replace($glyphData, pack('n', $ssid), $offset, 2);
                }
            }
            $data .= $glyphData;
        }
        $this->setTable('glyf', $data);
        return $this->buildPost();
    }

    /**
     * Build post-table
     *
     * @return Stream
     */
    protected function buildPost()
    {
        $this->seek('post');
        if ($this->glyphNames) {
            $numNames = 0;
            $names    = '';
            $data     = $this->read(2 * 4 + 2 * 2 + 5 * 4);
            $data    .= pack('n', count($this->subsettedGlyphs));

            foreach ((array)$this->subsettedGlyphs as $glyph) {
                if (is_string($glyph[self::NAME])) {
                    $data  .= pack('n', 258 + $numNames);
                    $names .= chr(strlen($glyph[self::NAME])) . $glyph[self::NAME];
                    $numNames++;
                } else {
                    $data .= pack('n', $glyph[self::NAME]);
                }
            }
            $data .= $names;
        } else {
            $this->skip(4);
            $data  = "\x00\x03\x00\x00";
            $data .= $this->read(4 + 2 * 2 + 5 * 4);
        }
        $this->setTable('post', $data);
        return $this->buildFont();
    }

    /**
     * Build subsetted font offset table and return stream object.
     *
     * @return Stream
     */
    protected function buildFont()
    {
        $tags = [];
        foreach (['cmap', 'cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'post', 'prep'] as $tag) {
            if (isset($this->fontTables[$tag])) {
                $tags[] = $tag;
            }
        }

        $checkSums = null;
        $numTables = count($tags);
        $offset    = 12 + 16 * $numTables;

        $searchRange   = pow(2, floor(log(count($tags)) / log(2))) * 16;
        $entrySelector = floor(log($numTables) / log(2));
        $rangeShift    = $numTables * 16 - $searchRange;
        $offsetTable   = pack('nnnnnn', 1, 0, $numTables, $searchRange, $entrySelector, $rangeShift);

        foreach ($tags as $tag) {
            if (!isset($this->fontTables[$tag][self::DATA])) {
                $this->loadTable($tag);
            }
            $table =& $this->fontTables[$tag];
            $table[self::OFFSET] = $offset;

            $offsetTable .= $tag . $table[self::CHECKSUM] . pack('NN', $table[self::OFFSET], $table[self::LENGTH]);
            $checkSums   .= $table[self::CHECKSUM];
            $offset      += strlen($table[self::DATA]);
        }

        $this->subsetStream->write($offsetTable);
        $checkSums = $this->checkSum($offsetTable) . $checkSums;

        $a    = unpack('n2', $this->checkSum($checkSums));
        $high = 0xB1B0 + ($a[1] ^ 0xFFFF);
        $low  = 0xAFBA + ($a[2] ^ 0xFFFF) + 1;

        $checkSumAdjustment = pack('nn', $high + ($low >> 16), $low);
        $head =& $this->fontTables['head'][self::DATA];
        $head = substr_replace($head, $checkSumAdjustment, 8, 4);

        foreach ($tags as $tag) {
            $this->subsetStream->write($this->fontTables[$tag][self::DATA]);
        }

        return $this->subsetStream;
    }

    /**
     * Set table data
     *
     * @param  $tag
     * @param  $data
     * @return void
     */
    protected function setTable($tag, $data)
    {
        $length = strlen($data);
        if (($length % 4) > 0) {
            $data = str_pad($data, $length + 4 - ($length % 4), "\x00");
        }
        $this->fontTables[$tag][self::DATA]     = $data;
        $this->fontTables[$tag][self::LENGTH]   = $length;
        $this->fontTables[$tag][self::CHECKSUM] = $this->checkSum($data);
    }

    /**
     * Load table
     *
     * @param $tag
     * @return void
     */
    protected function loadTable($tag)
    {
        $this->seek($tag);
        $length = $this->fontTables[$tag][self::LENGTH];
        if (($length % 4) > 0) {
            $length += 4 - ($length % 4);
        }
        $this->fontTables[$tag][self::DATA] = $this->read($length);
    }

    /**
     * Calculate table-data checksum
     *
     * @param         $data
     * @param  int    $high
     * @param  int    $low
     * @return string
     */
    protected function checkSum($data, $high = 0, $low = 0)
    {
        for ($i = 0; $i < strlen($data); $i += 4) {
            $high += (ord($data[$i]) << 8) + ord($data[$i + 1]);
            $low  += (ord($data[$i + 2]) << 8) + ord($data[$i + 3]);
        }
        return pack('nn', $high + ($low >> 16), $low);
    }
}
