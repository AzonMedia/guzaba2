<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Interfaces;

interface ConnectionInterface
{

    /**
     * Returns the database name for the connection.
     * @return string
     */
    public static function get_database(): string;

    /**
     * Replaces the named parameters like :some_val with ? as required by some drivers (not all drivers require this).
     * @param string $named_params_query
     * @param array $expected_parameters
     * @return string
     */
    public static function convert_query_for_binding(string $named_params_query, array &$expected_parameters = []): string;

    /**
     * Returns a string with a placeholder for multiple values.
     * To be used for example in IN clauses.
     * @example
     * some_col IN (:val0, :val1, :val2)
     * @param array $array
     * @param string $placeholder_name
     * @return string
     */
    public static function array_placeholder(array $array, string $placeholder_name): string;
}
