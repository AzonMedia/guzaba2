<?php
declare(strict_types=1);

/**
 * Guzaba Framework
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * @category    Guzaba2 Framework
 * @package        Base
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Veselin Kenashkov <kenashkov@azonmedia.com>
 */

namespace Guzaba2\Base\Exceptions;

use \Guzaba2\Base\Traits\SupportsObjectInternalId;
use Throwable;

/**
 * Class BaseException
 * All exceptions inherit this one
 */
abstract class BaseException extends \Exception
{


    use SupportsObjectInternalId;

    /**
     * BaseException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->set_object_internal_id();
        parent::__construct($message, $code, $previous);
    }


}