<?php
namespace bubach\PdfBuilder;

use bubach\PdfBuilder\Core\PdfPage;
use bubach\PdfBuilder\Exception\PdfException;

define('PDFBUILDER_VERSION','1.00');

class PdfDocument {

    /**
     * @var int Current page number
     */
    protected $_curPage = 0;

    /**
     * @var int Global number of pdf objects
     */
    protected $_pdfObjects = 2;

    /**
     * @var array Object offsets in output buffer
     */
    protected $_objectOffsets = array();

    /**
     * @var string PDF output buffer
     */
    protected $_outBuffer = '';

    /**
     * @var array PDF pages
     */
    protected $_pages = array();

    /**
     * @var int Current document state
     */
    protected $_curState = 1;

    /**
     * @var string PDF version used
     */
    protected $_pdfVersion = '1.3';

    /**
     * @var null Zoom display mode
     */
    protected $_zoomMode;

    /**
     * @var null Layout mode
     */
    protected $_layoutMode;

    /**
     * @var float
     */
    protected $_scaleFactor = 1;

    /**
     * @var string
     */
    protected $_aliasNbPages;

    /**
     * Internal document links
     *
     * @var array
     */
    public $internalLinks = array();

    /**
     * constants for PDF state
     */
    const STATE_END_PAGE = 1;
    const STATE_NEW_PAGE = 2;
    const STATE_END_DOC  = 3;

    /**
     * @var string
     */
    protected $_defSizeFormat = 'A4';

    /**
     * @var array
     */
    protected $_stdPageSize = array(
        'a3'     => array(841.89, 1190.55),
        'a4'     => array(595.28, 841.89),
        'a5'     => array(420.94, 595.28),
        'letter' => array(612, 792),
        'legal'  => array(612, 1008)
    );

    /**
     * @var array
     */
    protected $_defPageSize = array();

    /**
     * Non default page sizes
     *
     * @var array
     */
    protected $_pageSizes;

    /**
     * @var string
     */
    protected $_defOrientation = 'P';

    /**
     * @var string
     */
    protected $_fontPath = "";

    /**
     * Array holding plugin objects & methods
     * preset for core plugins.
     *
     * @var array
     */
    public $plugins = array(
        'addHeader'    => 'bubach\PdfBuilder\Core\PdfHeader',
        'outputHeader' => 'bubach\PdfBuilder\Core\PdfHeader',
        'addFooter'    => 'bubach\PdfBuilder\Core\PdfFooter',
        'outputFooter' => 'bubach\PdfBuilder\Core\PdfFooter',
        'addImage'     => 'bubach\PdfBuilder\Plugins\PdfImage',
        'setFont'      => 'bubach\PdfBuilder\Plugins\PdfText',
        'addText'      => 'bubach\PdfBuilder\Plugins\PdfText',
        'addCircle'    => 'bubach\PdfBuilder\Plugins\PdfShape',
        'addRectangle' => 'bubach\PdfBuilder\Plugins\PdfShape',
    );

    /**
     * Document settings, in array for easy plugin usage
     * and copy over to new page.
     *
     * @var array
     */
    public $data = array();

