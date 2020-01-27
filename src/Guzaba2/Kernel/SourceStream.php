<?php
declare(strict_types=1);


namespace Guzaba2\Kernel;

use Azonmedia\Utilities\DebugUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Exceptions\NotImplementedException;
use Guzaba2\Kernel\Exceptions\AutoloadException;
use Guzaba2\Translator\Translator as t;

/**
 * Class SourceStream
 * @see http://php.net/manual/en/class.streamwrapper.php
 * Registers guzaba.source stream which is used to load the classes.
 * It rewrites the CONFIG_RUNTIME constant
 * @package Guzaba2\Kernel
 */
class SourceStream extends Base
{
    public $context;

    protected $position = 0;


    protected $data = '';
    protected $path;
    protected $mode;
    protected $options;

    protected $read_enabled = false;
    protected $write_enabled = false;

    public const PROTOCOL = 'guzaba.source';

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->mode = $mode;
        $this->options = $options;
        $opened_path =& $this->path;
        $this->path = substr(strstr($path, '://'), 3);

        switch ($this->mode) {
            case 'rb':
            case 'r':
                //Open for reading only; place the file pointer at the beginning of the file.
                $this->data = self::load_data($this->path);
                $this->position = 0;
                $this->read_enabled = true;
                break;
            case 'rb+':
            case 'r+':
                //Open for reading and writing; place the file pointer at the beginning of the file.
            $this->data = self::load_data($this->path);
                $this->position = 0;
                $this->read_enabled = true;
                $this->write_enabled = true;
                break;
            case 'wb':
            case 'w':
                //Open for writing only; place the file pointer at the beginning of the file and truncate the file to zero length. If the file does not exist, attempt to create it.
                //is not loading any data as in this must it must truncate the source
                $this->data = '';
                $this->position = 0;
                $this->write_enabled = true;
                break;
            case 'wb+':
            case 'w+':
                //Open for reading and writing; place the file pointer at the beginning of the file and truncate the file to zero length. If the file does not exist, attempt to create it.
                $this->data = '';
                $this->position = 0;
                $this->read_enabled = true;
                $this->write_enabled = true;
                break;
            case 'ab':
            case 'a':
                //Open for writing only; place the file pointer at the end of the file. If the file does not exist, attempt to create it.
                $this->data = self::load_data($this->path);
                $this->position = strlen($this->data);
                $this->write_enabled = true;
                break;
            case 'ab+':
            case 'a+':
                //Open for reading and writing; place the file pointer at the end of the file. If the file does not exist, attempt to create it.
                $this->data = self::load_data($this->path);
                $this->position = strlen($this->data);
                $this->read_enabled = true;
                $this->write_enabled = true;
                break;
            case 'xb':
            case 'x':
                //Create and open for writing only; place the file pointer at the beginning of the file. If the file already exists, the fopen() call will fail by returning FALSE and generating an error of level E_WARNING. If the file does not exist, attempt to create it. This is equivalent to specifying O_EXCL|O_CREAT flags for the underlying open(2) system call.
                $this->data = self::load_data($this->path);
                $this->position = strlen($this->data);
                $this->write_enabled = true;
                break;
            case 'xb+':
            case 'x+':
                //Create and open for reading and writing; place the file pointer at the beginning of the file. If the file already exists, the fopen() call will fail by returning FALSE and generating an error of level E_WARNING. If the file does not exist, attempt to create it. This is equivalent to specifying O_EXCL|O_CREAT flags for the underlying open(2) system call.
                $this->data = self::load_data($this->path);
                $this->position = strlen($this->data);
                $this->read_enabled = true;
                $this->write_enabled = true;
                break;
            default:
                throw new RunTimeException(sprintf(t::_('An unsupported mode "%s" is provided.'), $this->mode));
        }

