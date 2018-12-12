<?php
namespace PdfBuilder\Font;

use PdfBuilder\Exception\PdfException;
use PdfBuilder\Stream\Stream;
use Exception;

class FontParser extends Stream
{
    /**
     * @var array Font table information.
     */
    protected $fontTables = [];

    /**
     * @var int Font table information for internal use.
     */
    protected $numberOfHMetrics, $numGlyphs, $indexToLocFormat, $cMapSegCount;

    /**
     * @var bool Flag for if the font contains glyph names or not.
     */
    protected $glyphNames;

    /**
     * @var array
     */
    protected $subsettedChars, $subsettedGlyphs, $startCount, $endCount, $idDelta, $idRange = [];

    /**
     * @var int
     */
    public $unitsPerEm, $xMin, $yMin, $xMax, $yMax;

    /**
     * @var string
     */
    public $postScriptName = '';

    /**
     * @var bool
     */
    public $embeddable, $bold, $isFixedPitch;

    /**
     * @var int
     */
    public $typoAscender, $typoDescender, $capHeight, $italicAngle, $underlinePosition, $underlineThickness;

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
                'checkSum' => $this->read(4),
                'offset'   => $this->readULong(),
                'length'   => $this->readULong()
            ];
            $this->setMarker($tag, $this->fontTables[$tag]['offset']);
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
        $this->unitsPerEm = $this->readUShort();

        $this->skip(2 * 8);
        $this->xMin = $this->readShort();
        $this->yMin = $this->readShort();
        $this->xMax = $this->readShort();
        $this->yMax = $this->readShort();

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

            if ($nameId == 6) {
                $this->seek($this->getMarker('name') + $stringOffset + $offset);

                $this->postScriptName = $this->read($length);
                $this->postScriptName = str_replace(chr(0), '', $this->postScriptName);
                $this->postScriptName = preg_replace('|[ \[\](){}<>/%]|', '', $this->postScriptName);
                break;
            }
        }

        if ($this->postScriptName == '') {
            throw new PdfException('PostScript name not found');
        }
        return $this->parseOS2();
    }

    /**
     * OS/2 table, contains line spacing, font style and weight.
     *
     * @return $this
     */
    protected function parseOS2()
    {
        $this->seek('OS/2');
        $version = $this->readUShort();

        $this->skip(3 * 2);
        $fsType = $this->readUShort();
        $this->embeddable = ($fsType != 2) && (($fsType & 0x200) == 0);

        $this->skip(11 * 2 + 10 + 4 * 4 + 4);
        $fsSelection = $this->readUShort();
        $this->bold  = ($fsSelection & 32) != 0;

        $this->skip(2 * 2);
        $this->typoAscender  = $this->readShort();
        $this->typoDescender = $this->readShort();

        if ($version >= 2) {
            $this->skip(3 * 2 + 2 * 4 + 2);
            $this->capHeight = $this->readShort();
        } else {
            $this->capHeight = 0;
        }
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
        $version = $this->readULong();
        $this->italicAngle = $this->readShort();

        $this->skip(2);
        $this->underlinePosition  = $this->readShort();
        $this->underlineThickness = $this->readShort();
        $this->isFixedPitch       = ($this->readULong() != 0);
        $this->glyphNames         = ($version == 0x20000) ? true : false;

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
            'w'          => $advancedWith,
            'lsb'        => $lsb,
            'offset'     => $offset,
            'length'     => $length,
            'components' => $this->getGlyphComponents($offset),
            'name'       => $this->getGlyphName($index)
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
     * @param  $character
     * @return int
     */
    public function getCmapGlyphId($character)
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
                    if ($character[0] == $c) {
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
     * Subset font to characters from external
     * mapping file.
     *
     * @param  $encoding
     * @throws PdfException
     * @return Stream
     */
    public function subsetFont($encoding)
    {
        $encoding = preg_replace('/[^a-z0-9-_]/', '', $encoding);
        $mapFile = dirname(__FILE__) . '/../../maps/' . $encoding . '.map';

        if (!file_exists($mapFile)) {
            throw new PdfException('Unknown encoding (' . $encoding . ')');
        }
        $map = [];
        $resource = fopen($mapFile, 'r');

        while ($line = fgets($resource)) {
            if (preg_match('/^[\!\=]([0-9A-F]{2,})\s+U\+([0-9A-F]{2})([0-9A-F]{2})\s+([^\s]+)/', $line, $matches)) {
                $unicode = (hexdec($matches[2]) << 8) + hexdec($matches[3]);
                $map[hexdec($matches[1])] = array($unicode, $matches[4]);
            }
        }

        $this->addGlyph(0);
        $this->subsettedChars = [];

        foreach ($map as $char) {
            if ($glyphId = $this->getCmapGlyphId($char)) {
                $this->subsettedChars[$char[0]] = $glyphId;
                $this->addGlyph($glyphId);
            }
        }
        ksort($this->subsettedChars);

        return $this->buildCmap();
    }

    /**
     * Add glyph to subsetted font
     *
     * @param $id
     * @return void
     */
    public function addGlyph($id)
    {
        if (!isset($this->subsettedGlyphs[$id])) {
            $subsetCount = count($this->subsettedGlyphs);
            $this->subsettedGlyphs[$id] = $this->getGlyph($id);
            $this->subsettedGlyphs[$id]['ssid'] = $subsetCount;

            foreach ($this->subsettedGlyphs[$id]['components'] as $cid) {
                $this->addGlyph($cid);
            }
        }
    }

    /**
     * Build cmap-table
     *
     * @return string
     */
    protected function buildCmap()
    {
        $chars    = array_keys($this->subsettedChars);
        $segments = [];
        $segment  = [$chars[0], $chars[0]];

        for ($i = 1; $i < count($chars); $i++) {
            if ($chars[$i] > $segment[1] + 1) {
                $segments[] = $segment;
                $segment = [$chars[$i], $chars[$i]];
            } else {
                $segment[1]++;
            }
        }

        $segments[]    = $segment;
        $segments[]    = [0xFFFF, 0xFFFF];
        $entrySelector = 0;
        $glyphIdArray  = '';

        $segCount = $n = count($segments);
        $startCount = $endCount = $idDelta = $idRangeOffset = [];

        for ($i = 0; $i < $segCount; $i++) {
            list($start, $end) = $segments[$i];

            $startCount[] = $start;
            $endCount[]   = $end;

            if ($start != $end) {
                $idDelta[] = 0;
                $idRangeOffset[] = strlen($glyphIdArray) + ($segCount - $i) * 2;

                for ($c = $start; $c <= $end; $c++) {
                    $glyphId = $this->subsettedChars[$c];
                    $ssid    = $this->subsettedGlyphs[$glyphId]['ssid'];
                    $glyphIdArray .= pack('n', $ssid);
                }
            } else {
                if ($start < 0xFFFF) {
                    $glyphId = $this->subsettedChars[$start];
                    $ssid    = $this->subsettedGlyphs[$glyphId]['ssid'];
                } else {
                    $ssid = 0;
                }
                $idDelta[] = $ssid - $start;
                $idRangeOffset[] = 0;
            }
        }

        while ($n != 1) {
            $n = $n >> 1;
            $entrySelector++;
        }

        $searchRange = (1 << $entrySelector) * 2;
        $rangeShift  = 2 * $segCount - $searchRange;
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
     * @return string
     */
    protected function buildHheaHmtxLocaMaxp()
    {
        $this->loadTable('hhea');
        $this->loadTable('maxp');
        $count = pack('n', count($this->subsettedGlyphs));
        $hhea  = substr_replace($this->fontTables['hhea']['data'], $count, 4 + 15 * 2, 2);
        $maxp  = substr_replace($this->fontTables['maxp']['data'], $count, 4, 2);
        $this->setTable('hhea', $hhea);
        $this->setTable('maxp', $maxp);

        $hmtx = $loca = '';
        $offset = 0;

        foreach ((array)$this->subsettedGlyphs as $id => $glyph) {
            $hmtx   .= pack('nn', $glyph['w'], $glyph['lsb']);
            $loca   .= ($this->indexToLocFormat == 0) ? pack('n', $offset / 2) : pack('N', $offset);
            $offset += $this->subsettedGlyphs[$id]['length'];
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
     * @return string
     */
    protected function buildGlyf($data = '')
    {
        foreach ((array)$this->subsettedGlyphs as $glyph) {
            $this->seek($this->getMarker('glyf') + $glyph['offset']);
            $glyphData = ($glyph['length'] > 0) ? $this->read($glyph['length']) : '';

            if (isset($glyph['components'])) {
                foreach ($glyph['components'] as $offset => $cid) {
                    $ssid = $this->subsettedGlyphs[$cid]['ssid'];
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
     * @return string
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
                if (is_string($glyph['name'])) {
                    $data  .= pack('n', 258 + $numNames);
                    $names .= chr(strlen($glyph['name'])) . $glyph['name'];
                    $numNames++;
                } else {
                    $data .= pack('n', $glyph['name']);
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
     * Build subsetted font
     *
     * @return string
     */
    protected function buildFont()
    {
        $tags = [];
        foreach (['cmap', 'cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'post', 'prep'] as $tag) {
            if (isset($this->fontTables[$tag])) {
                $tags[] = $tag;
            }
        }

        $entrySelector = 0;
        $numTables = $n = count($tags);
        $offset = 12 + 16 * $numTables;

        foreach ($tags as $tag) {
             if (!isset($this->fontTables[$tag]['data'])) {
                $this->loadTable($tag);
            }
            $this->fontTables[$tag]['offset'] = $offset;//$this->setMarker($tag, $offset);
            $offset += strlen($this->fontTables[$tag]['data']);
        }

        while ($n != 1) {
            $n = $n >> 1;
            $entrySelector++;
        }

        $searchRange = 16 * (1 << $entrySelector);
        $rangeShift  = 16 * $numTables - $searchRange;
        $offsetTable = pack('nnnnnn', 1, 0, $numTables, $searchRange, $entrySelector, $rangeShift);

        foreach ($tags as $tag) {
            $table = $this->fontTables[$tag];
            $offsetTable .= $tag . $table['checkSum'] . pack('NN', $table['offset'], $table['length']);
        }

        $s = $this->checkSum($offsetTable);
        foreach ($tags as $tag) {
            $s .= $this->fontTables[$tag]['checkSum'];
        }

        $a    = unpack('n2', $this->checkSum($s));
        $high = 0xB1B0 + ($a[1] ^ 0xFFFF);
        $low  = 0xAFBA + ($a[2] ^ 0xFFFF) + 1;

        $checkSumAdjustment = pack('nn', $high + ($low >> 16), $low);
        $this->fontTables['head']['data'] = substr_replace($this->fontTables['head']['data'], $checkSumAdjustment, 8, 4);
        $font = $offsetTable;

        foreach ($tags as $tag) {
            $font .= $this->fontTables[$tag]['data'];
        }
        return $font;
    }

    /**
     * Set table data
     *
     * @param $tag
     * @param $data
     * @return void
     */
    protected function setTable($tag, $data)
    {
        $length = strlen($data);
        if (($length % 4) > 0) {
            $data = str_pad($data, $length + 4 - ($length % 4), "\x00");
        }
        $this->fontTables[$tag]['data'] = $data;
        $this->fontTables[$tag]['length'] = $length;
        $this->fontTables[$tag]['checkSum'] = $this->checkSum($data);
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
        $length = $this->fontTables[$tag]['length'];

        if (($length % 4) > 0) {
            $length += 4 - ($length % 4);
        }
        $this->fontTables[$tag]['data'] = $this->read($length);
    }

    /**
     * Calculate table-data checksum
     *
     * @param     $data
     * @param int $high
     * @param int $low
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
