import { Head, useForm } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/users';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: RoleOption['value'];
    isActive: boolean;
    createdAt: string | null;
};

type RoleOption = {
    value: 'admin' | 'analyst' | 'viewer';
    label: string;
};

export default function UsersIndex({
    users,
    roles,
    currentUserId,
    status,
}: {
    users: ManagedUser[];
    roles: RoleOption[];
    currentUserId: number | null;
    status?: string;
}) {
    const createForm = useForm({
        name: '',
        email: '',
        role: roles[0]?.value ?? 'viewer',
        password: '',
        password_confirmation: '',
        is_active: true,
    });

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Admin Workspace
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Manage team access and account status
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Admins can create accounts, adjust roles, and
                            deactivate access without leaving the protected
                            workspace.
                        </p>
                    </div>
                </section>

                {status && (
                    <Alert>
                        <AlertTitle>User management updated</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 xl:grid-cols-[0.82fr_1.18fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Create user</CardTitle>
                            <CardDescription>
                                Add a new admin, analyst, or viewer account.
                                Accounts are active by default.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    createForm.post(
                                        UserController.store.url(),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                createForm.reset(
                                                    'name',
                                                    'email',
                                                    'password',
                                                    'password_confirmation',
                                                );
                                                createForm.setData(
                                                    'role',
                                                    roles[0]?.value ?? 'viewer',
                                                );
                                                createForm.setData(
                                                    'is_active',
                                                    true,
                                                );
                                            },
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-4 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            value={createForm.data.name}
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Portfolio Analyst"
                                        />
                                        <InputError
                                            message={createForm.errors.name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={createForm.data.email}
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="analyst@example.com"
                                        />
                                        <InputError
                                            message={createForm.errors.email}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 lg:grid-cols-2">
                                    <RoleField
                                        id="role"
                                        label="Role"
                                        roles={roles}
                                        value={createForm.data.role}
                                        error={createForm.errors.role}
                                        onChange={(value) =>
                                            createForm.setData('role', value)
                                        }
                                    />

                                    <StatusField
                                        id="is_active"
                                        label="Status"
                                        value={createForm.data.is_active}
                                        error={createForm.errors.is_active}
                                        onChange={(value) =>
                                            createForm.setData(
                                                'is_active',
                                                value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Password
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={createForm.data.password}
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'password',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={createForm.errors.password}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={
                                                createForm.data
                                                    .password_confirmation
                                            }
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'password_confirmation',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <Button disabled={createForm.processing}>
                                    Create user
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>User directory</CardTitle>
                            <CardDescription>
                                Review current accounts, change roles, and
                                deactivate access. Admins cannot edit or
                                deactivate their own account from this screen.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {users.map((user) => (
                                    <UserRow
                                        key={user.id}
                                        user={user}
                                        roles={roles}
                                        isCurrentUser={
                                            currentUserId === user.id
                                        }
                                    />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Users',
                href: usersIndex(),
            },
        ]}
    >
        {page}
    </AppLayout>
);

function UserRow({
    user,
    roles,
    isCurrentUser,
}: {
    user: ManagedUser;
    roles: RoleOption[];
    isCurrentUser: boolean;
}) {
    const form = useForm({
        name: user.name,
        email: user.email,
        role: user.role,
        is_active: user.isActive,
    });

    return (
        <div className="rounded-2xl border border-sidebar-border/70 bg-card p-4">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(UserController.update.url({ user: user.id }), {
                        preserveScroll: true,
                    });
                }}
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={user.isActive ? 'default' : 'outline'}
                            >
                                {user.isActive ? 'Active' : 'Inactive'}
                            </Badge>
                            <Badge variant={roleBadgeVariant(user.role)}>
                                {roleLabel(user.role, roles)}
                            </Badge>
                            {isCurrentUser && (
                                <Badge variant="secondary">Current user</Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {user.createdAt
                                ? `Created ${formatDate(user.createdAt)}`
                                : 'Created recently'}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        disabled={isCurrentUser || !form.data.is_active}
                        onClick={() =>
                            form.delete(
                                UserController.destroy.url({ user: user.id }),
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        Deactivate
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.2fr_1.2fr_180px_140px]">
                    <div className="grid gap-2">
                        <Label htmlFor={`name-${user.id}`}>Name</Label>
                        <Input
                            id={`name-${user.id}`}
                            value={form.data.name}
                            disabled={isCurrentUser}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`email-${user.id}`}>Email</Label>
                        <Input
                            id={`email-${user.id}`}
                            type="email"
                            value={form.data.email}
                            disabled={isCurrentUser}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <RoleField
                        id={`role-${user.id}`}
                        label="Role"
                        roles={roles}
                        value={form.data.role}
                        error={form.errors.role}
                        disabled={isCurrentUser}
                        onChange={(value) => form.setData('role', value)}
                    />

                    <StatusField
                        id={`status-${user.id}`}
                        label="Status"
                        value={form.data.is_active}
                        error={form.errors.is_active}
                        disabled={isCurrentUser}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button disabled={isCurrentUser || form.processing}>
                        Save changes
                    </Button>
                    {isCurrentUser && (
                        <p className="text-sm text-muted-foreground">
                            Self-management stays disabled to prevent accidental
                            lockout.
                        </p>
                    )}
                </div>
            </form>
        </div>
    );
}

function RoleField({
    id,
    label,
    roles,
    value,
    error,
    onChange,
    disabled = false,
}: {
    id: string;
    label: string;
    roles: RoleOption[];
    value: RoleOption['value'];
    error?: string;
    onChange: (value: RoleOption['value']) => void;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                disabled={disabled}
                onChange={(event) =>
                    onChange(event.target.value as RoleOption['value'])
                }
                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {roles.map((role) => (
                    <option key={role.value} value={role.value}>
                        {role.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function StatusField({
    id,
    label,
    value,
    error,
    onChange,
    disabled = false,
}: {
    id: string;
    label: string;
    value: boolean;
    error?: string;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value ? '1' : '0'}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value === '1')}
                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <InputError message={error} />
        </div>
    );
}

function roleLabel(value: RoleOption['value'], roles: RoleOption[]): string {
    return roles.find((role) => role.value === value)?.label ?? value;
}

function roleBadgeVariant(
    role: RoleOption['value'],
): 'default' | 'secondary' | 'outline' {
    if (role === 'admin') {
        return 'default';
    }

    if (role === 'analyst') {
        return 'secondary';
    }

    return 'outline';
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
