<?php
namespace Swoole\Curl;

class Handler
{
    private $client;
    private $info = [
        'url' => '',
        'content_type' => '',
        'http_code' => 0,
        'header_size' => 0,
        'request_size' => 0,
        'filetime' => -1,
        'ssl_verify_result' => 0,
        'redirect_count' => 0,
        'total_time' => 5.3E-5,
        'namelookup_time' => 0.0,
        'connect_time' => 0.0,
        'pretransfer_time' => 0.0,
        'size_upload' => 0.0,
        'size_download' => 0.0,
        'speed_download' => 0.0,
        'speed_upload' => 0.0,
        'download_content_length' => -1.0,
        'upload_content_length' => -1.0,
        'starttransfer_time' => 0.0,
        'redirect_time' => 0.0,
        'redirect_url' => '',
        'primary_ip' => '',
        'certinfo' => [],
        'primary_port' => 0,
        'local_ip' => '',
        'local_port' => 0,
        'http_version' => 0,
        'protocol' => 0,
        'ssl_verifyresult' => 0,
        'scheme' => '',
    ];
    private $urlInfo;
    private $postData;
    private $outputStream;
    private $proxy;
    private $clientOptions;
    private $followLocation;
    private $maxRedirs;
    private $withHeader;
    private $headerFunction;
    private $readFunction;
    private $writeFunction;
    private $progressFunction;
    public $returnTransfer;
    public $method = 'GET';
    public $headers;
    public $errCode;
    public $errMsg;
    private const ERRORS = [
        3 => 'No URL set!',
    ];

    public function __construct($url = NULL)
    {
        $new_url = $url;
    }

    private function create(string $url) : void
    {
    }

    public function execute()
    {
    }

    public function close() : void
    {
    }

    private function setError($code, $msg = '') : void
    {
    }

    private function getUrl() : string
    {
    }

    public function setOption(int $opt, $value) : bool
    {
    }

    public function reset() : void
    {
    }

    public function getInfo()
    {
    }

    private function unparseUrl(array $parsedUrl) : string
    {
    }

    private function getRedirectUrl(string $location) : array
    {
    }
}
