<?php

namespace Guzaba2\Swoole;

use Azonmedia\SwooleToPsr\SwooleToPsr;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Request as GuzabaRequest;
use Swoole\Http\Request as SwooleRequest;

/**
 * Class SwooleToGuzaba
 * Because the PSR-7 specification does not provide for withServerParams and the the PsrRequest is already created and provided to the SwooleToPsr::ConvertRequest() method there is no way to set the server params.
 * Due to this this class is created and is a custom extension to the generic SwooleToPsr
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
    public static function convert_request_with_server_params(SwooleRequest $SwooleRequest, GuzabaRequest $PsrRequest): GuzabaRequest
    {
        /** @var GuzabaRequest $PsrRequest */
        $PsrRequest = self::ConvertRequest($SwooleRequest, $PsrRequest);
        $PsrRequest = $PsrRequest->withServerParams($SwooleRequest->server);

        return $PsrRequest;
    }
}
