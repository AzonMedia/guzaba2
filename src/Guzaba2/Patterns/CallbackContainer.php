<?php
declare(strict_types=1);
/*
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework 2
 * @package        Patterns
 * @subpackage    Overloading
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Patterns;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;

/**
 * A container class for callbacks.
 * When executed will execute all callbacks in the order they were pushed
 */
class CallbackContainer extends Base
{
    /**
     * Use to contain the callables
     *
     * @var callable[]
     */
    protected $callables;

    /**
     *
     * @param array $callables Array of callables
     * @throws RunTimeException
     */
    public function __construct(array $callables = [])
    {
        foreach ($callables as $callable) {
            if (!is_callable($callable)) {
                throw new RunTimeException(sprintf(t::_('The provided argument to %s is not of type callable.'), __CLASS__));
            }
        }
        $this->callables = $callables;
        parent::__construct();
    }

    /**
     * Executes the callables (in the order they were added)
     *
     */
    public function __invoke(): bool
    {
        $args = func_get_args();
        foreach ($this->callables as $callable) {
            if ($callable) { //it may be null if the object got destroyed in the mean time (this is a way to remove a callable from the array)
                call_user_func_array($callable, $args);
            }
        }
        return true;
    }

    /**
     * Returns all callables that are added to the container
     * @return array of callables
     */
    public function get_callables(): array
    {
        return $this->callables;
    }

    /**
     * Adds a new callable.
     * No typehiting due to child (a invokable class is not considered covariant to callable by PHP)
     * @param callable $callable
     * @return callable The callable that is added is also returned
     * @throws RunTimeException
     */
    public function add_callable($callable): callable
    {
        if (!is_callable($callable)) {
            throw new RunTimeException(sprintf(t::_('The provided argument to %s is not of type callable.'), __CLASS__));
        }
        $this->callables[] = $callable;
        return $callable;
    }

    /**
     * Alias of add_callable
     * @param callable $callable
     * @return callable
     * @throws RunTimeException
     */
    public function add(callable $callable)
    {
        return $this->add_callable($callable);
    }
}
