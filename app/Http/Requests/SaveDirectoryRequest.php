<?php

namespace App\Http\Requests;

use App\Support\DirectoryDefinition;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDirectoryRequest extends FormRequest
{
    public function module(): string
    {
        return explode('.', $this->route()->getName())[0];
    }

    public function record(): mixed
    {
        return $this->route(rtrim($this->module(), 's'));
    }

    public function authorize(): bool
    {
        return $this->record() ? $this->user()->can('update', $this->record())
            : $this->user()->can('create', DirectoryDefinition::model($this->module()));
    }

    public function rules(): array
    {
        $rules = ['organization_id' => ['missing'], 'operational_unit_id' => ['missing'], 'deleted_at' => ['missing']];
        foreach (DirectoryDefinition::fields($this->module()) as $key => [$label, $type, $required, $options]) {
            $rule = [$required ? 'required' : 'nullable'];
            if ($type === 'select') {
                $rule[] = Rule::in($options);
            } elseif ($type === 'reference') {
                $rule = [...$rule, 'integer', 'min:1'];
            } elseif ($type === 'number') {
                $rule = [...$rule, 'integer', 'between:0,4294967295'];
            } elseif ($type === 'decimal') {
                $rule = [...$rule, 'numeric', 'min:0', $key === 'base_salary' ? 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/' : 'regex:/\A\d{1,4}(?:\.\d{1,4})?\z/'];
            } elseif ($type === 'date') {
                $rule[] = 'date_format:Y-m-d';
            } elseif ($type === 'json') {
                $rule = [...$rule, 'string', 'max:10000', 'json', function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! is_array(json_decode($value, true))) {
                        $fail('Isi JSON berupa object atau array.');
                    }
                }];
            } else {
                $rule = [...$rule, 'string', 'max:'.$options];
                if ($type === 'email') {
                    $rule[] = 'email';
                }
            }
            $rules[$key] = $rule;
        }
        $code = $this->module() === 'employees' ? 'employee_no' : 'code';
        $rules[$code][] = 'regex:/\A[A-Za-z0-9_-]+\z/';
        $rules[$code][] = Rule::unique($this->module(), $code)->where('organization_id', $this->user()->organization_id)->ignore($this->record()?->id);
        if ($this->module() === 'employees') {
            $org = $this->user()->organization_id;
            $rules['position_id'][] = Rule::exists('positions', 'id')->where('organization_id', $org);
            $rules['home_unit_id'][] = Rule::exists('operational_units', 'id')->where('organization_id', $org)->whereNull('deleted_at');
            $rules['user_id'][] = Rule::exists('users', 'id')->where('organization_id', $org)->whereNull('deleted_at');
            $rules['user_id'][] = Rule::unique('employees', 'user_id')->ignore($this->record()?->id);
            if ($this->filled('ended_on')) {
                $rules['joined_on'][] = 'required';
                $rules['ended_on'][] = 'after_or_equal:joined_on';
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return array_map(fn ($field) => $field[0], DirectoryDefinition::fields($this->module()));
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'unique' => ':attribute sudah digunakan, termasuk pada data yang diarsipkan.',
            'exists' => ':attribute tidak tersedia dalam organization Anda.', 'missing' => ':attribute ditentukan oleh sistem.',
            'json' => ':attribute harus berupa JSON valid.', 'in' => 'Pilihan :attribute tidak valid.', 'max' => ':attribute melebihi batas :max.',
            'regex' => 'Format :attribute tidak valid.', 'after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal bergabung.'];
    }
}
