<?php
declare(strict_types=1);

namespace Guzaba2\Database\Sql\Traits;

trait ConnectionTrait
{
    public static function get_database() : string
    {
        return static::CONFIG_RUNTIME['database'];
    }

    public static function convert_query_for_binding(string $named_params_query, array &$expected_parameters = []): string
    {
        preg_match_all('/:([a-zA-Z0-9_]*)/', $named_params_query, $matches);
        if (isset($matches[1]) && count($matches[1])) {
            $expected_parameters = $matches[1];
        }
        $query = preg_replace('/:([a-zA-Z0-9_]*)/', '?', $named_params_query);
        return $query;
    }

    public static function array_placeholder(array $array, string $placeholder_name): string
    {
        $placeholders = str_repeat(':'.$placeholder_name, count($array));
        for ($aa=0; $aa < count($placeholders) ; $aa++) {
            $placeholders[$aa] = $placeholders[$aa].$aa;
        }
        return implode(','.$placeholders);
    }
}