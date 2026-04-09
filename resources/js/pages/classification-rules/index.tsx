import { Form, Head, useForm } from '@inertiajs/react';
import ClassificationRuleController from '@/actions/App/Http/Controllers/ClassificationRuleController';
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
import { index as classificationRulesIndex } from '@/routes/classification-rules';

type ClassificationRule = {
    id: number;
    chave: string;
    classe: string;
    estrategia: string;
    matchType: string;
    priority: number;
    isActive: boolean;
    createdAt: string | null;
    creatorName: string | null;
};

type MatchTypeOption = {
    value: string;
    label: string;
};

type ClassificationOptions = {
    classes: string[];
    strategies: string[];
};

type RuleFilters = {
    search: string;
    matchType: string;
    active: string;
};

export default function ClassificationRulesIndex({
    rules,
    filters,
    matchTypes,
    classificationOptions,
    status,
}: {
    rules: ClassificationRule[];
    filters: RuleFilters;
    matchTypes: MatchTypeOption[];
    classificationOptions: ClassificationOptions;
    status?: string;
}) {
    const createForm = useForm({
        chave: '',
        classe: classificationOptions.classes[0] ?? '',
        estrategia: classificationOptions.strategies[0] ?? '',
        match_type: matchTypes[0]?.value ?? 'exact',
        priority: 0,
        is_active: true,
    });
    const importForm = useForm<{
        file: File | null;
    }>({
        file: null,
    });
    const exportUrl = ClassificationRuleController.export.url({
        query: {
            search: filters.search || undefined,
            match_type: filters.matchType || undefined,
            active: filters.active === 'all' ? undefined : filters.active,
        },
    });

    return (
        <>
            <Head title="Classification Rules" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <section className="space-y-2">
                    <p className="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Admin Workspace
                    </p>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Classification rule catalog
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Admins can now search, create, adjust, and retire
                            deterministic rules directly from this workspace.
                        </p>
                    </div>
                </section>

                {status && (
                    <Alert>
                        <AlertTitle>Rules updated</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 xl:grid-cols-[0.85fr_1.15fr]">
                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Create rule</CardTitle>
                            <CardDescription>
                                Add an exact, contains, or ticker-prefix rule to
                                strengthen deterministic classification.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    createForm.post(
                                        ClassificationRuleController.store.url(),
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="chave">Rule key</Label>
                                    <Input
                                        id="chave"
                                        value={createForm.data.chave}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'chave',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="PETR4 or TESOURO SELIC"
                                    />
                                    <InputError
                                        message={createForm.errors.chave}
                                    />
                                </div>

                                <div className="grid gap-4 lg:grid-cols-2">
                                    <RuleSelectField
                                        id="classe"
                                        label="Classe"
                                        value={createForm.data.classe}
                                        options={classificationOptions.classes}
                                        error={createForm.errors.classe}
                                        onChange={(value) =>
                                            createForm.setData('classe', value)
                                        }
                                    />
                                    <RuleSelectField
                                        id="estrategia"
                                        label="Estratégia"
                                        value={createForm.data.estrategia}
                                        options={
                                            classificationOptions.strategies
                                        }
                                        error={createForm.errors.estrategia}
                                        onChange={(value) =>
                                            createForm.setData(
                                                'estrategia',
                                                value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 lg:grid-cols-[1fr_120px_auto]">
                                    <RuleSelectField
                                        id="match_type"
                                        label="Match type"
                                        value={createForm.data.match_type}
                                        options={matchTypes.map(
                                            (option) => option.value,
                                        )}
                                        optionLabels={Object.fromEntries(
                                            matchTypes.map((option) => [
                                                option.value,
                                                option.label,
                                            ]),
                                        )}
                                        error={createForm.errors.match_type}
                                        onChange={(value) =>
                                            createForm.setData(
                                                'match_type',
                                                value,
                                            )
                                        }
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="priority">
                                            Priority
                                        </Label>
                                        <Input
                                            id="priority"
                                            type="number"
                                            min={0}
                                            max={999}
                                            value={createForm.data.priority}
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'priority',
                                                    Number(
                                                        event.target.value || 0,
                                                    ),
                                                )
                                            }
                                        />
                                        <InputError
                                            message={createForm.errors.priority}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="is_active">
                                            Status
                                        </Label>
                                        <select
                                            id="is_active"
                                            value={
                                                createForm.data.is_active
                                                    ? '1'
                                                    : '0'
                                            }
                                            onChange={(event) =>
                                                createForm.setData(
                                                    'is_active',
                                                    event.target.value === '1',
                                                )
                                            }
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <InputError
                                            message={
                                                createForm.errors.is_active
                                            }
                                        />
                                    </div>
                                </div>

                                <Button disabled={createForm.processing}>
                                    Create rule
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/70">
                        <CardHeader>
                            <CardTitle>Rule catalog</CardTitle>
                            <CardDescription>
                                Filter the existing catalog and update rules
                                inline without leaving the table.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex flex-col gap-4 rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4 lg:flex-row lg:items-end lg:justify-between">
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        Bulk CSV workflow
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Export the rule catalog for offline
                                        edits or import a CSV to create and
                                        update rules in bulk.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                                    <form
                                        className="flex flex-col gap-3 lg:flex-row lg:items-end"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            importForm.post(
                                                ClassificationRuleController.import.url(),
                                                {
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="rules_csv">
                                                CSV file
                                            </Label>
                                            <Input
                                                id="rules_csv"
                                                type="file"
                                                accept=".csv,text/csv"
                                                onChange={(event) =>
                                                    importForm.setData(
                                                        'file',
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={importForm.errors.file}
                                            />
                                        </div>

                                        <Button
                                            disabled={
                                                importForm.processing ||
                                                importForm.data.file === null
                                            }
                                            variant="outline"
                                        >
                                            Import CSV
                                        </Button>
                                    </form>

                                    <Button asChild>
                                        <a href={exportUrl}>Export CSV</a>
                                    </Button>
                                </div>
                            </div>

                            <Form
                                action={classificationRulesIndex.url()}
                                method={classificationRulesIndex().method}
                                className="grid gap-4 rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4 lg:grid-cols-[1.4fr_1fr_1fr_auto_auto]"
                                options={{
                                    preserveScroll: true,
                                    preserveState: true,
                                }}
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="search">
                                                Search
                                            </Label>
                                            <Input
                                                id="search"
                                                name="search"
                                                defaultValue={filters.search}
                                                placeholder="PETR4, Ações, Renda Fixa"
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="match_type_filter">
                                                Match type
                                            </Label>
                                            <select
                                                id="match_type_filter"
                                                name="match_type"
                                                defaultValue={filters.matchType}
                                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="">
                                                    Any type
                                                </option>
                                                {matchTypes.map((option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="active_filter">
                                                Status
                                            </Label>
                                            <select
                                                id="active_filter"
                                                name="active"
                                                defaultValue={filters.active}
                                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="all">All</option>
                                                <option value="active">
                                                    Active
                                                </option>
                                                <option value="inactive">
                                                    Inactive
                                                </option>
                                            </select>
                                        </div>

                                        <div className="flex items-end">
                                            <Button disabled={processing}>
                                                Apply filters
                                            </Button>
                                        </div>

                                        <div className="flex items-end">
                                            <Button asChild variant="outline">
                                                <a
                                                    href={
                                                        classificationRulesIndex()
                                                            .url
                                                    }
                                                >
                                                    Reset
                                                </a>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            {rules.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-8 text-sm text-muted-foreground">
                                    No rules match this filter set.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {rules.map((rule) => (
                                        <RuleRow
                                            key={rule.id}
                                            rule={rule}
                                            matchTypes={matchTypes}
                                            classificationOptions={
                                                classificationOptions
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ClassificationRulesIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Classification Rules',
                href: classificationRulesIndex(),
            },
        ]}
    >
        {page}
    </AppLayout>
);

function RuleRow({
    rule,
    matchTypes,
    classificationOptions,
}: {
    rule: ClassificationRule;
    matchTypes: MatchTypeOption[];
    classificationOptions: ClassificationOptions;
}) {
    const form = useForm({
        chave: rule.chave,
        classe: rule.classe,
        estrategia: rule.estrategia,
        match_type: rule.matchType,
        priority: rule.priority,
        is_active: rule.isActive,
    });

    return (
        <div className="rounded-2xl border border-sidebar-border/70 bg-card p-4">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(
                        ClassificationRuleController.update.url({
                            classificationRule: rule.id,
                        }),
                        {
                            preserveScroll: true,
                        },
                    );
                }}
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={rule.isActive ? 'default' : 'outline'}
                            >
                                {rule.isActive ? 'Active' : 'Inactive'}
                            </Badge>
                            <Badge variant="secondary">
                                {
                                    matchTypes.find(
                                        (option) =>
                                            option.value === rule.matchType,
                                    )?.label
                                }
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Created by {rule.creatorName ?? 'System'}
                            {rule.createdAt
                                ? ` on ${formatDate(rule.createdAt)}`
                                : ''}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            form.delete(
                                ClassificationRuleController.destroy.url({
                                    classificationRule: rule.id,
                                }),
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        Delete
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.1fr_1fr_1fr_160px_120px_120px]">
                    <div className="grid gap-2">
                        <Label htmlFor={`chave-${rule.id}`}>Rule key</Label>
                        <Input
                            id={`chave-${rule.id}`}
                            value={form.data.chave}
                            onChange={(event) =>
                                form.setData('chave', event.target.value)
                            }
                        />
                        <InputError message={form.errors.chave} />
                    </div>

                    <RuleSelectField
                        id={`classe-${rule.id}`}
                        label="Classe"
                        value={form.data.classe}
                        options={classificationOptions.classes}
                        error={form.errors.classe}
                        onChange={(value) => form.setData('classe', value)}
                    />

                    <RuleSelectField
                        id={`estrategia-${rule.id}`}
                        label="Estratégia"
                        value={form.data.estrategia}
                        options={classificationOptions.strategies}
                        error={form.errors.estrategia}
                        onChange={(value) => form.setData('estrategia', value)}
                    />

                    <RuleSelectField
                        id={`match_type-${rule.id}`}
                        label="Match type"
                        value={form.data.match_type}
                        options={matchTypes.map((option) => option.value)}
                        optionLabels={Object.fromEntries(
                            matchTypes.map((option) => [
                                option.value,
                                option.label,
                            ]),
                        )}
                        error={form.errors.match_type}
                        onChange={(value) => form.setData('match_type', value)}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor={`priority-${rule.id}`}>Priority</Label>
                        <Input
                            id={`priority-${rule.id}`}
                            type="number"
                            min={0}
                            max={999}
                            value={form.data.priority}
                            onChange={(event) =>
                                form.setData(
                                    'priority',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                        <InputError message={form.errors.priority} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`is_active-${rule.id}`}>Status</Label>
                        <select
                            id={`is_active-${rule.id}`}
                            value={form.data.is_active ? '1' : '0'}
                            onChange={(event) =>
                                form.setData(
                                    'is_active',
                                    event.target.value === '1',
                                )
                            }
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <InputError message={form.errors.is_active} />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button disabled={form.processing}>Save changes</Button>
                </div>
            </form>
        </div>
    );
}

function RuleSelectField({
    id,
    label,
    value,
    options,
    error,
    onChange,
    optionLabels,
}: {
    id: string;
    label: string;
    value: string;
    options: string[];
    error?: string;
    onChange: (value: string) => void;
    optionLabels?: Record<string, string>;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {options.map((option) => (
                    <option key={option} value={option}>
                        {optionLabels?.[option] ?? option}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
