<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmTaskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:2', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_at' => [$required, 'date'],
            'lead_id' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'nullable', 'integer'],
            'assigned_to' => ['sometimes', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'completed' => ['sometimes', 'boolean'],
        ];
    }
}
