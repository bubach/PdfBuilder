<?php
namespace PdfBuilder\Core;

use PdfBuilder\PdfDocument;

class PdfHeader {

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
        $this->_dataCopy = $this->_pdfDocument->data;

        if (is_callable($this->_headerCallback)) {
            call_user_func($this->_headerCallback, $this->_pdfDocument);
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