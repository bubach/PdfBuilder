<?php
namespace PdfBuilder\Plugins;

use PdfBuilder\PdfDocument;
use PdfBuilder\Exception\PdfException;

class PdfImage {

    /**
     * @var PdfDocument
     */
    private $_pdfBuilder;

    /**
     * @param PdfDocument $pdfBuilder
     */
    public function __construct(PdfDocument $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }

    /**
     * Put an image on the page
     *
     * @param  $file
     * @param  null $x
     * @param  null $y
     * @param  int $w
     * @param  int $h
     * @param  string $type
     * @param  string $link
     * @return PdfDocument
     * @throws PdfException
     */
    public function addImage($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
        $document       = $this->_pdfBuilder;
        $imageOutputter = $document->getOutputter()->getImageOutputter();
        
        if (!isset($imageOutputter->images[$file])) {
            if ($type == '') {
                $pos = strrpos($file, '.');
                if (!$pos) {
                    throw new PdfException('Image file has no extension and no type was specified: '.$file);
                }
                $type = substr($file, $pos + 1);
            }
            
            $type = strtolower($type);
            if ($type == 'jpeg') {
                $type = 'jpg';
            }

            $mtd = 'parse'.$type;
            if (!method_exists($imageOutputter, $mtd)) {
                throw new PdfException('Unsupported image type: '.$type);
            }
            $info      = $imageOutputter->$mtd($file);
            $info['i'] = count($imageOutputter->images) + 1;
            $imageOutputter->images[$file] = $info;
        } else {
            $info = $imageOutputter->images[$file];
        }

        if ($w == 0 && $h == 0) {
            $w = -96;
            $h = -96;
        }

        if ($w < 0) {
            $w = -$info['w'] * 72 / $w / $document->getScaleFactor();
        }
        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $document->getScaleFactor();
        }
        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }
        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        if ($y === null) {
            if ($document->getY() + $h > $document->getPageBreakTrigger() && !$document->getInHeaderOrFooter() && $document->acceptPageBreak()) {
                $x2 = $document->getX();
                $document->addPage($document->getOrientation(), $document->getCurPageSize());
                $document->setX($x2);
            }
            $y = $document->getY();
            $document->setY($y + $h);
        }

        if ($x === null) {
            $x = $document->getX();
        }
        $document->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $document->getScaleFactor(),
            $h * $document->getScaleFactor(),
            $x * $document->getScaleFactor(),
            ($document->getHeight() - ($y + $h)) * $document->getScaleFactor(),
            $info['i'])
        );

        if ($link) {
            $document->getPage()->link($x, $y, $w, $h, $link);
        }
        return $document;
    }

}