<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case Extracted = 'extracted';
    case ExtractionFailed = 'extraction_failed';
    case Classifying = 'classifying';
    case Classified = 'classified';
    case ClassificationFailed = 'classification_failed';
    case ReadyForReview = 'ready_for_review';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
}
