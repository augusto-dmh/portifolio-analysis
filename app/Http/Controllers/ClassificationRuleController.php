<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ClassificationRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('classification-rules/index');
    }
}
