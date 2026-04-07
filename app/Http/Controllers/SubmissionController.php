<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('submissions/index');
    }
}