        return true;
    }

    /**
     * Returns the rewritten class source.
     * The rewriting is done by first loading the class (by using eval()) with a different name so that the runtime configuration can be obtained (@see Kernel::get_runtime_configuration()).
     * Then on the second pass the actual class source is rewritten with the new runtime config and the source is returned.
     * @uses Kernel::get_runtime_configuration()
     * @param string $path Class path that is to be loaded
     * @return string
     */
    private static function load_data(string $path) : string
    {

        //print 'load_data: '.$path.PHP_EOL;

        if (Kernel::check_syntax($path, $error)) {
            $message = sprintf(t::_('The file %s contains errors. %s'), $path, $error);
            //throw new AutoloadException($error_str);
            //looks much better if it just stops
            Kernel::stop($message);
        }

        //$class_source = file_get_contents($path);
        if (\Swoole\Coroutine::getCid() > 0) {
            $class_source = \Swoole\Coroutine::readFile($path);
        } else {
            $class_source = file_get_contents($path);
        }


        foreach (Kernel::get_registered_autoloader_paths() as $ns_base => $autoload_path) {
            if (strpos($path, $autoload_path) !== FALSE) {
                $class_ns_base = $ns_base;
                $class_autoload_path = $autoload_path;
                break;
            }
        }
        if (empty($class_ns_base)) {
            throw new \RuntimeException(sprintf('The file %s can not be loaded as it is not from whithin a registered autoload path.', $path));
        }

        $ns_pos = strpos( $path, str_replace('\\', '/', $class_ns_base) );
        if ($ns_pos) {
            $class_name = substr($path, $ns_pos );
            $class_name = str_replace('/', '\\', $class_name);
            $class_name = str_replace('.php', '', $class_name);
        } else {

            $class_name = str_replace($class_autoload_path,'',$path);
            $class_name = str_replace('.php', '', $class_name);
            $class_name = str_replace('/', '\\', $class_name);
            $class_name = $class_ns_base.'\\'.$class_name;
        }




        //print $class_name.PHP_EOL.PHP_EOL;
        //print $path.' '.$class_ns.PHP_EOL;
        //print $class_name.PHP_EOL.PHP_EOL;

        //$class_name = $path;
//        $registered_autoload_paths = Kernel::get_registered_autoloader_paths();
//        foreach ($registered_autoload_paths as $autoload_path) {
//            $class_name = str_replace($autoload_path, '', $class_name);
//        }

//        $class_name = str_replace('/', '\\', $class_name);
//        $class_name = str_replace('.php', '', $class_name);
//        // Strip leading slash
//        $class_name = substr($class_name, 1);


        $ns_arr = explode('\\', $class_name);
        $class_name_without_ns = array_pop($ns_arr);
        //TODO - replace the below with tokenizer
        if (strpos($class_source, 'abstract class') !== FALSE) {
            $class_without_config_source = str_replace('class '.$class_name_without_ns, 'class '.$class_name_without_ns.'_without_config', $class_source);
        } else {
            $class_without_config_source = str_replace('class '.$class_name_without_ns, 'abstract class '.$class_name_without_ns.'_without_config', $class_source);
        }
        //TODO - improve this - replace with tokenized
        //handle self::class
        $class_without_config_source = str_replace('self::class', '\'\\'.$class_name.'\'', $class_without_config_source);

        if (strpos($class_without_config_source, '<?php')===0) {

            $class_without_config_source = substr($class_without_config_source, 5);
        }
        //before evluating check for parse errors
        //this will not be executing anything so runtime errors are not expected
        try {
            eval($class_without_config_source);
        } catch (\Throwable $Exception) {
//            $message = '';
//            $PreviosException = $Exception->getPrevious();
//
//            $message .= get_class($Exception).' '.$Exception->getMessage().' in '.$Exception->getFile().':'.$Exception->getLine().PHP_EOL.'Eval code:'.PHP_EOL.DebugUtil::dump_code_with_lines($class_without_config_source).PHP_EOL.PHP_EOL;
//            if ($PreviosException) {
//                $message .= get_class($Exception).' '.$Exception->getMessage().' in '.$Exception->getFile().':'.$Exception->getLine().PHP_EOL.'Eval code:'.PHP_EOL.DebugUtil::dump_code_with_lines($class_without_config_source).PHP_EOL.PHP_EOL;
//            }
//            //Kernel::log($message);
            Kernel::exception_handler($Exception);
        }


        $runtime_config = Kernel::get_runtime_configuration($class_name.'_without_config');

        $to_be_replaced_str = 'protected const CONFIG_RUNTIME = [];';
        $replacement_str = 'protected const CONFIG_RUNTIME = '.str_replace(PHP_EOL, ' ', var_export($runtime_config, TRUE)).';';//remove the new lines as this will change the line of the errors/exceptions

        $class_source = str_replace($to_be_replaced_str, $replacement_str, $class_source);

//        if ($class_name === \GuzabaPlatform\Platform\Application\MysqlConnection::class) {
//            print $class_source;
//        }
        return $class_source;
    }

    public function stream_read($count)
    {
        if (!$this->read_enabled) {
            throw new RunTimeException(sprintf(t::_('The stream "%s" was opned in "%s" mode in which reading is not allowed.'), $this->path, $this->mode));
        }
        $ret = substr($this->data, $this->position, $count);
        $this->position += $count;
        return $ret;
    }

    public function stream_write($data)
    {
        if (!$this->write_enabled) {
            throw new RunTimeException(sprintf(t::_('The stream "%s" was opned in "%s" mode in which writing is not allowed.'), $this->path, $this->mode));
        }
        $this->data = substr($this->data, 0, $this->position).$data;
        $this->position += strlen($data);

        return strlen($data);
    }

    public function stream_flush()
    {
        return true;
    }

    public function stream_close()
    {
        return true;
    }

    public function stream_lock()
    {
        throw new NotImplementedException(sprintf(t::_('Locking of a %s stream is not implemented yet.'), self::PROTOCOL));
    }

    public function stream_set_option(int $option, int $arg1, int $arg2)
    {
        //throw new NotImplementedException(sprintf(t::_('Setting options of a %s stream is not implemented yet.'), self::PROTOCOL));
        //just ignore this for now
    }

    public function stream_seek($offset, $whence  = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = strlen($this->data) + $offset;
                break;
        }
        return true;
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_eof()
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat()
    {
        $ret = [
            0                => 0,
            'dev'            => 0,
            1                => 0,
            'ino'            => 0,
            2                => 0,
            'mode'        => 0,
            3                => 0,
            'nlink'        => 0,
            4                => getmyuid(),
            'uid'            => getmyuid(),
            5                => getmygid(),
            'gid'            => getmygid(),
            6                => 0,
            'rdev'        => 0,
            7                => strlen($this->data),
            'size'        => strlen($this->data),
            8                => time(),
            'atime'        => time(),
            9                => time(),
            'mtime'        => time(),
            10                => time(),
            'ctime'        => time(),
            11                => 512,
            'blksize'    => 512,
            12                => ceil(strlen($this->data)/512),
            'blocks'        => ceil(strlen($this->data)/512),
        ];
        return $ret;
    }

    public function url_stat($path, $flags)
    {
        return $this->stream_stat();
    }
}
