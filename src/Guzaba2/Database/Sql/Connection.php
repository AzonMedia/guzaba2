<?php


namespace Guzaba2\Database\Sql;


abstract class Connection extends \Guzaba2\Database\Connection
{
    // public static function get_tprefix() : string
    // {
    //     return static::CONFIG_RUNTIME['tprefix'] ?? '';
    // }

    public static function get_database() : string
    {
        return static::CONFIG_RUNTIME['database'];
    }

    protected static function convert_query_for_binding(string $named_params_query, array &$expected_parameters = []) : string
    {
        preg_match_all('/:([a-zA-Z0-9_]*)/', $named_params_query, $matches);
        if (isset($matches[1]) && count($matches[1])) {
            $expected_parameters = $matches[1];
        }
        $query = preg_replace('/:([a-zA-Z0-9_]*)/', '?', $named_params_query);
        return $query;
    }
}