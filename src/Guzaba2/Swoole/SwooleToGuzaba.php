<?php

namespace Guzaba2\Swoole;

use Azonmedia\SwooleToPsr\SwooleToPsr;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Request as GuzabaRequest;
use Swoole\Http\Request as SwooleRequest;

/**
 * Class SwooleToGuzaba
 * @package Guzaba2\Swoole
 */
class SwooleToGuzaba extends SwooleToPsr
{
    /**
     * @param SwooleRequest $SwooleRequest
     * @param GuzabaRequest $PsrRequest
     * @return GuzabaRequest
     * @throws RunTimeException
     */
    public static function ConvertRequestWithServerParams(SwooleRequest $SwooleRequest, GuzabaRequest $PsrRequest): GuzabaRequest
    {
        /** @var GuzabaRequest $PsrRequest */
        $PsrRequest = self::ConvertRequest($SwooleRequest, $PsrRequest);
        $PsrRequest->withServerParams($SwooleRequest->server);

        return $PsrRequest;
    }

}