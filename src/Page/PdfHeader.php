<?php
namespace bubach\PdfBuilder\Page;

class PdfHeader {

    /**
     * @var callable
     */
    protected $_headerCallback;

    /**
     * @var PdfPage
     */
    protected $_pdfDocument;

    /**
     * Page/Header settings, in array for easy plugin usage.
     *
     * @var array
     */
    protected $_data = array();

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
     * Call the footer callback with PdfPage reference
     */
    public function outputHeader()
    {
        if (is_callable($this->_headerCallback)) {
            call_user_func($this->_headerCallback, $this->_pdfDocument);
        }
        return $this->_pdfDocument;
    }

    /**
     * Set page data, used by plugins.
     *
     * @param  string|array $key
     * @param  mixed $value
     * @return PdfDocument
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->_data = $key;
        } else {
            $this->_data[$key] = $value;
        }
        return $this;
    }

    /**
     * Get page data from the object.
     *
     * @param  string          $key
     * @return string | array  $data
     */
    public function getData($key = null)
    {
        if ($key) {
            return isset($this->_data[$key]) ? $this->_data[$key] : null;
        } else {
            return $this->_data;
        }
    }

} 