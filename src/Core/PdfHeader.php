<?php
namespace PdfBuilder\Core;

use PdfBuilder\PdfDocument;

class PdfHeader
{

    /**
     * @var callable
     */
    protected $_headerCallback;

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * Document settings backup array.
     *
     * @var array
     */
    protected $_dataCopy = array();

    /**
     * Construct header instance
     *
     * @param  PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument)
    {
        $this->_pdfDocument = $pdfDocument;
    }

    /**
     * Set header callback, in form
     *      array($this, 'headermethod')
     *
     * @param callable $callback
     */
    public function setHeader($callback)
    {
        $this->_headerCallback = $callback;
    }

    /**
     * Call the header callback with PdfPage reference,
     * first make data copy - then restore with proper
     * getters and setters if available.
     *
     * @return PdfDocument
     */
    public function outputHeader()
    {
        $this->_pdfDocument->data['inHeaderOrFooter'] = true;

        if (is_callable($this->_headerCallback)) {
            call_user_func($this->_headerCallback, $this->_pdfDocument);
        }

        if (method_exists($this->_pdfDocument, 'Header')) {
            call_user_func(array($this->_pdfDocument, 'Header'));
        }

        $this->_pdfDocument->data['inHeaderOrFooter'] = false;
        return $this->_pdfDocument;
    }
}
