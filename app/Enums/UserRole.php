<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Viewer = 'viewer';
}
