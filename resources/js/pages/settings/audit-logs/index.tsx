import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/audit-logs';

interface AuditLogRow {
    id: number;
    action: string;
    causer_name: string | null;
    subject_label: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: AuditLogRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface Filters {
    user?: string;
    action?: string;
    from?: string;
    to?: string;
    search?: string;
}

function formatAction(action: string): string {
    return action
        .split('.')
        .join(' ')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function AuditLogsIndex({
    logs,
    filters,
    actionOptions,
    userOptions,
}: {
    logs: PaginatedLogs;
    filters: Filters;
    actionOptions: string[];
    userOptions: { id: number; name: string }[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [action, setAction] = useState(filters.action ?? 'all');
    const [user, setUser] = useState(filters.user ?? 'all');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [details, setDetails] = useState<AuditLogRow | null>(null);

    function applyFilters(next: Partial<Filters>) {
        const merged = { search, action, user, from, to, ...next };
        router.get(
            index().url,
            {
                search: merged.search || undefined,
                action: merged.action && merged.action !== 'all' ? merged.action : undefined,
                user: merged.user && merged.user !== 'all' ? merged.user : undefined,
                from: merged.from || undefined,
                to: merged.to || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Audit Logs" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Audit Logs"
                    description="Track who performed sensitive payroll and account actions"
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <Label>Search</Label>
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onBlur={() => applyFilters({ search })}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters({ search });
                                }
                            }}
                            placeholder="Actor or subject"
                            className="w-48"
                        />
                    </div>
                    <div>
                        <Label>Action</Label>
                        <Select
                            value={action}
                            onValueChange={(v) => {
                                setAction(v);
                                applyFilters({ action: v });
                            }}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All actions</SelectItem>
                                {actionOptions.map((opt) => (
                                    <SelectItem key={opt} value={opt}>
                                        {formatAction(opt)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>User</Label>
                        <Select
                            value={user}
                            onValueChange={(v) => {
                                setUser(v);
                                applyFilters({ user: v });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All users</SelectItem>
                                {userOptions.map((opt) => (
                                    <SelectItem key={opt.id} value={String(opt.id)}>
                                        {opt.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>From</Label>
                        <Input
                            type="date"
                            value={from}
                            onChange={(e) => {
                                setFrom(e.target.value);
                                applyFilters({ from: e.target.value });
                            }}
                            className="w-40"
                        />
                    </div>
                    <div>
                        <Label>To</Label>
                        <Input
                            type="date"
                            value={to}
                            onChange={(e) => {
                                setTo(e.target.value);
                                applyFilters({ to: e.target.value });
                            }}
                            className="w-40"
                        />
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Timestamp</TableHead>
                            <TableHead>Actor</TableHead>
                            <TableHead>Action</TableHead>
                            <TableHead>Subject</TableHead>
                            <TableHead className="text-right">Details</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {logs.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    No audit log entries found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell>
                                        {new Date(log.created_at).toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        {log.causer_name ?? (
                                            <span className="text-muted-foreground">
                                                System
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {formatAction(log.action)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {log.subject_label ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={!log.metadata}
                                            onClick={() => setDetails(log)}
                                        >
                                            View
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {logs.total === 0
                            ? '0 results'
                            : `${logs.from}–${logs.to} of ${logs.total}`}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={logs.current_page === 1}
                            onClick={() =>
                                goToPage(
                                    logs.links.find((l) => l.label === '&laquo; Previous')
                                        ?.url ?? null,
                                )
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={logs.current_page === logs.last_page}
                            onClick={() =>
                                goToPage(
                                    logs.links.find((l) => l.label === 'Next &raquo;')
                                        ?.url ?? null,
                                )
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={details !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetails(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Details</DialogTitle>
                    </DialogHeader>
                    <pre className="max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                        {JSON.stringify(details?.metadata, null, 2)}
                    </pre>
                </DialogContent>
            </Dialog>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Audit Logs',
            href: index(),
        },
    ],
};
