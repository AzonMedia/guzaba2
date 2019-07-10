<?php
declare(strict_types=1);

namespace Guzaba2\Http\Body;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Psr\Http\Message\StreamInterface;
use Guzaba2\Translator\Translator as t;

//TODO - finish this class - finish Seek/rewing etc
/**
 * Class Str
 * Implemented using a string, not a stream.
 * NOT FINISHED
 * @package Guzaba2\Http\Body
 */
class Str extends Base
    implements StreamInterface
{

    /**
     * @var string
     */
    protected $string = '';

    /**
     * Will be lowered when the processing is over
     * @var bool
     */
    protected $is_writable_flag = TRUE;

    protected $is_readable_flag = TRUE;

    protected $is_seekable_flag = TRUE;

    protected const DEFAULT_DOCTYPE = '<!doctype html>';

    /**
     * Stream constructor.
     * @param string $content If content is provided it will be written to the body
     */
    public function __construct( string $string = '')
    {

        if ($string) {
            $this->write($string);
        }
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     * @throws RunTimeException
     */
    public function __toString() : string
    {
        $ret = '';
        if ($this->isReadable()) {
            $this->rewind();
            $ret = $this->getContents();
        } else {
            throw new RunTimeException(sprintf(t::_('Can not convert this stream to string because it is not readable.')));
        }

        return $ret;
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close() : void
    {
        //does nothing
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach() /* ?resource */
    {


        $this->is_writable_flag = FALSE;
        $this->is_readable_flag = FALSE;
        $this->stream = NULL;

        return NULL;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize() : ?int
    {
        $size = strlen($this->string);
        return $size;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell() : int
    {
        if (($position = ftell($this->stream)) === false) {
            throw new RunTimeException(t::_('Can not retrieve the position of the pointer in the stream.'));
        }
        return $position;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof() : bool
    {
        return feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable() : bool
    {
        return $this->is_seekable_flag;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws RuntimeException on failure.
     *
     */
    public function seek( /* int */ $offset, /* int */ $whence = SEEK_SET) : void
        //public function seek(int $offset, int $whence = SEEK_SET) : void
    {
        if (!$this->isSeekable() || fseek($this->stream, $offset, $whence)) {
            throw new RunTimeException(t::_('Can not seek this stream.'));
        }

    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws RuntimeException on failure.
     */
    public function rewind() : void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException(t::_('Can not rewind this stream.'));
        }
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable() : bool
    {
        return $this->is_writable_flag;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws RuntimeException on failure.
     */
    public function write( /* string */ $string) /* int */
        //public function write(string $string) : int
    {
        //there is no need to use co::fwrite() as it is a memory stream (and also fwrite cant be used with memory stream)
        //if (!$this->isWritable() || ($size = fwrite($this->stream, $string)) === false) {
        if (!$this->isWritable()) { // Swoole\Coroutine::fwrite(): cannot represent a stream of type MEMORY as a select()able descriptor
            throw new RuntimeException('Can not write to this stream.');
        }
        $this->string .= $string;
        $size = strlen($string);
        return $size;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable() : bool
    {
        return $this->is_readable_flag;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws RuntimeException if an error occurs.
     */
    public function read( /* int */ $length) : string
        //public function read(int $length) : string
    {
        if (!$this->isReadable() || ($str = fread($this->stream, $length)) === false) {
            throw new RuntimeException(t::_('Can not read from this stream.'));
        }
        return $str;
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents() : string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException(t::_('Can not get the contents of this stream.'));
        }
        $contents = $this->string;
        return $contents;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata( /* ?string */ $key = NULL) /* mixed */
        //public function getMetadata(?string $key = NULL) /* mixed */
    {
        $meta = stream_get_meta_data($this->stream);
        if (is_null($key) === true) {
            return $meta;
        }
        return isset($meta[$key]) ? $meta[$key] : NULL;
    }
}