<?php

namespace App\Http\Controllers;

use App\Enums\ClassificationSource;
use App\Enums\DocumentStatus;
use App\Http\Requests\UpdateExtractedAssetRequest;
use App\Models\ExtractedAsset;
use App\Services\DocumentStatusMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ExtractedAssetController extends Controller
{
    public function update(
        UpdateExtractedAssetRequest $request,
        ExtractedAsset $asset,
        DocumentStatusMachine $documentStatusMachine,
    ): RedirectResponse {
        $asset->loadMissing('document.submission');

        if (! in_array($asset->document->status, [
            DocumentStatus::ReadyForReview,
            DocumentStatus::Reviewed,
        ], true)) {
            throw ValidationException::withMessages([
                'classe' => 'This document is not available for analyst review.',
            ]);
        }

        $asset->forceFill([
            'classe' => $request->validated('classe'),
            'estrategia' => $request->validated('estrategia'),
            'classification_source' => ClassificationSource::Manual,
            'is_reviewed' => true,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'original_classe' => $asset->original_classe ?? $asset->classe,
            'original_estrategia' => $asset->original_estrategia ?? $asset->estrategia,
        ])->save();

        $asset->document->loadMissing('extractedAssets');

        $allAssetsReviewed = $asset->document->extractedAssets
            ->every(fn (ExtractedAsset $documentAsset): bool => $documentAsset->is_reviewed);

        if ($asset->document->status === DocumentStatus::ReadyForReview && $allAssetsReviewed) {
            $documentStatusMachine->transitionDocument(
                $asset->document,
                DocumentStatus::Reviewed,
                eventType: 'review_completed',
                triggeredBy: 'user',
                metadata: [
                    'reviewed_by' => $request->user()?->id,
                ],
            );
        }

        return back()->with('status', 'Asset review saved.');
    }
}
