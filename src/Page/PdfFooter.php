<?php
namespace bubach\PdfBuilder\Page;

class PdfFooter {

    /**
     * @var callable
     */
    protected $_footerCallback;

    /**
     * @var PdfPage
     */
    protected $_pdfPage;

    /**
     * Page/Footer settings, in array for easy plugin usage.
     *
     * @var array
     */
    protected $_data = array();

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
     * Call the footer callback with PdfPage reference
     */
    public function outputFooter()
    {
        if (is_callable($this->_footerCallback)) {
            call_user_func($this->_footerCallback, $this->_pdfDocument);
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