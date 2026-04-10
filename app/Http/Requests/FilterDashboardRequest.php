<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class FilterDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audit_action' => ['nullable', 'string', 'max:120'],
            'audit_search' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'audit_action' => $this->normalizeString('audit_action'),
            'audit_search' => $this->normalizeString('audit_search'),
        ]);
    }

    private function normalizeString(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $normalized = Str::of($value)->trim()->toString();

        return $normalized === '' ? null : $normalized;
    }
}
