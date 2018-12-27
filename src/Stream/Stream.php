<?php
namespace PdfBuilder\Stream;

class Stream
{
    /**
     * @var resource Stream resource
     */
    protected $resource;

    /**
     * @var int Stream offset
     */
    protected $offset = 0;

    /**
     * @var array Named offsets for later lookup
     */
    protected $marks = [];

    /**
     * Constructor, will use memory stream if not
     * provided with a valid stream pointer.
     *
     * @param $stream Resource pointer to stream
     */
    public function __construct($stream = null)
    {
        if ($stream == null) {
            $this->resource = fopen('php://temp', 'wb');
        } else {
            $this->resource = $stream;
        }
    }

    /**
     * Get stream resource.
     *
     * @param  bool     $rewinded
     * @return resource
     */
    public function getResource($rewinded = false)
    {
        if ($rewinded) {
            $this->seek(0);
        }
        return $this->resource;
    }

    /**
     * Get the current stream offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Closes the stream.
     *
     * @return void
     */
    public function close()
    {
        if ($this->resource !== null) {
            @fclose($this->resource);
            $this->resource = null;
        }
    }

    /**
     * Go to offset in stream. Can use string for
     * accessing previously set mark.
     *
     * @param int $offset
     */
    public function seek($offset)
    {
        if (is_string($offset)) {
            fseek($this->resource, $this->marks[$offset]);
        } elseif ($offset < 0) {
            fseek($this->resource, $offset, SEEK_END);
        } else {
            fseek($this->resource, $offset);
        }

        $this->offset = ftell($this->resource);
    }

    /**
     * Skip ahead N bytes.
     *
     * @param $bytes
     */
    public function skip($bytes)
    {
        fseek($this->resource, $bytes, SEEK_CUR);
        $this->offset += $bytes;
    }

    /**
     * Mark current or provided offset as 'name'.
     *
     * @param      $name
     * @param null $offset
     */
    public function setMarker($name, $offset = null)
    {
        $offset = (is_null($offset) ? $this->offset : $offset);
        $this->marks[$name] = $offset;
    }

    /**
     * Get marker offset
     *
     * @param  $name
     * @return bool
     */
    public function getMarker($name)
    {
        return isset($this->marks[$name]) ? $this->marks[$name] : false;
    }

    /**
     * Read a number of bytes and rewind stream to
     * previous position.
     *
     * @param  $sizeInBytes
     * @return string
     */
    public function peek($sizeInBytes)
    {
        $data = fread($this->resource, $sizeInBytes);
        fseek($this->resource, -$sizeInBytes, SEEK_CUR);

        return $data;
    }

    /**
     * Check stream EOF.
     *
     * @return bool
     */
    public function isEnd()
    {
        return $this->offset >= $this->getSize();
    }

    /**
     * Get stream size
     *
     * @return int
     */
    public function getSize()
    {
        $stat = fstat($this->resource);
        return $stat['size'];
    }

    /**
     * Compare next x bytes in stream to provided bytes in array.
     *
     * @param  $sizeInBytes
     * @param  $bytes
     * @return bool
     */
    public function compare($sizeInBytes, $bytes)
    {
        $data = $this->peek($sizeInBytes);

        if (is_array($bytes)) {
            $source = $bytes;
            $bytes = null;

            foreach ($source as $byte) {
                $bytes .= is_int($byte) ? chr($byte) : $byte;
            }
        }

        return ($data === $bytes);
    }

    /**
     * Read a byte from the stream.
     *
     * @return int
     */
    public function readByte()
    {
        $byte = fgetc($this->resource);

        if ($byte !== false) {
            $this->offset++;
        }

        return ord($byte);
    }

    /**
     * Write a byte to the stream.
     *
     * @param  $byte
     * @return int
     */
    public function writeByte($byte)
    {
        if (fwrite($this->resource, chr($byte))) {
            $this->offset++;
        }

        return chr($byte);
    }

    /**
     * Read string from stream.
     *
     * @param  int    $size
     * @return string
     */
    public function read($size = 1)
    {
        $data = fread($this->resource, $size);

        if ($data !== false) {
            $this->offset += $size;
        } else {
            $this->offset = ftell($this->resource);
        }

        return $data;
    }

    /**
     * Write a string to the stream.
     *
     * @param      $data
     * @param null $length
     * @return     $this
     */
    public function write($data, $length = null)
    {
        if (is_null($length)) {
            $length = strlen($data);
        }

        if (fwrite($this->resource, $data, $length)) {
            $this->offset += $length;
        } else {
            $this->offset = ftell($this->resource);
        }

        return $this;
    }

    /**
     * Return unsigned 16-bit int
     *
     * @return int
     */
    public function readUShort()
    {
        $data = unpack('nn', $this->read(2));
        return $data['n'];
    }

    /**
     * Get signed 16-bit int
     *
     * @return int
     */
    public function readShort()
    {
        $data = unpack('nn', $this->read(2));
        if ($data['n'] >= 0x8000) {
            $data['n'] -= 65536;
        }
        return $data['n'];
    }

    /**
     * Get unsigned 32-bit int
     *
     * @return int
     */
    public function readULong()
    {
        $data = unpack('NN', $this->read(4));
        return $data['N'];
    }

    /**
     * Destructor, closes stream automatically.
     */
    public function __destruct()
    {
        $this->close();
    }
}
