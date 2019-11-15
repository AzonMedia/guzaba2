<?php

namespace Guzaba2\Kernel\Interfaces;

interface ClassDeclarationValidationInterface
{

    /**
     * Must return an array of the validation methods (method names or description) that were run.
     * @return array
     */
    public static function run_all_validations() : array;
}