<?php

namespace App\Http\Requests;

use App\Models\Submission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Submission::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $acceptedExtensions = config('portfolio.upload.accepted_extensions', [
            'pdf',
            'png',
            'jpg',
            'jpeg',
            'csv',
            'xlsx',
        ]);
        $maxFilesPerSubmission = (int) config('portfolio.upload.max_files_per_submission', 20);
        $maxFileSizeInKilobytes = (int) config('portfolio.upload.max_file_size_mb', 50) * 1024;

        return [
            'email_lead' => ['nullable', 'email', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:'.$maxFilesPerSubmission],
            'documents.*' => [
                'required',
                File::types($acceptedExtensions)->max($maxFileSizeInKilobytes),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'documents.*' => 'document',
        ];
    }
}
