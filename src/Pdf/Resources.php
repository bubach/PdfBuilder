<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Font\Font;

class Resources extends ObjectStructure
{

    /**
     * @var self Shared Resources instance between pages (for now)
     */
    protected static $instance;

    /**
     * @var Font[] Font objects shared between all Resources instances
     */
    protected $fonts = [];

    /**
     * @var array PDF xObjects
     */
    protected $xObjects = [];

    /**
     * @return Resources Get instance
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor setting default dictionary values
     *
     * @return self
     */
    public function __construct()
    {
        parent::__construct('Resources');

        $this->setArrayName('ProcSet', ['PDF', 'TEXT', 'ImageB', 'ImageC', 'ImageI']);
        $this->setDictionaryValue('Font', null);
        $this->setDictionaryValue('XObject', null);
    }

    /**
     * Get font string string, loading it if needed.
     *
     * @param         $fontName
     * @param  int    $fontSize
     * @param  string $style
     * @return        $this
     */
    public function setFont($fontName, $fontSize = 10, $style = '')
    {
        $fontNumber = false;

        foreach ($this->fonts as $i => $font) {
            if (strtolower($font->getNameStyle()) == strtolower($fontName . $style)) {
                $fontNumber = $i;
                break;
            }
        }

        if (!$fontNumber) {
            $font = new Font($fontName);
            $fontNumber = count($this->fonts) + 1;

            $this->fonts[$fontNumber] = $font;
            $this->add($font);

            $this->setDictionaryName('Font', sprintf('F%d', $fontNumber), true);
            $this->setDictionaryValue('Font', $font->getReference(), true);
        }

        return sprintf("BT /F%d %d Tf ET\n", $fontNumber, $fontSize);
    }

    /**
     *
     */
    public function getFont($fontName, $style = '')
    {
        //.. Return Font object which in turn can provide
        // Content-commands for setting font, stile, size, color and so on.
        // Used by Text-Content
    }

    /**
     * Get font streams
     */
    public function getStreams($document = null)
    {
        return parent::getStreams($document);
    }
}
