<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'isActive' => $user->isActive(),
                'createdAt' => $user->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => Str::of($role->value)->title()->toString(),
                ])
                ->values()
                ->all(),
            'currentUserId' => $request->user()?->getKey(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->enum('role', UserRole::class),
            'password' => $request->validated('password'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return to_route('users.index')
            ->with('status', 'User created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->enum('role', UserRole::class),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return to_route('users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        return to_route('users.index')
            ->with('status', 'User deactivated.');
    }
}
