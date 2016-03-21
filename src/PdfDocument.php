<?php
namespace bubach\PdfBuilder;

use bubach\PdfBuilder\Page\PdfPage;
use bubach\PdfBuilder\Exception\PdfException;

class PdfDocument {

    /**
     * @var int Current page number
     */
    protected $_currPage = 0;

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
    protected $_currState = 1;

    /**
     * @var string PDF version used
     */
    protected $_pdfVersion = '1.3';

    /**
     * constants for PDF state
     */
    const STATE_END_PAGE = 1;
    const STATE_NEW_PAGE = 2;
    const STATE_END_DOC  = 3;

    /**
     * @var string
     */
    protected $_stdUnit = 'mm';

    /**
     * @var string
     */
    protected $_stdOrientation = 'P';

    /**
     * @var string
     */
    protected $_stdSize = 'A4';

    /**
     * @var string
     */
    protected $_fontPath = "";

    /**
     * @var array Non default page sizes
     */
    public $pageSizes = array();

    /**
     * @var bool
     */
    public $inHeader = false;

    /**
     * @var bool
     */
    public $inFooter = false;

    /**
     * Array holding plugin objects & methods
     * preset for core plugins.
     *
     * @var array
     */
    public $plugins = array(
        'addImage'     => 'PdfImage',
        'setFont'      => 'PdfText',
        'addText'      => 'PdfText',
        'addCircle'    => 'PdfShape',
        'addRectangle' => 'PdfShape',
    );

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
        $this->setStdUnit($unit);
        $this->setStdOrientation($orientation);
        $this->setStdSize($size);
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
     * @return string
     */
    public function getFontPath()
    {
        return $this->_fontPath;
    }

    /**
     * @param string $fontPath
     */
    public function setFontPath($fontPath)
    {
        $this->_fontPath = $fontPath;
    }

    /**
     * @return string
     */
    public function getStdUnit()
    {
        return $this->_stdUnit;
    }

    /**
     * @param string $stdUnit
     */
    public function setStdUnit($stdUnit)
    {
        $this->_stdUnit = $stdUnit;
    }

    /**
     * @return string
     */
    public function getStdOrientation()
    {
        return $this->_stdOrientation;
    }

    /**
     * @param string $stdOrientation
     */
    public function setStdOrientation($stdOrientation)
    {
        $this->_stdOrientation = $stdOrientation;
    }

    /**
     * @return string
     */
    public function getStdSize()
    {
        return $this->_stdSize;
    }

    /**
     * @param string $stdSize
     */
    public function setStdSize($stdSize)
    {
        $this->_stdSize = $stdSize;
    }

    /**
     * Set document state
     *
     * @param $state
     */
    public function setState($state)
    {
        $this->_currState = $state;
    }

    /**
     * Get current document state
     *
     * @return int
     */
    public function getState()
    {
        return $this->_currState;
    }

    /**
     * @return int Current page number
     */
    public function getCurrPageNo()
    {
        return $this->_currPage;
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
     * close the document
     */
    public function close()
    {
        if ($this->_currState == self::STATE_END_DOC) {
            return;
        }
        if ($this->_currPage == 0) {
            $this->addPage();
        }

        $this->outputFooter();
        $this->_endpage();
        $this->_enddoc();
    }

    /**
     * Get a PDF page instance
     */
    public function getPage($number = null)
    {
        $number = empty($number) ? $this->getCurrPageNo() : $number;
        return isset($this->_pages[$number]) ? $this->_pages[$number] : $this->addPage();
    }

    /**
     * Add new PDF page to document
     *
     * @param  string  $orientation
     * @param  string  $unit
     * @param  string  $size
     * @return PdfPage
     */
    public function addPage($orientation = '', $unit = '',  $size = '')
    {
        $orientation = empty($orientation) ? $this->_stdOrientation : $orientation;
        $unit        = empty($unit) ? $this->_stdUnit : $unit;
        $size        = empty($size) ? $this->_stdSize : $size;
        $page        = new PdfPage($this, $orientation, $unit, $size);

        $this->_currState = self::STATE_END_PAGE;

        if ($this->_currPage > 0) {
            $this->outputFooter();
            $this->getPage()->_endPage();
        }

        $page->_beginPage($orientation, $size);
        $this->_out('2 J');
        $page->_lineWidth = $this->getPage()->_lineWidth;
        $this->_out(sprintf('%.2F w',$this->getPage()->_lineWidth * $this->getPage()->_scaleFactor));

        $page->setData($this->getPage()->getData());
        // Set colors
        $this->DrawColor = $dc;
        if($dc!='0 G')
            $this->_out($dc);
        $this->FillColor = $fc;
        if($fc!='0 g')
            $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        // Page header
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
        // Restore line width
        if($this->LineWidth!=$lw)
        {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w',$lw*$this->k));
        }
        // Restore font
        if($family)
            $this->SetFont($family,$style,$fontsize);
        // Restore colors
        if($this->DrawColor!=$dc)
        {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if($this->FillColor!=$fc)
        {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        $this->_pages[]  = $page;
        $this->_currPage = count($this->_pages);

        return $page;
    }

    /**
     * Begin a new object
     */
    function _newobj()
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
        if ($this->_currState == self::STATE_NEW_PAGE) {
            $this->getPage()->outBuffer .= $s."\n";
        } else {
            $this->_outBuffer .= $s."\n";
        }
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
        if ($this->_currState < self::STATE_END_DOC) {
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
            return call_user_func_array(array($className, $method), $parameters);
        } else {
            return call_user_func_array([$this->getPage(), $method], $parameters);
        }
    }

}