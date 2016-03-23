<?php
namespace bubach\PdfBuilder\Core;

use bubach\PdfBuilder\PdfDocument;

class PdfFooter {

    /**
     * @var callable
     */
    protected $_footerCallback;

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
     * Construct footer instance
     *
     * @param  PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument)
    {
        $this->_pdfDocument = $pdfDocument;
    }

    /**
     * Set footer callback, in form
     *      array($this, 'footermethod')
     *
     * @param callable $callback
     */
    public function setFooter($callback)
    {
        $this->_footerCallback = $callback;
    }

    /**
     * Call the footer callback with PdfPage reference,
     * first make data copy - then restore with proper
     * getters and setters if available.
     *
     * @return PdfDocument
     */
    public function outputFooter()
    {
        $this->_dataCopy = $this->_pdfDocument->data;

        if (is_callable($this->_footerCallback)) {
            call_user_func($this->_footerCallback, $this->_pdfDocument);
        }

        foreach ($this->_dataCopy as $key => $value) {
            if ($value !== $this->_pdfDocument->data[$key]) {
                $method = "set".ucfirst($key);
                $this->_pdfDocument->$method($value);
            }
        }

        return $this->_pdfDocument;
    }

}