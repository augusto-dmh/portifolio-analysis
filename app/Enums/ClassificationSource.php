<?php

namespace App\Enums;

enum ClassificationSource: string
{
    case Base1 = 'base1';
    case Deterministic = 'deterministic';
    case Ai = 'ai';
    case Manual = 'manual';
}
