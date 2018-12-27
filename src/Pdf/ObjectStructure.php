<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Stream\CompressionFilter;
use PdfBuilder\Stream\Stream;
use PdfBuilder\Document;

class ObjectStructure
{

    /**
     * @var int - Object ID
     */
    public $objectId;

    /**
     * @var array - Direct/inline objects in this structure.
     */
    public $directObjects = [];

    /**
     * @var ObjectStructure[] - Indirect/referenced objects in this structure.
     */
    public $indirectObjects = [];

    /**
     * @var Stream - A content stream, usually with compression filter.
     */
    protected $stream = null;

    /**
     * @var CompressionFilter - Active stream filter for compressing data.
     */
    protected $filter = null;

    /**
     * @var string Object type name
     */
    protected $objectType = null;

    /**
     * Constants for the different string types to be escaped
     */
    const STRING  = 1;
    const NAME    = 2;
    const COMMENT = 3;

    /**
     * Constructor. Automatically set dictionary type and parent
     * reference if provided.
     *
     * @param null|string $type
     * @param null        $parent
     */
    public function __construct($type = null, $parent = null)
    {
        if (is_string($type)) {
            $this->setName('Type', $type);
            $this->objectType = $type;
        }

        if ($parent instanceof self) {
            $this->setValue('Parent', $parent->getReference());
        }
    }

    /**
     * Get object reference, will return a callable for
     * lazy resolve when no object id is set or when param
     * $forceLazy is set to true.
     *
     * @param  bool            $forceLazy
     * @return string|callable
     */
    public function getReference($forceLazy = false)
    {
        if (empty($this->objectId) || $forceLazy) {
            return function() {
                return $this->getReference();
            };
        }

        return $this->objectId . ' 0 R';
    }

    /**
     * Get object type, convenience for objects
     * without custom subclass to check for.
     *
     * @return string
     */
    public function getType()
    {
        return $this->objectType;
    }

    /**
     * Add stream data to this structure.
     *
     * @param         $data
     * @param  bool   $compression
     * @return Stream
     */
    public function writeDataStream($data, $compression = true)
    {
        if (empty($this->stream) || $data instanceof Stream) {
            $this->stream = (($data instanceof Stream) ? $data : new Stream());
        }
        if ($compression && !$this->filter instanceof CompressionFilter) {
            $this->setArrayName('Filter', 'FlateDecode');

            if ($compression instanceof CompressionFilter) {
                $this->filter = $compression;
            } else {
                $this->filter = new CompressionFilter();
                $this->filter->addDeflateWriter($this->stream->getResource());
            }
        }
        if (is_string($data)) {
            $this->stream->write($data);
        }
        return $this->stream;
    }

    /**
     * Get the data stream, sets one first if not already done.
     *
     * @param  bool   $compression
     * @return Stream
     */
    public function getDataStream($compression = true)
    {
        return $this->writeDataStream(null, $compression);
    }

    /**
     * Escapes literal value.
     *
     * @param         $value
     * @param  string $type
     * @return mixed
     */
    public function escapeValue($value, $type = null)
    {
        if (is_string($value)) {
            $value = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $value);
        }

