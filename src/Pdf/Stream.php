<?php
namespace PdfBuilder\Pdf;

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
     * Constructor
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
     * @return resource
     */
    public function getStream()
    {
        return $this->resource;
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
     * Go to offset in stream.
     *
     * @param int $offset
     */
    public function seek($offset)
    {
        if ($offset < 0) {
            fseek($this->resource, $offset, SEEK_END);
        } else {
            fseek($this->resource, $offset);
        }

        $this->offset = ftell($this->resource);
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
     * Zero fill bitwise right shift
     *
     * @param  $value
     * @param  $shiftBy
     * @return int
     */
    protected function zerofillRightShift($value, $shiftBy)
    {
        if ($shiftBy >= 32 || $shiftBy < -32) {
            $m = (int)($shiftBy / 32);
            $shiftBy = $shiftBy - ($m * 32);
        }
        if ($shiftBy < 0) {
            $shiftBy = 32 + $shiftBy;
        }
        if ($shiftBy == 0) {
            return (($value >> 1) & 0x7FFFFFFF) * 2 + (($value >> $shiftBy) & 1);
        }

        if ($value < 0) {
            $value = ($value >> 1);
            $value &= 0x7FFFFFFF;
            $value |= 0x40000000;
            $value = ($value >> ($shiftBy - 1));
        } else {
            $value = ($value >> $shiftBy);
        }

        return $value;
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
     * Get boolean from stream.
     *
     * @return bool
     */
    public function readBool()
    {
        return !!$this->readByte();
    }

    /**
     * Write boolean to stream.
     *
     * @param $val
     */
    public function writeBool($val)
    {
        $this->writeByte(($val) ? 1 : 0);
    }

    /**
     * Read unsigned int32 from stream.
     *
     * @return int
     */
    public function readUInt32()
    {
        $b1 = $this->readByte() * 0x1000000;
        $b2 = $this->readByte() << 16;
        $b3 = $this->readByte() << 8;
        $b4 = $this->readByte();

        return $b1 + $b2 + $b3 + $b4;
    }

    /**
     * Write unsigned int32 to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeUInt32($val)
    {
        $this->writeByte($this->zerofillRightShift($val, 24) & 0xff);
        $this->writeByte($val >> 16 & 0xff);
        $this->writeByte($val >> 8 & 0xff);

        return $this->writeByte($val & 0xff);
    }

    /**
     * Read int32 from stream.
     *
     * @return int
     */
    public function readInt32()
    {
        $int = $this->readUInt32();

        if ($int >= 0x80000000) {
            return $int - 0x100000000;
        } else {
            return $int;
        }
    }

    /**
     * Write int32 to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeInt32($val)
    {
        if ($val < 0) {
            $val += 0x100000000;
        }
        return $this->writeUInt32($val);
    }

    /**
     * Read integer from stream.
     *
     * @return int
     */
    public function readInt()
    {
        return $this->readInt32();
    }

    /**
     * Write integer to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeInt($val)
    {
        return $this->writeInt32($val);
    }

    /**
     * Read unsigned int16 from stream.
     *
     * @return int
     */
    public function readUInt16()
    {
        $b1 = $this->readByte() << 8;
        $b2 = $this->readByte();

        return $b1 | $b2;
    }

    /**
     * Write unsigned int16 to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeUInt16($val)
    {
        $this->writeByte($val >> 8 & 0xff);
        return $this->writeByte($val & 0xff);
    }

    /**
     * Read int16 from stream.
     *
     * @return int
     */
    public function readInt16()
    {
        $int = $this->readUInt16();
        if ($int >= 0x8000) {
            return $int - 0x10000;
        } else {
            return $int;
        }
    }

    /**
     * Write int16 to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeInt16($val)
    {
        if ($val < 0) {
            $val += 0x10000;
        }
        return $this->writeUInt16($val);
    }

    /**
     * Read string from stream.
     *
     * @param  int    $size
     * @return string
     */
    public function readString($size = 1)
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
     * @param  $string
     */
    public function writeString($string)
    {
        if (fwrite($this->resource, $string)) {
            $this->offset += strlen($string);
        } else {
            $this->offset = ftell($this->resource);
        }
    }

    /**
     * Read short from stream.
     *
     * @return int
     */
    public function readShort()
    {
        return $this->readInt16();
    }

    /**
     * Write short to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeShort($val)
    {
        return $this->writeInt16($val);
    }

    /**
     * Read long long from stream.
     *
     * @return int
     */
    public function readLongLong()
    {
        $b1 = $this->readByte();
        $b2 = $this->readByte();
        $b3 = $this->readByte();
        $b4 = $this->readByte();
        $b5 = $this->readByte();
        $b6 = $this->readByte();
        $b7 = $this->readByte();
        $b8 = $this->readByte();

        if ($b1 & 0x80) {
            return
                $b1 ^ 0xff * 0x100000000000000 +
                $b2 ^ 0xff * 0x1000000000000 +
                $b3 ^ 0xff * 0x10000000000 +
                $b4 ^ 0xff * 0x100000000 +
                $b5 ^ 0xff * 0x1000000 +
                $b6 ^ 0xff * 0x10000 +
                $b7 ^ 0xff * 0x100 +
                $b8 ^ 0xff + 1 * -1;
        }
        return
            $b1 * 0x100000000000000 +
            $b2 * 0x1000000000000 +
            $b3 * 0x10000000000 +
            $b4 * 0x100000000 +
            $b5 * 0x1000000 +
            $b6 * 0x10000 +
            $b7 * 0x100 +
            $b8;
    }

    /**
     * Write long long to stream.
     *
     * @param  $val
     * @return int
     */
    public function writeLongLong($val)
    {
        $high = floor($val / 0x100000000);
        $low = $val & 0xffffffff;

        $this->writeByte($high >> 24 & 0xff);
        $this->writeByte($high >> 16 & 0xff);
        $this->writeByte($high >> 8 & 0xff);
        $this->writeByte($high & 0xff);
        $this->writeByte($low >> 24 & 0xff);
        $this->writeByte($low >> 16 & 0xff);
        $this->writeByte($low >> 8 & 0xff);

        return $this->writeByte($low & 0xff);
    }

    /**
     * Read from the stream.
     *
     * @param  $bytes
     * @return string
     */
    public function read($bytes)
    {
        $data = fread($this->resource, $bytes);
        $this->offset = (($data !== false) ? $this->offset + $bytes : ftell($this->resource));
        return $data;
    }

    /**
     * Write bytes to stream.
     *
     * @param $bytes
     */
    public function write($bytes)
    {
        if (fwrite($this->resource, $bytes)) {
            $this->offset += strlen($bytes);
        } else {
            $this->offset = ftell($this->resource);
        }
    }

    /**
     * Destructor, closes stream automatically.
     */
    public function __destruct()
    {
        $this->close();
    }
}
