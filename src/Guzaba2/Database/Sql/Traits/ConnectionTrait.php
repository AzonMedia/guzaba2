<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Traits;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Translator\Translator as t;

trait ConnectionTrait
{
    public static function get_database(): string
    {
        return static::CONFIG_RUNTIME['database'];
    }

    /**
     * Format to human readable form an SQL query.
     * Uses \PhpMyAdmin\SqlParser
     * @param string $sql
     * @param array $options
     * @return string
     */
    public static function format_sql(string $sql, array $options = []): string
    {
        return \PhpMyAdmin\SqlParser\Utils\Formatter::format($sql, $options);
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
        $placeholders = [];
        for ($aa = 0; $aa < count($array); $aa++) {
            $placeholders[] = ':' . $placeholder_name . $aa;
        }
        return implode(',', $placeholders);
    }

    /**
     * Prepares params values for binding.
     * If a value is an array it gets converted to key0, key1, key2... etc to match the @see self::array_placeholder().
     * @param array $params
     * @return array
     * @throws RuntimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function prepare_params(array $params): array
    {
        foreach ($params as $param_name => $param_value) {
            if (is_array($param_value)) {
                if (array_keys($param_value) !== range(0, count($param_value) - 1)) {
                    $message = sprintf(
                        t::_('The array for parameters %1$s is not an indexed array. All params: %2$s'),
                        $param_name,
                        print_r($params, true)
                    );
                    throw new \RuntimeException($message);
                }
                for ($aa = 0; $aa < count($param_value); $aa++) {
                    $params[$param_name . $aa] = $param_value[$aa];
                }
                unset($params[$param_name]);
            }
        }
        return $params;
    }
}