    /**
     * PdfBuilder constructor
     *
     * @param string $orientation
     * @param string $unit
     * @param string $size
     * @param null   $fontPath
     */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $fontPath = null)
    {
        $this->_doChecks();
        $this->setFontPath($fontPath);
        $this->setScaleFactor($unit);
        $this->setDefOrientation($orientation);
        $this->setDefSizeFormat($size);
        $this->setDisplayMode('default');
    }

    /**
     * Some initial checks
     *
     * @throws PdfException
     */
    private function _doChecks()
    {
        if (sprintf('%.1F', 1.0) != '1.0') {
            throw new PdfException('This version of PHP is not supported');
        }

        if (!function_exists('mb_strlen')) {
            throw new PdfException('mbstring extension is not available');
        }

        if (ini_get('mbstring.func_overload') & 2) {
            throw new PdfException('mbstring overloading must be disabled');
        }

        if (get_magic_quotes_runtime() && version_compare(PHP_VERSION, '5.3.0', '<')) {
            @set_magic_quotes_runtime(0);
        }
    }

    /**
     * Set unit/scale-factor
     *
     * @param  $unit
     * @return $this
     * @throws PdfException
     */
    public function setScaleFactor($unit)
    {
        switch ($unit) {
            case 'pt':
                $this->_scaleFactor = 1;
                break;
            case 'mm':
                $this->_scaleFactor = 72/25.4;
                break;
            case 'cm':
                $this->_scaleFactor = 72/2.54;
                break;
            case 'in':
                $this->_scaleFactor = 72;
                break;
            default:
                throw new PdfException('Incorrect unit: '.$unit);;
        }
        return $this;
    }

    /**
     * Get document scale-factor
     *
     * @return float
     */
    public function getScaleFactor()
    {
        return $this->_scaleFactor;
    }

    /**
     * Add non-default page-size
     *
     * @param  $wPt
     * @param  $hPt
     * @return $this
     */
    public function addPageSize($wPt, $hPt)
    {
        $this->_pageSizes[$this->_curPage] = array($wPt, $hPt);
        return $this;
    }

    /**
     * Get the standard page size(s)
     *
     * @param  $number
     * @return array|bool
     */
    public function getStdPageSize($number = false)
    {
        if ($number) {
            return isset($this->_stdPageSize[$number]) ? $this->_stdPageSize[$number] : false;
        }
        return $this->_stdPageSize;
    }

    /**
     * Set the default page size
     *
     * @param  $size
     * @return $this
     */
    public function setDefPageSize($size)
    {
        $this->_defPageSize = $size;
        return $this;
    }

    /**
     * Get default page-size
     *
     * @return array
     */
    public function getDefPageSize()
    {
        return $this->_defPageSize;
    }

    /**
     * @return string
     */
    public function getFontPath()
    {
        return $this->_fontPath;
    }

    /**
     * Set the font path
     *
     * @param  string $fontPath
     * @return $this
     */
    public function setFontPath($fontPath)
    {
        $this->_fontPath = $fontPath;
        return $this;
    }

    /**
     * Get default orientation
     *
     * @return string
     */
    public function getDefOrientation()
    {
        return $this->_defOrientation;
    }

    /**
     * Set default orientation
     *
     * @param  string $defOrientation
     * @return $this
     */
    public function setDefOrientation($defOrientation)
    {
        $this->_defOrientation = $defOrientation;
        return $this;
    }

    /**
     * Get default size format
     *
     * @return string
     */
    public function getDefSizeFormat()
    {
        return $this->_defSizeFormat;
    }

    /**
     * Set default size format
     *
     * @param string $defSizeFormat
     */
    public function setDefSizeFormat($defSizeFormat)
    {
        $this->_defSizeFormat = $defSizeFormat;
    }

    /**
     * Set document state
     *
     * @param $state
     */
    public function setState($state)
    {
        $this->_curState = $state;
    }

    /**
     * Get current document state
     *
     * @return int
     */
    public function getState()
    {
        return $this->_curState;
    }

    /**
     * @return int Current page number
     */
    public function getCurPageNo()
    {
        return $this->_curPage;
    }

    /**
     * Add plugin, will use all public methods except constructor
     *
     * $param string  Loaded class-name
     */
    public function addPlugin($className)
    {
        $methodNames = get_class_methods($className);
        foreach ($methodNames as $name) {
            if ($name != "__construct" && !isset($this->plugins[$name])) {
                $this->plugins[$name] = $className;
            }
        }
    }

    /**
     * Set display mode in viewer
     *
     * @param  $zoom
     * @param  string $layout
     * @throws PdfException
     */
    public function setDisplayMode($zoom, $layout = 'default')
    {
        $zoomModes   = array('fullpage', 'fullwidth', 'real', 'default');
        $layoutModes = array('single', 'continuous', 'two', 'default');

        if ( !is_string($zoom) || in_array($zoom, $zoomModes) ) {
            $this->_zoomMode = $zoom;
        } else {
            throw new PdfException('Incorrect zoom display mode: '.$zoom);
        }

        if ( in_array($layout, $layoutModes)) {
            $this->_layoutMode = $layout;
        } else {
            throw new PdfException('Incorrect layout display mode: '.$layout);
        }
    }

    /**
     * close the document
     */
    public function close()
    {
        if ($this->_curState == self::STATE_END_DOC) {
            return;
        }
        if ($this->_curPage == 0) {
            $this->addPage();
        }

        $this->outputFooter();
        $this->setState(self::STATE_END_PAGE);
        $this->_enddoc();
    }

    /**
     * Get a PDF page instance
     */
    public function getPage($number = null)
    {
        $number = empty($number) ? $this->getCurPageNo() : $number;
        return isset($this->_pages[$number - 1]) ? $this->_pages[$number - 1] : $this->addPage();
    }

    /**
     * Add new PDF page to document
     *
     * @param  string  $orientation
     * @param  string  $size
     * @return PdfPage
     */
    public function addPage($orientation = '',  $size = '')
    {
        $this->_curState = self::STATE_END_PAGE;

        if ($this->_curPage > 0) {
            $this->outputFooter();
            $this->setState(self::STATE_END_PAGE);

            $orientation = empty($orientation) ? $this->getPage()->getOrientation() : $orientation;
            $size        = empty($size) ? $this->getPage()->getCurPageSize() : $size;
            $page        = new PdfPage($this, $orientation, $size);

            $page->setData($this->getPage()->getData());
        } else {
            $orientation = empty($orientation) ? $this->_defOrientation : $orientation;
            $size        = empty($size) ? $this->_defSizeFormat : $size;
            $page        = new PdfPage($this, $orientation, $size);

            $this->setDefPageSize($page->getCurPageSize());
        }

        $this->_pages[] = $page;
        $this->_curPage++;
        $this->setState(self::STATE_NEW_PAGE);
        $this->_out('2 J');
        $this->outputHeader();

        return $page;
    }

    /**
     * Begin a new object
     */
    public function _newobj()
    {
        $this->_pdfObjects++;
        $this->_objectOffsets[$this->_pdfObjects] = strlen($this->_outBuffer);
        $this->_out($this->_pdfObjects.' 0 obj');
    }

    /**
     * Output stream
     *
     * @param $s
     */
    function _putstream($s)
    {
        $this->_out('stream');
        $this->_out($s);
        $this->_out('endstream');
    }

    /**
     * Output to buffer(s)
     *
     * @param $s
     */
    public function _out($s)
    {
        if ($this->_curState == self::STATE_NEW_PAGE) {
            $this->getPage()->outBuffer .= $s."\n";
        } else {
            $this->_outBuffer .= $s."\n";
        }
    }

    /**
     * Output document resources and close document
     */
    protected function _enddoc()
    {
        $this->_putHeader();
        $this->_putPages();
        $this->_putResources();

        $this->_putInfo();
        $this->_putCatalog();

        $o = strlen($this->_outBuffer);
        $this->_putXRef();
        $this->_putTrailer($o);
        $this->setState(self::STATE_END_DOC);
    }

    function _putXObjectDict()
    {
        $images = array(); // TODO: placeholder for $this->images

        foreach ($images as $image) {
            $this->_out('/I'.$image['i'].' '.$image['n'].' 0 R');
        }
    }

    protected function _putResourceDict()
    {
        $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');

        $fonts = array();// TODO: placeholder for $this->fonts

        foreach ($fonts as $font) {
            $this->_out('/F'.$font['i'].' '.$font['n'].' 0 R');
        }

        $this->_out('>>');
        $this->_out('/XObject <<');
        $this->_putxobjectdict();
        $this->_out('>>');
    }

    protected function _putResources()
    {
        //$this->_putfonts();
        //$this->_putimages();

        $this->_objectOffsets[2] = strlen($this->_outBuffer);
        $this->_out('2 0 obj');
        $this->_out('<<');
        $this->_putResourceDict();
        $this->_out('>>');
        $this->_out('endobj');
    }

    /**
     * Output page data
     */
    protected function _putPages()
    {
        $nb = $this->_curPage;

        if (!empty($this->_aliasNbPages)) {
            $alias = $this->UTF8ToUTF16BE($this->_aliasNbPages, false);
            $r     = $this->UTF8ToUTF16BE("$nb", false);

            for ($n = 1; $n <= $nb; $n++) {
                $this->getPage($n)->pageBuffer = str_replace($alias, $r, $this->getPage($n)->pageBuffer);
            }
            for ($n = 1; $n <= $nb; $n++) {
                $this->getPage($n)->pageBuffer = str_replace($this->_aliasNbPages, $nb, $this->getPage($n)->pageBuffer);
            }
        }

        if ($this->_defOrientation == 'P') {
            $wPt = $this->_defPageSize[0] * $this->_scaleFactor;
            $hPt = $this->_defPageSize[1] * $this->_scaleFactor;
        } else {
            $wPt = $this->_defPageSize[1] * $this->_scaleFactor;
            $hPt = $this->_defPageSize[0] * $this->_scaleFactor;
        }

        $filter = empty($this->_compress) ? '/Filter /FlateDecode ' : '';

        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');

            if (isset($this->_pageSizes[$n])) {
                $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->_pageSizes[$n][0], $this->_pageSizes[$n][1]));
            }

            $this->_out('/Resources 2 0 R');

            if (!empty($this->getPage($n)->pageLinks)) {
                $annots = '/Annots [';

                foreach($this->getPage($n)->pageLinks as $pl) {
                    $rect    = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';

                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
                    } else {
                        $l = $this->internalLinks[$pl[4]];
                        $h = isset($this->_pageSizes[$l[0]]) ? $this->_pageSizes[$l[0]][1] : $hPt;
                        $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $l[0], $h - $l[1] * $this->_scaleFactor);
                    }
                }
                $this->_out($annots.']');
            }

            if ($this->_pdfVersion > '1.3') {
                $this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->_out('/Contents '.($this->_pdfObjects + 1).' 0 R>>');
            $this->_out('endobj');

            $p = empty($this->_compress) ? gzcompress($this->getPage($n)->pageBuffer) : $this->getPage($n)->pageBuffer;
            $this->_newobj();
            $this->_out('<<'.$filter.'/Length '.strlen($p).'>>');
            $this->_putstream($p);
            $this->_out('endobj');
        }

        $this->_objectOffsets[1] = strlen($this->_outBuffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';

        for ($i = 0; $i < $nb; $i++) {
            $kids .= ( 3 + 2 * $i).' 0 R ';
        }
        $this->_out($kids.']');
        $this->_out('/Count '.$nb);
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->_out('>>');
        $this->_out('endobj');
    }

    /**
     * Output cross-references
     */
    protected function _putXRef()
    {
        $this->_out('xref');
        $this->_out('0 '.($this->_pdfObjects + 1));
        $this->_out('0000000000 65535 f ');

        for ($i = 1; $i <= $this->_pdfObjects; $i++) {
            $this->_out(sprintf('%010d 00000 n ', $this->_objectOffsets[$i]));
        }
    }

    /**
     * Output PDF header
     */
    protected function _putHeader()
    {
        $this->_out('%PDF-'.$this->_pdfVersion);
    }

    /**
     * Output PDF trailer
     *
     * @param $o
     */
    protected function _putTrailer($o)
    {
        $this->_out('trailer');
        $this->_out('<<');
        $this->_out('/Size '.($this->_pdfObjects + 1));
        $this->_out('/Root '.$this->_pdfObjects.' 0 R');
        $this->_out('/Info '.($this->_pdfObjects - 1).' 0 R');
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($o);
        $this->_out('%%EOF');
    }

    /**
     * Output document presentation information
     */
    protected function _putCatalog()
    {
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');

        if ($this->_zoomMode == 'fullpage') {
            $this->_out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->_zoomMode == 'fullwidth') {
            $this->_out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->_zoomMode == 'real') {
            $this->_out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->_zoomMode)) {
            $this->_out('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F',$this->_zoomMode / 100).']');
        }

        if ($this->_layoutMode == 'single') {
            $this->_out('/PageLayout /SinglePage');
        } elseif ($this->_layoutMode == 'continuous') {
            $this->_out('/PageLayout /OneColumn');
        } elseif ($this->_layoutMode == 'two') {
            $this->_out('/PageLayout /TwoColumnLeft');
        }

        $this->_out('>>');
        $this->_out('endobj');
    }

    /**
     * Output PDF document meta-information
     */
    protected function _putInfo()
    {
        $this->_newobj();
        $this->_out('<<');

        $this->_out('/Producer '.$this->_textstring('PdfBuilder '.PDFBUILDER_VERSION));
        if (!empty($this->title)) {
            $this->_out('/Title '.$this->_textstring($this->title));
        }
        if (!empty($this->subject)) {
            $this->_out('/Subject '.$this->_textstring($this->subject));
        }
        if (!empty($this->author)) {
            $this->_out('/Author '.$this->_textstring($this->author));
        }
        if (!empty($this->keywords)) {
            $this->_out('/Keywords '.$this->_textstring($this->keywords));
        }
        if (!empty($this->creator)) {
            $this->_out('/Creator '.$this->_textstring($this->creator));
        }
        $this->_out('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));

        $this->_out('>>');
        $this->_out('endobj');
    }

    /**
     * Format a text string
     *
     * @param  $s
     * @return string
     */
    protected function _textstring($s)
    {
        return '('.$this->_escape($s).')';
    }

    /**
     * Escape special characters in strings
     *
     * @param  $s
     * @return string
     */
    protected function _escape($s)
    {
        $s = str_replace('\\','\\\\',$s);
        $s = str_replace('(','\\(',$s);
        $s = str_replace(')','\\)',$s);
        $s = str_replace("\r",'\\r',$s);
        return $s;
    }

    /**
     * Output the PDF, with support for IE contype request
     *
     * @param  string $name
     * @param  string $destination
     * @throws PdfException
     * @return string
     */
    public function output($name = '', $destination = '')
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
            header('Content-Type: application/pdf');
            exit;
        }
        if ($this->_curState < self::STATE_END_DOC) {
            $this->close();
        }
        if (empty($destination)) {
            if (empty($name)) {
                $name        = 'doc.pdf';
                $destination = 'I';
            } else {
                $destination = 'F';
            }
        }

        switch (strtoupper($destination)) {
            case 'I':
                $this->_checkOutput();

                if (PHP_SAPI != 'cli') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->_outBuffer;
                break;
            case 'D':
                $this->_checkOutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->_outBuffer;
                break;
            case 'F':
                $f = fopen($name,'wb');
                if (!$f) {
                    throw new PdfException('Unable to create output file: '.$name);
                }
                fwrite($f, $this->_outBuffer, strlen($this->_outBuffer));
                fclose($f);
                break;
            case 'S':
                return $this->_outBuffer;
            default:
                throw new PdfException('Incorrect output destination: '.$destination);
        }
        return '';
    }

    /**
     * Check if we can output PDF
     *
     * @throws PdfException
     */
    private function _checkOutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                throw new PdfException("Some data has already been outputted, can't send PDF file (output started at $file:$line)");
            }
        }
        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                ob_clean();
            } else {
                throw new PdfException("Some data has already been outputted, can't send PDF file");
            }
        }
    }

    /**
     * get PDF output
     *
     * @return string
     */
    public function __toString() {
        return $this->output('', 'S');
    }

    /**
     * Magic getter, checks plugins first.
     *
     * @param  $name
     * @return string
     */
    public function __get($name)
    {
        $method    = "get".ucfirst($name);
        $className = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($className) {
            return call_user_func(array($className, $method));
        } else {
            return isset($this->data[$name]) ? $this->data[$name] : false;
        }
    }

    /**
     * Magic setter, checks plugins first
     *
     * @param  $name
     * @param  $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        $method    = "set".ucfirst($name);
        $className = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($className) {
            return call_user_func_array(array($className, $method), array($value));
        } else {
            $this->data[$name] = $value;
            return $this;
        }
    }

    /**
     * Call PdfPage functions
     *
     * @param  $method
     * @param  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $className = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($className) {
            $class = new $className($this);
            return call_user_func_array(array($class, $method), $parameters);
        } else {
            return call_user_func_array(array($this->getPage(), $method), $parameters);
        }
    }

}