<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Stream\Stream;
use PdfBuilder\Document;

class CosStructure
{

    /**
     * @var int Object ID
     */
    public $objectId;

    /**
     * @var array Direct/inline objects in this structure.
     */
    public $directObjects = [];

    /**
     * @var CosStructure[] Indirect/referenced objects in this structure.
     */
    public $indirectObjects = [];

    /**
     * @var Stream Content stream, usually with compression filter.
     */
    protected $stream;

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
     * Add stream data
     *
     * @param $data
     */
    public function addStreamData($data)
    {
        if (empty($this->stream) || $data instanceof Stream) {
            $this->stream = (($data instanceof Stream) ? $data : new Stream());

            $this->setValue('Length', function() {
                return $this->stream->getSize();
            });
        }
        if (is_string($data)) {
            $this->stream->writeString($data);
        }
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
     * Adds/overwrites an name entry to the COS object.
     *
     * @param  string $key   The name key
     * @param  string $value The name value, shown as "/Value"
     * @return string
     */
    public function setName($key, $value)
    {
        if (!empty($key)) {
            return $this->directObjects['/' . $key] = '/' . $this->escapeValue($value);
        }
        return $this->directObjects[] = '/' . $this->escapeValue($value);
    }

    /**
     * Adds/overwrites an string entry to the COS object.
     *
     * @param  string $key   The value key
     * @param  string $value The string value
     * @return string
     */
    public function setString($key, $value)
    {
        if (!empty($key)) {
            return $this->directObjects['/' . $key] = '(' . $this->escapeValue($value) . ')';
        }
        return $this->directObjects[] = '(' . $this->escapeValue($value) . ')';
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
        if (!empty($key)) {
            return $this->directObjects['/' . $key] = $value;
        }
        return $this->directObjects[] = $value;
    }

    /**
     * Add an array Name type value or values if provided with an
     * array. Key is optional and not part of output. Can also
     * be an array for one to one key => value mapping.
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
        $count = (isset($this->directObjects[$name]) ? count($this->directObjects[$name]) : 0);
        return (isset($this->directObjects[$name][1]) ? count($this->directObjects[$name][1]) : $count);
    }

    /**
     * Check if a named direct object is available.
     *
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return (isset($this->directObjects[$name]) ? true : false);
    }

    /**
     * Get a named direct object if available.
     *
     * @param $name
     * @return bool
     */
    public function get($name)
    {
        return (isset($this->directObjects[$name]) ? trim($this->directObjects[$name], '/') : null);
    }

    /**
     * Check if any direct objects are available.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return (count($this->directObjects) > 0) ? false : true;
    }

    /**
     * And an direct inline object or array entry
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

        if (!isset($this->directObjects[$object]) || !$add) {
            $this->directObjects[$object] = [$startDelimiter, [], $endDelimiter];
        }

        foreach ((array)$value as $v) {
            $v = (($valueType == 'name') ? '/' . $this->escapeValue($v) : $v);
            $v = (($valueType == 'string') ? '(' . $this->escapeValue($v) . ')' : $this->escapeValue($v));
            $this->directObjects[$object][1][] = $v;
        }
    }

    /**
     * Process the structure's direct cos-objects to form
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
     * Get array of streams to output in order for full PDF.
     *
     * @param  null|Document $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        if ($document instanceof Document && !empty($this->indirectObjects)) {
            foreach ($this->indirectObjects as $object) {
                if (empty($object->objectId)) {
                    $document->add($object);
                }
            }
        }

        $stream = new Stream();
        $stream->writeString("\n{$this->objectId} 0 obj\n");
        $stream->writeString("<<\n");

        $stream->writeString($this->process($this->directObjects));
        $stream->writeString("\n>>\n");

        if ($this->stream instanceof Stream) {
            $stream->writeString("stream\n");
            $stream->seek(0);

            $streams[] = $stream;
            $stream = new Stream();

            $this->stream->seek(0);
            $streams[] = $this->stream;

            $stream->writeString("endstream\n");
        }

        $stream->writeString("endobj");
        $stream->seek(0);
        $streams[] = $stream;

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
