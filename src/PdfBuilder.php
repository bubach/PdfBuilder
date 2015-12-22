<?php
namespace bubach\PdfBuilder;

use bubach\PdfBuilder\PdfPage;
use bubach\PdfBuilder\PdfText;
use bubach\PdfBuilder\PdfShape;
use bubach\PdfBuilder\PdfImage;
use bubach\PdfBuilder\PdfException;

class PdfBuilder {

    /** @var int Current page number */
    private $_currPage = 0;

    /** @var int Global number of pdf objects */
    private $_pdfObjects = 2;

    /** @var array Object offsets in output buffer */
    private $_objectOffsets = array();

    /** @var string PDF output buffer */
    private $_outBuffer = '';

    /** @var array PDF pages */
    private $_pages = array();

    /** @var int Current document state */
    private $_currState = 1;

    /** @var string PDF version used */
    private $_pdfVersion = '1.3';

    /** constants for PDF state */
    const STATE_END_PAGE = 1;
    const STATE_NEW_PAGE = 2;
    const STATE_END_DOC  = 3;

    /**
     * @var PdfPage instance
     */
    private $_pdfPage;

    /**
     * @var PdfText inctance
     */
    private $_pdfText;

    /**
     * @var PdfShape instance
     */
    private $_pdfShape;

    /**
     * @var PdfImage instance
     */
    private $_pdfImage;

    /**
     * PDFgen constructor
     *
     * @param string $orientation
     * @param string $unit
     * @param string $size
     */
    public function __construct($orientation='P', $unit='mm', $size='A4') {

        $this->_dochecks();

        $this->_pdfPage  = new PdfPage($this);
        $this->_pdfText  = new PdfText($this);
        $this->_pdfShape = new PdfShape($this);
        $this->_pdfImage = new PdfImage($this);

        if (defined('PDFGEN_FONTPATH')) {
            $this->_pdfText->setFontPath(FPDF_FONTPATH);
            if (substr($this->_pdfText->getFontPath(), -1) != '/' && substr($this->_pdfText->getFontPath(), -1) != '\\') {
                $this->_pdfText->setFontPath($this->_pdfText->getFontPath().'/');
            }
        } elseif (is_dir(dirname(__FILE__).'/font')) {
            $this->_pdfText->setFontPath(dirname(__FILE__).'/font/');
        } else {
            $this->_pdfText->setFontPath('');
        }
    }

    /**
     * Some initial checks
     *
     * @throws PdfException
     */
    private function _dochecks()
    {
        if (sprintf('%.1F', 1.0) != '1.0') {
            throw PdfException('This version of PHP is not supported');
        }

        if (!function_exists('mb_strlen')) {
            throw PdfException('mbstring extension is not available');
        }

        if (ini_get('mbstring.func_overload') & 2) {
            throw PdfException('mbstring overloading must be disabled');
        }

        if (get_magic_quotes_runtime() && version_compare(PHP_VERSION, '5.3.0', '<')) {
            @set_magic_quotes_runtime(0);
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

        $dest = strtoupper($dest);

        if (empty($dest)) {
            if (empty($name)) {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }

        switch ($dest) {
            case 'I':
                $this->_checkoutput();

                if (PHP_SAPI != 'cli') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->_outBuffer;
                break;
            case 'D':
                $this->_checkoutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->_outBuffer;
                break;
            case 'F':
                $f = fopen($name,'wb');
                if (!$f) {
                    throw PdfException('Unable to create output file: '.$name);
                }
                fwrite($f, $this->_outBuffer, strlen($this->_outBuffer));
                fclose($f);
                break;
            case 'S':
                return $this->_outBuffer;
            default:
                throw PdfException('Incorrect output destination: '.$dest);
        }
        return '';
    }

    /**
     * Check if we can output PDF
     *
     * @throws PdfException
     */
    private function _checkoutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                throw PdfException("Some data has already been outputted, can't send PDF file (output started at $file:$line)");
            }
        }

        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                ob_clean();
            } else {
                throw PdfException("Some data has already been outputted, can't send PDF file");
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