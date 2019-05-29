<?php


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
    public const TYPE_YML   = 'yaml';

    public const TYPES_MAP = [
        self::TYPE_TEXT     => ['mime' => 'text/plain'],
        self::TYPE_HTML     => ['mime' => 'text/html'],
        self::TYPE_JSON     => ['mime' => 'application/json'],
        self::TYPE_XML      => ['mime' => 'text/xml'],
        self::TYPE_SOAP     => ['mime' => 'application/soap+xml'],
        self::TYPE_YML      => ['mime' => ['text/yaml', 'application/x-yaml'] ],

    ];

    /**
     * Returns a string representing a constant (@see self::TYPES_MAP) or null if no match is found
     * @return string|null
     */
    public static function get_content_type(string $header_content) : ?string
    {
        $ret = NULL;
        foreach (ContentType::TYPES_MAP as $content_constant => $content_data) {
            $mime_types = (array) $content_data['mime'];
            foreach ($mime_types as $mime_type) {
                if (stripos($header_content, $mime_type) !== FALSE) {
                    $ret = constant(self::class.'::'.$content_constant);
                    break;
                }
            }
        }
        return $ret;
    }
}