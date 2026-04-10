<?php

namespace App\Http\Requests;

use App\Enums\MatchType;
use App\Models\ClassificationRule;
use App\Support\ClassificationOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classificationOptions = new ClassificationOptions;

        return [
            'chave' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $exists = ClassificationRule::query()
                        ->where('chave_normalized', Str::upper(trim($value)))
                        ->where('match_type', $this->string('match_type')->toString())
                        ->exists();

                    if ($exists) {
                        $fail('A rule with this key and match type already exists.');
                    }
                },
            ],
            'classe' => ['required', 'string', 'max:255', Rule::in($classificationOptions->classes())],
            'estrategia' => ['required', 'string', 'max:255', Rule::in($classificationOptions->strategies())],
            'match_type' => ['required', Rule::enum(MatchType::class)],
            'priority' => ['required', 'integer', 'between:0,999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chave' => is_string($this->input('chave')) ? trim($this->input('chave')) : $this->input('chave'),
            'classe' => is_string($this->input('classe')) ? trim($this->input('classe')) : $this->input('classe'),
            'estrategia' => is_string($this->input('estrategia')) ? trim($this->input('estrategia')) : $this->input('estrategia'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
