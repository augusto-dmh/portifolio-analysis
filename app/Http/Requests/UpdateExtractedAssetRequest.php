<?php

namespace App\Http\Requests;

use App\Models\ExtractedAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExtractedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExtractedAsset|null $asset */
        $asset = $this->route('asset');

        return ($this->user()?->can('analyst-or-above') ?? false)
            && $asset instanceof ExtractedAsset
            && $this->user()?->can('view', $asset->submission);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classe' => ['required', 'string', 'max:255'],
            'estrategia' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'classe' => is_string($this->input('classe')) ? trim($this->input('classe')) : $this->input('classe'),
            'estrategia' => is_string($this->input('estrategia')) ? trim($this->input('estrategia')) : $this->input('estrategia'),
        ]);
    }
}
