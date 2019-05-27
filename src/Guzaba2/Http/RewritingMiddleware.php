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
class RewritingMiddleware extends Base
implements MiddlewareInterface
{

    /**
     * @var Server
     */
    protected $HttpServer;

    /**
     * @var Rewriter
     */
    protected $Rewriter;

    /**
     * RewritingMiddleware constructor.
     * @param Server $Server
     * @param Rewriter $Rewriter
     */
    public function __construct(Server $Server, Rewriter $Rewriter)
    {

        $this->HttpServer = $Server;

        $this->Rewriter = $Rewriter;
    }

    /**
     * {@inheritDoc
     * Will replace the Request provided to the next middleware with a one with a different Uri
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Randler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler): ResponseInterface
    {
        $Request = $this->Rewriter->rewrite_request($Request);
        $Response = $Handler->handle($Request);

        return $Response;
    }
}