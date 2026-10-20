<?php

namespace App\Http\Requests;

use App\Support\DirectoryDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDirectoryRequest extends FormRequest
{
    public function module(): string
    {
        return explode('.', $this->route()->getName())[0];
    }

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', DirectoryDefinition::model($this->module()));
    }

    public function rules(): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']];
        if ($this->module() !== 'positions') {
            $rules['archived'] = ['nullable', Rule::in(['all', 'yes', 'no'])];
        }
        foreach (DirectoryDefinition::filters($this->module()) as $key) {
            $field = DirectoryDefinition::fields($this->module())[$key];
            $rules[$key] = $field[1] === 'select' ? ['nullable', Rule::in($field[3])]
                : ($field[1] === 'reference' ? ['nullable', 'integer', 'min:1'] : ['nullable', 'string', 'max:40']);
        }

        return $rules;
    }
}
