<?php
namespace bubach\PdfBuilder;

use bubach\PdfBuilder\Page\PdfPage;
use bubach\PdfBuilder\Objects\PdfText;
use bubach\PdfBuilder\Objects\PdfShape;
use bubach\PdfBuilder\Objects\PdfImage;
use bubach\PdfBuilder\Exception\PdfException;

class PdfDocument {

    /** @var int Current page number */
    protected $_currPage = 0;

    /** @var int Global number of pdf objects */
    protected $_pdfObjects = 2;

    /** @var array Object offsets in output buffer */
    protected $_objectOffsets = array();

    /** @var string PDF output buffer */
    protected $_outBuffer = '';

    /** @var array PDF pages */
    protected $_pages = array();

    /** @var int Current document state */
    protected $_currState = 1;

    /** @var string PDF version used */
    protected $_pdfVersion = '1.3';

    /** constants for PDF state */
    const STATE_END_PAGE = 1;
    const STATE_NEW_PAGE = 2;
    const STATE_END_DOC  = 3;

    /**
     * @var PdfText instance
     */
    protected $_pdfText;

    /**
     * @var PdfShape instance
     */
    protected $_pdfShape;

    /**
     * @var PdfImage instance
     */
    protected $_pdfImage;

    /**
     * PdfBuilder constructor
     *
     * @param string $orientation
     * @param string $unit
     * @param string $size
     * @param null $fontpath
     */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $fontpath = null)
    {
        $this->_doChecks();
        $this->_pdfText  = new PdfText($this, $fontpath);
        $this->_pdfShape = new PdfShape($this);
        $this->_pdfImage = new PdfImage($this);
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
     * close the document
     */
    public function close()
    {
        if ($this->_currState == self::STATE_END_DOC) {
            return;
        }

        if ($this->_currPage == 0) {
            //$this->addPage();
        }
        /*
        $this->_inFooter = true;
        $this->footer();
        $this->_inFooter = false;

        $this->_endpage();
        $this->_enddoc();
        */
    }

    /**
     * Get PDF shape object
     *
     * @return PdfShape
     */
    public function shape()
    {
        return $this->_pdfShape;
    }

    /**
     * Get PdfText object
     *
     * @return PdfText
     */
    public function text()
    {
        return $this->_pdfText;
    }

    /**
     *
     */
    public function page($number = null)
    {
        $number = empty($number) ? $this->getCurrPageNo() : $number;
        return $this->_pages[$number];
    }

    /**
     * Get PdfImage object
     *
     * @return PdfImage
     */
    public function image()
    {
        return $this->_pdfImage;
    }

    /**
     * Add new PDF page to document
     *
     * @param string $orientation
     * @param string $size
     */
    public function addPage($orientation='', $size='')
    {

        //         $this->_pdfPage  = new PdfPage($this, $orientation, $unit, $size);

        // Start a new page
        if($this->state==0)
            $this->Open();
        $family = $this->FontFamily;
        $style = $this->FontStyle.($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;
        if($this->page>0)
        {
            // Page footer
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            // Close page
            $this->_endpage();
        }
        // Start new page
        $this->_beginpage($orientation,$size);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->k));
        // Set font
        if($family)
            $this->SetFont($family,$style,$fontsize);
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
    }

    /**
     * Output the PDF, with support for IE contype request
     *
     * @param  string $name
     * @param  string $dest
     * @throws PdfException
     * @return string
     */
    public function output($name = '', $dest = '')
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
            header('Content-Type: application/pdf');
            exit;
        }

        if ($this->_currState < self::STATE_END_DOC) {
            $this->close();
        }

        if (empty($dest)) {
            if (empty($name)) {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }

        switch (strtoupper($dest)) {
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
                throw new PdfException('Incorrect output destination: '.$dest);
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
        return call_user_func_array([$this->_pdfPage, $method], $parameters);
    }

}