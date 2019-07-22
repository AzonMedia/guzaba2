<?php
declare(strict_types=1);
/*
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Description of parameterException
 * @category    Guzaba Framework 2
 * @package        Database
 * @subpackage    Exceptions
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Database\Exceptions;

use Guzaba2\Transaction\Transaction;

class TransactionException extends DatabaseException
{
    protected $transaction;

    /**
     * TransactionException constructor.
     * @param Transaction|null $transaction
     * @param $errormsg
     * @param \Exception|null $previous_exception
     */
    public function __construct(Transaction $transaction, $errormsg, \Exception $previous_exception = null)
    {
        parent::__construct($errormsg, 0, $previous_exception);
    }

    public function getTransaction()
    {
        return $this->transaction;
    }
}

