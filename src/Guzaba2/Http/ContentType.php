<?php
declare(strict_types=1);


namespace Guzaba2\Http;

/**
 * Class ContentType
 * Contains only static methods
 * @package Guzaba2\Http
 */
abstract class ContentType
{
    public const TYPE_TEXT  = 'text';
    public const TYPE_HTML  = 'html';
    public const TYPE_JSON  = 'json';
    public const TYPE_XML   = 'xml';
    public const TYPE_SOAP  = 'soap';
    public const TYPE_YAML   = 'yaml';
    public const TYPE_NATIVE = 'php';

    public const TYPES_MAP = [
        self::TYPE_TEXT     => [
            //'name'  => 'text',
            'mime'  => 'text/plain'
        ],
        self::TYPE_HTML     => [
            //'name'  => 'html',
            'mime'  => 'text/html'
        ],
        self::TYPE_JSON     => [
            //'name'  => 'json',
            'mime'  => 'application/json'
        ],
        self::TYPE_XML      => [
            //'name'  => 'xml',
            'mime'  => 'text/xml'
        ],
        self::TYPE_SOAP     => [
            //'name'  => 'soap',
            'mime'  => 'application/soap+xml'
        ],
        self::TYPE_YAML      => [
            //'name'  => 'yaml',
            'mime'  => [
                'text/yaml',
                'application/x-yaml'
            ]
        ],
        self::TYPE_NATIVE   => [
            //'mime'  => 'text/x-php',
            'mime'  => 'application/php',
        ]

    ];

    /**
     * Returns a string representing a constant (@param string $header_content
     * @return string|null
     * @see self::TYPES_MAP) or null if no match is found
     */
    public static function get_content_type(string $header_content) : ?string
    {
        $ret = NULL;
        foreach (ContentType::TYPES_MAP as $content_constant => $content_data) {
            $mime_types = (array) $content_data['mime'];
            foreach ($mime_types as $mime_type) {
                if (stripos($header_content, $mime_type) !== FALSE) {
                    $ret = $content_constant;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * @param string $content_type
     * @return bool
     */
    public static function is_valid_content_type(string $content_type) : bool
    {
        $ret = FALSE;
        foreach (self::TYPES_MAP as $type_const=>$type_data) {
            if (strtolower($content_type) === strtolower($type_const)) {
                $ret = TRUE;
                break;
            }
        }
        return $ret;
    }
}
