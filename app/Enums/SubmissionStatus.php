<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case PartiallyComplete = 'partially_complete';
    case Completed = 'completed';
    case Failed = 'failed';
}
