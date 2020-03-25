<?php
declare(strict_types=1);


namespace Guzaba2\Coroutine;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;

/**
 * Class Resources
 * A class representing the resources allocated to a coroutine.
 * To be set as a property to the coroutine context.
 * Do not modify to assign the $Context as a property to this class as this will create a circular reference and delay the object destruction (delaying the release of the resources).
 * @package Guzaba2\Coroutine
 */
class Resources extends Base
{
    /**
     * @var int
     */
    protected int $cid;

    /**
     * Where this coroutine was created.
     * @var array
     */
    protected array $created_backtrace = [];

    /**
     * A list with the currently assigned resources to this coroutine.
     * @var array
     */
    protected array $resources = [];

    /**
     * Resources constructor.
     */
    public function __construct()
    {
        $this->cid = Coroutine::getCid();

        if (Coroutine::completeBacktraceEnabled()) {
            $this->created_backtrace = [];
            $pcid = Coroutine::getPcid();
            if ($pcid > 0) {
                //there is parent coroutine - lets save the backtrace where this coroutine was created
                //by getting the backtrace of the parent coroutine
                $this->created_backtrace = \Swoole\Coroutine::getBackTrace($pcid, \DEBUG_BACKTRACE_IGNORE_ARGS);
            }
        }
    }


    public function __destruct()
    {
        $this->free_all_resources();
    }

    public function get_backtrace() : array
    {
        return $this->created_backtrace;
    }

    /**
     * Returns the coroutine ID to which this context is attached.
     * @return int
     */
    public function get_cid() : int
    {
        return $this->cid;
    }

    /**
     * Assign a resource to the coroutine context.
     * @param ResourceInterface $Resource
     */
    public function assign_resource(ResourceInterface $Resource) : void
    {
        if (!in_array($Resource, $this->resources)) {
            $this->resources[] = $Resource;
        }
    }

    /**
     * Unassign a resource from the coroutine context. This is to be called when the coroutine no longer uses this resource.
     * @param ResourceInterface $Resource
     */
    public function unassign_resource(ResourceInterface $Resource) : void
    {
        foreach ($this->resources as $key=>$AssignedResource) {
            if ($Resource === $AssignedResource) {
                unset($this->resources[$key]);
            }
        }
        $this->resources = array_values($this->resources);
    }

    /**
     * @param string|null $resource_class
     * @return array Array of ResourceInterface
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_resources(?string $resource_class = NULL) : array
    {
        $ret = [];
        $all_resources = $this->resources;
        $ret = $all_resources;

        if ($resource_class && !empty($all_resources)) {
            if (!class_exists($resource_class)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided resource_class %s to %s() does not exist.'), $resource_class, __METHOD__));
            }
            $ret = [];
            foreach ($all_resources as $Resource) {
                if (get_class($Resource) === $resource_class) {
                    $ret[] = $Resource;
                }
            }
        }
        return $ret;
    }

    /**
     * Usually acoroutine has only one resource (connection) of certain class.
     * This class returns this resource if the coroutine has assigned one.
     * @param string $resource_class
     * @return ResourceInterface|NULL
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_resource(string $resource_class) : ?ResourceInterface
    {
        $resources = $this->get_resources($resource_class);
        $ret = $resources[0] ?? NULL;
        return $ret;
    }

    /**
     * Frees all resources used by the coroutine if they werent freed manualyl before that.
     * To be called at coroutine end as a safety measure.
     */
    public function free_all_resources() : void
    {
        while (count($this->resources)) {
            $Resource = array_pop($this->resources);
            $Resource->force_release();
        }
    }
}
