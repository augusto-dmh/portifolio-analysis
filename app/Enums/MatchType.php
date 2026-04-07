<?php

namespace App\Enums;

enum MatchType: string
{
    case Exact = 'exact';
    case TickerPrefix = 'ticker_prefix';
    case Contains = 'contains';
}
