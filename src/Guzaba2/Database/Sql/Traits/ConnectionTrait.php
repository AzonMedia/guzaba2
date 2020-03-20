<?php
declare(strict_types=1);

namespace Guzaba2\Database\Sql\Traits;

trait ConnectionTrait
{
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