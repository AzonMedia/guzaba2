<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Azonmedia\UrlRewriting\Rewriter;
use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RewritingMiddleware
 * @package Guzaba2\Http
 */
class CorsMiddleware extends Base implements MiddlewareInterface
{
    private $headers;

    /**
     * RewritingMiddleware constructor.
     * @param Server $Server
     * @param Rewriter $Rewriter
     * @param $headers
     */
    public function __construct(array $headers = [])
    {
        parent::__construct();

        $this->headers = $headers;
    }

    /**
     * {@inheritDoc}
     * Will replace the Request provided to the next middleware with a one with a different Uri
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler): ResponseInterface
    {
        $Response = $Handler->handle($Request);

        //TODO - improve
        if (empty($this->headers)) {
            $Response = $Response->withAddedHeader('Access-Control-Allow-Origin', '*');
        } else {
            foreach ($this->headers as $key => $value) {
                $Response = $Response->withAddedHeader($key, $value);
            }
        }

        return $Response;
    }
}
