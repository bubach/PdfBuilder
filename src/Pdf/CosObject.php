<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Document;

class CosObject
{

    /**
     * @var int Object ID
     */
    public $objectId;

    /**
     * @var array Entries in this object.
     */
    protected $entries = [];

    /**
     * Constructor.
     *
     * @param null|string $type
     * @param null        $parent
     */
    public function __construct($type = null, $parent = null)
    {
        if (is_string($type)) {
            $this->setName('Type', $type);
        }
        if ($parent instanceof self) {
            $this->setValue('Parent', $parent->getLazyReference());
        }
    }

    /**
     * Get object reference
     *
     * @return string
     */
    public function getReference()
    {
        return $this->objectId . ' 0 R';
    }

    /**
     * For getting an object reference as late as possible,
     * evaluated in the getStreams method.
     *
     * @return callable
     */
    public function getLazyReference()
    {
        return function() {
            return $this->getReference();
        };
    }

    /**
     * Escape the value
     *
     * @param $value
     * @return mixed
     */
    public function escapeValue($value)
    {
        if (is_string($value)) {
            return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $value);
        }
        return $value;
    }

    /**
     * Adds/overwrites an name entry to the content object.
     *
     * @param  string $key   The name key
     * @param  string $value The name value, shown as "/Value"
     * @return string
     */
    public function setName($key, $value)
    {
        return $this->entries['/' . $key] = '/' . $this->escapeValue($value);
    }

    /**
     * Adds/overwrites an string entry to the content object.
     *
     * @param  string $key   The value key
     * @param  string $value The string value
     * @return string
     */
    public function setString($key, $value)
    {
        return $this->entries['/' . $key] = '(' . $this->escapeValue($value) . ')';
    }

    /**
     * Set value of type int, real or reference
     *
     * @param  string $key   The value key
     * @param  mixed  $value
     * @return mixed
     */
    public function setValue($key, $value)
    {
        return $this->entries['/' . $key] = $value;
    }

    /**
     * Add an array Name type value or values if
     * provided with an array
     *
     * @param string       $array
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayName($array, $value, $add = false)
    {
        $this->setMultipleValue('array', 'name', $array, $value, $add);
    }

    /**
     * Add an array string type value or values if
     * provided with an array
     *
     * @param string       $array
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayString($array, $value, $add = false)
    {
        $this->setMultipleValue('array', 'string', $array, $value, $add);
    }

    /**
     * Add array entry in integer, real or reference
     * format. No formatting required.
     *
     * @param string       $array
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayValue($array, $value, $add = false)
    {
        $this->setMultipleValue('array', 'value', $array, $value, $add);
    }

    /**
     * Add an object Name type value or values if
     * provided with an array
     *
     * @param string       $object
     * @param string|array $value
     * @param bool         $add
     */
    public function setObjectName($object, $value, $add = false)
    {
        $this->setMultipleValue('object', 'name', $object, $value, $add);
    }

    /**
     * Add an object string type value or values if
     * provided with an array
     *
     * @param string       $object
     * @param string|array $value
     * @param bool         $add
     */
    public function setObjectString($object, $value, $add = false)
    {
        $this->setMultipleValue('object', 'string', $object, $value, $add);
    }

    /**
     * Add object value in integer, real or reference
     * format. No formatting required.
     *
     * @param string       $object
     * @param string|array $value
     * @param bool         $add
     */
    public function setObjectValue($object, $value, $add = false)
    {
        $this->setMultipleValue('object', 'value', $object, $value, $add);
    }

    /**
     * Get the count from an array or object entry
     *
     * @param  $name
     * @return array
     */
    public function getCount($name)
    {
        $count = (isset($this->entries[$name]) ? count($this->entries[$name]) : 0);
        return (isset($this->entries[$name][1]) ? count($this->entries[$name][1]) : $count);
    }

    /**
     * Check if entry are available.
     *
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return (isset($this->entries[$name]) ? true : false);
    }

    /**
     * Check if entries are available.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return (count($this->entries) > 0) ? true : false;
    }

    /**
     * And an inline object or array entry
     *
     * @param string $objectType
     * @param string $valueType
     * @param string $object
     * @param string $value
     * @param bool   $add
     */
    protected function setMultipleValue($objectType, $valueType, $object, $value, $add = false)
    {
        $startDelimiter = (($objectType == 'array') ? '[' : '<<');
        $endDelimiter = (($objectType == 'array') ? ']' : '>> ');

        if (!isset($this->entries[$object]) || !$add) {
            $this->entries[$object] = [$startDelimiter, [], $endDelimiter];
        }

        foreach ((array)$value as $v) {
            $v = (($valueType == 'name') ? '/' . $this->escapeValue($v) : $v);
            $v = (($valueType == 'string') ? '(' . $this->escapeValue($v) . ')' : $this->escapeValue($v));
            $this->entries[$object][1][] = $v;
        }
    }

    /**
     * Process the objects entries to form
     * array of output streams.
     *
     * @param  array  $entries
     * @return string
     */
    protected function process($entries = array())
    {
        $res = null;

        foreach ($entries as $key => $value) {
            if (is_array($value)) {
                $val = (empty($value[1]) ? "\n" : $this->process($value[1]));
                $res .= sprintf("\n/$key %s%s%s ", $value[0], $val, $value[2]);
            } elseif (is_callable($value) && !is_string($value)) {
                $res .= ((is_int($key)) ? '' : $key . ' ');
                $res .= sprintf("%s ", $value());
            } else {
                $res .= ((is_int($key)) ? '' : $key . ' ');
                $res .= sprintf("%s ", $value);
            }
        }

        return $res;
    }

    /**
     * Get array of streams that should be
     * outputted in order.
     *
     * Re-set entry for Parent in case the Id has
     * updated.
     *
     * @param  null|Document $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        if ($document instanceof Document && !empty($this->objects)) {
            foreach ($this->objects as $object) {
                if (empty($object->objectId)) {
                    $document->add($object);
                }
            }
        }

        $header = new Stream();
        $header->writeString("\n{$this->objectId} 0 obj\n");
        $header->writeString("<<\n");

        $header->writeString($this->process($this->entries));
        $streams[] = $header;

        $footer = new Stream();
        $footer->writeString("\n>>\n");
        $footer->writeString("endobj");
        $streams[] = $footer;

        return $streams;
    }

    /**
     * Get size of combined streams
     *
     * @return int
     */
    public function getSize()
    {
        $size = 0;
        foreach ($this->getStreams() as $stream) {
            $size += $stream->getSize();
        }
        return $size;
    }
}