        switch ($type) {
            case self::STRING:
                return '(' . mb_convert_encoding($value, 'cp1252', 'UTF-8') . ')';
            case self::NAME:
                return '/' . $value;
            case self::COMMENT:
                return '%' . $value;
            case null:
            default:
                return $value;
        }
    }

    /**
     * Set any object without name object key.
     *
     * @param  string $value The comment string
     * @return string
     */
    public function setEntry($value)
    {
        return $this->directObjects[] = $this->escapeValue($value);
    }

    /**
     * Set comment object.
     *
     * @param  string $value The comment string
     * @return string
     */
    public function setComment($value)
    {
        return $this->directObjects[] = $this->escapeValue($value, self::COMMENT);
    }

    /**
     * Set named value of type int, real, bool, byte or reference.
     *
     * @param  string $key   The name key
     * @param  string $value The name value
     * @return string
     */
    public function setValue($key, $value)
    {
        return $this->directObjects[$key] = $this->escapeValue($value);
    }

    /**
     * Set named value of type name.
     *
     * @param  $key
     * @param  $value
     * @return string
     */
    public function setName($key, $value)
    {
        return $this->directObjects[$key] = $this->escapeValue($value, self::NAME);
    }

    /**
     * Sets named string value.
     *
     * @param  string $key   The value key
     * @param  string $value The string value
     * @return string
     */
    public function setString($key, $value)
    {
        return $this->directObjects[$key] = $this->escapeValue($value, self::STRING);
    }

    /**
     * Add one or many name objects to an array object. Set
     * add flag to true for appending value to the array.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayName($key, $value, $add = false)
    {
        $this->setMultipleValue(['[', ']'], self::NAME, $key, $value, $add);
    }

    /**
     * Add one or many string objects to an array object. Set
     * add flag to true for appending value to the array.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayString($key, $value, $add = false)
    {
        $this->setMultipleValue(['[', ']'], self::STRING, $key, $value, $add);
    }

    /**
     * Add one or many objects in bool, integer, real or reference format to an
     * array object. Set add flag to true for appending value to the array.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setArrayValue($key, $value, $add = false)
    {
        $this->setMultipleValue(['[', ']'], null, $key, $value, $add);
    }

    /**
     * Add one or many name objects to a dictionary object. Set
     * add flag to true for appending value to the dictionary.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setDictionaryName($key, $value, $add = false)
    {
        $this->setMultipleValue(['<<', '>>'], self::NAME, $key, $value, $add);
    }

    /**
     * Add one or many string objects to a dictionary object. Set
     * add flag to true for appending value to the dictionary.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setDictionaryString($key, $value, $add = false)
    {
        $this->setMultipleValue(['<<', '>>'], self::STRING, $key, $value, $add);
    }

    /**
     * Add one or many objects of type bool, integer, real or reference to a dictionary
     * object. Set add flag to true for appending value to the dictionary.
     *
     * @param string       $key
     * @param string|array $value
     * @param bool         $add
     */
    public function setDictionaryValue($key, $value, $add = false)
    {
        $this->setMultipleValue(['<<', '>>'], null, $key, $value, $add);
    }

    /**
     * Get count of member objects from an array or dictionary object.
     *
     * @param  $name
     * @return integer
     */
    public function getCount($name)
    {
        $count = (isset($this->directObjects[$name]) ? count($this->directObjects[$name]) : 0);
        return (isset($this->directObjects[$name][1]) ? count($this->directObjects[$name][1]) : $count);
    }

    /**
     * Check if name-value pair objects exists based on name object.
     *
     * @param  $name
     * @return bool
     */
    public function has($name)
    {
        return (isset($this->directObjects[$name]) ? true : false);
    }

    /**
     * Get a value object from name-value pair's name object as key.
     *
     * @param  $name
     * @return ObjectStructure|null
     */
    public function get($name)
    {
        return (isset($this->directObjects[$name]) ? trim($this->directObjects[$name], '/()%') : null);
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
     * Add or append objects to named array or dictionary object.
     *
     * @param string $delimiters
     * @param string $type
     * @param string $key
     * @param string $value
     * @param bool   $add
     */
    protected function setMultipleValue($delimiters, $type, $key, $value, $add = false)
    {
        if (!isset($this->directObjects[$key]) || !$add) {
            $this->directObjects[$key] = [$delimiters[0], [], $delimiters[1]];
        }

        foreach ((array)$value as $v) {
            $this->directObjects[$key][1][] = ($type !== null ? $this->escapeValue($v, $type) : $v);
        }
    }

    /**
     * Get indirect objects
     *
     * @return ObjectStructure[]
     */
    public function getIndirectObjects()
    {
        return $this->indirectObjects;
    }

    /**
     * Add object
     *
     * @param $object
     */
    public function add($object)
    {
        $this->indirectObjects[] = $object;
    }

    /**
     * Add orphan objects to the document and get assigned an object if,
     *
     * @param $document
     */
    protected function addIndirectObjects($document)
    {
        if ($document instanceof Document && !empty($this->indirectObjects)) {
            foreach ($this->getIndirectObjects() as $object) {
                if (empty($object->objectId)) {
                    $document->add($object);
                }
            }
        }
    }

    /**
     * Recursively get structure's direct objects as string.
     *
     * @param  array  $entries
     * @param  int    $indent
     * @return string
     */
    protected function process($entries = [], $indent = 0)
    {
        $res = null;
        $indent++;

        foreach ($entries as $key => $value) {
            if (!is_int($key)) {
                $res .= "\n" . str_repeat(' ', (4 * $indent));
                $res .= '/' . $key . ' ';
            }

            if (is_array($value)) {
                $res .= $value[0];
                $res .= trim($this->process($value[1]), $indent);
                $res .= $value[2];
                continue;
            }

            if (is_callable($value) && !is_string($value)) {
                $res .= $value();
            } else {
                $res .= $value;
            }
            if (is_numeric($key) && $key + 1 !== count($entries)) {
                $res .= ' ';
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
        $this->addIndirectObjects($document);

        $stream = new Stream();
        $stream->write("\n{$this->objectId} 0 obj\n");
        $stream->write("<<");

        if ($this->stream instanceof Stream && !$this->has('Length')) {
            if ($this->filter instanceof CompressionFilter) {
                $this->filter->removeFilter($this->stream->getResource());
            }
            $this->setValue('Length', $this->stream->getSize());
        }

        $stream->write($this->process($this->directObjects));
        $stream->write("\n>>\n");

        if ($this->stream instanceof Stream) {
            $stream->write("stream\n");
            $stream->seek(0);
            $streams[] = $stream;

            $this->stream->seek(0);
            $streams[] = $this->stream;

            $stream = new Stream();
            $stream->write("\nendstream\n");
        }
        $stream->write("endobj");
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
