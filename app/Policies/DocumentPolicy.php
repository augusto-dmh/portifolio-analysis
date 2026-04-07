<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role instanceof UserRole;
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('view', $document->submission);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Document $document): bool
    {
        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        return false;
    }

    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
