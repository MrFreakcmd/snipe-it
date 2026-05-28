<?php

namespace App\Models\Traits;

use App\Models\CustomFieldset;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

trait HasCustomFields
{
    /**
     * Return the CustomFieldset for this model instance.
     * Override in each model to supply the correct fieldset.
     */
    protected function getCustomFieldset(): ?CustomFieldset
    {
        return null;
    }

    public function customFieldValidationRules(): array
    {
        $fieldset = $this->getCustomFieldset();

        if (! $fieldset) {
            return [];
        }

        foreach ($fieldset->fields as $field) {
            if ($field->format === 'BOOLEAN' && ! $field->field_encrypted) {
                $this->{$field->db_column} = filter_var($this->{$field->db_column}, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $fieldset->validation_rules();
    }

    public function save(array $params = [])
    {
        $this->rules += $this->customFieldValidationRules();

        return parent::save($params);
    }

    public function customFieldsForCheckinCheckout(string $checkin_checkout): void
    {
        $fieldset = $this->getCustomFieldset();

        if (! $fieldset?->fields) {
            return;
        }

        foreach ($fieldset->fields as $field) {
            if (($field->{$checkin_checkout} == 1) && request()->has($field->db_column)) {
                if ($field->field_encrypted == '1') {
                    if (Gate::allows('assets.view.encrypted_custom_fields')) {
                        $value = request()->input($field->db_column);
                        $this->{$field->db_column} = Crypt::encrypt(is_array($value) ? implode(', ', $value) : $value);
                    }
                } else {
                    $value = request()->input($field->db_column);
                    $this->{$field->db_column} = is_array($value) ? implode(', ', $value) : $value;
                }
            }
        }
    }
}
