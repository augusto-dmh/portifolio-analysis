<?php

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('submission.{submission}', function (User $user, string $submission): bool {
    $submissionModel = Submission::query()->find($submission);

    return $submissionModel !== null && $user->can('view', $submissionModel);
});
