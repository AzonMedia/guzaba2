<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Translator\Translator as t;

class StatementMysqli extends Statement implements StatementInterface
{

    /**
     * If a string is over this limit it will be bound as blob
     */
    public const STRING_AS_BLOB_LIMIT = 2000;

    public function execute(array $parameters = []): self
    {

        if ($parameters && $this->params) {
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__));
        }

        if ($parameters) {
            $this->params = $parameters;
        }

        $this->params = $this->get_connection()::prepare_params($this->params);

        $sql = $this->get_query();

        $statement_group_str = $this->get_statement_group_as_string();
        if ($statement_group_str === null) {
            throw new RunTimeException(sprintf(t::_('The statement for query %s can not be determined of which type is (DQL, DML etc...).'), $sql));
        }

        $position_parameters = $this->convert_to_position_parameters($this->params);

        //mysqli does not support arguments provided to execute()
        //the argumetns must be bound individually
        //$ret = $this->NativeStatement->execute($position_parameters);
        if (count($position_parameters)) {
            $this->NativeStatement->bind_param(self::get_types_for_binding($position_parameters), ...$position_parameters);
        }

        $exec_start_time = microtime(true);

        $ret = $this->NativeStatement->execute();
        if ($ret === false) {
            $this->handle_error();//will throw exception
        }

        $exec_end_time = microtime(true);
        $Apm = self::get_service('Apm');
        $Apm->increment_value('cnt_' . strtolower($statement_group_str) . '_statements', 1);
        $Apm->increment_value('time_' . strtolower($statement_group_str) . '_statements', $exec_end_time - $exec_start_time);

        return $this;
    }

    public function fetch_all(): array
    {
        return $this->fetchAll();
    }

    public function fetchAll(): array
    {
        $result = $this->NativeStatement->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * @param array $position_parameters
     * @return array
     */
    public static function get_types_for_binding(array $position_parameters): string
    {
        $types = '';
        foreach ($position_parameters as $position_parameter) {
            if (is_int($position_parameter)) {
                $types .= 'i';
            } elseif (is_float($position_parameter)) {
                $types .= 'd';
            } elseif (is_bool($position_parameter)) {
                $types .= 'i';//treat bool as int
            } elseif (is_null($position_parameter)) {
                $types .= 's';//treat NULL as string
            } elseif (is_string($position_parameter)) {
                if (strlen($position_parameter) <= self::STRING_AS_BLOB_LIMIT) {
                    $types .= 's';
                } else {
                    $types .= 'b';//binary
                }
            } else {
                throw new InvalidArgumentException(sprintf(t::_('An unsupported parameter type of %s is provided for binding.'), gettype($position_parameter)));
            }
        }
        return $types;
    }
}
