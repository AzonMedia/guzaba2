<?php


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

    public function execute(array $parameters = []) : self
    {

        if ($parameters && $this->params) {
            //throw new ParameterException('*', sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__), $query, $parameters );
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__));
        }

        if ($parameters) {
            $this->params = $parameters;
        }

        $position_parameters = $this->convert_to_position_parameters($this->params);

        //mysqli does not support arguments provided to execute()
        //the argumetns must be bound individually
        //$ret = $this->NativeStatement->execute($position_parameters);
        if (count($position_parameters)) {
            $this->NativeStatement->bind_param(self::get_types_for_binding($position_parameters), ...$position_parameters);
        }

        $ret = $this->NativeStatement->execute();
        if ($ret === FALSE) {
            $this->handle_error();//will throw exception
        }

        return $this;
    }

    public function fetch_all(): array
    {
        return $this->fetchAll();
    }

    public function fetchAll() : array
    {
        $result = $this->NativeStatement->get_result();
        $data = [];
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    public function fetch_row(string $column_name = '')
    {
        return $this->fetchRow($column_name);
    }

    public function fetchRow(string $column_name = '') /*mixed*/
    {
        //todo
    }

    /**
     * @param array $position_parameters
     * @return array
     */
    public static function get_types_for_binding(array $position_parameters) : string
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
                throw new InvalidArgumentException(sprintf(t::_('An unsupported parameter type of %s is provided for binding.'), gettype($position_parameter) ));
            }
        }
        return $types;
    }
}
