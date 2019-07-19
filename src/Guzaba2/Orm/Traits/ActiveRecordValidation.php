<?php


namespace Guzaba2\Orm\Traits;


trait ActiveRecordValidation
{
    /**
     * Disables the validation (@see activerecordValidation::validate()) that is invoked on save().
     * This will also bypass the validation hooks like _before_validate.
     * By defaults this is enabled.
     */
    public function disable_validation() : void
    {
        $this->validation_is_disabled_flag = true;
    }

    /**
     * Enables the validation (activerecordValidation::validate()) that is invoked on save()
     * By defaults this is enabled.
     * @return void
     */
    public function enable_validation() : void
    {
        $this->validation_is_disabled_flag = false;
    }

    /**
     * Returns is the validation enabled for this instance.
     * @return bool
     */
    public function validation_is_disabled() : bool
    {
        return $this->validation_is_disabled_flag;
    }
}