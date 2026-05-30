import { useState, useMemo } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/payroll-runs';

interface PayrollRun {
    id: number;
    period_start: string;
    period_end: string;
    payable_date: string;
    status: 'draft' | 'locked';
}

const PAGE_SIZE = 10;

export default function PayrollIndex({ runs }: { runs: PayrollRun[] }) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<PayrollRun | null>(null);

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [page, setPage] = useState(1);

    const createForm = useForm({ period_start: '', period_end: '', payable_date: '' });

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return runs.filter(run => {
            const matchesSearch =
                !q ||
                run.period_start.includes(q) ||
                run.period_end.includes(q) ||
                run.payable_date.includes(q);
            const matchesStatus = statusFilter === 'all' || run.status === statusFilter;
            return matchesSearch && matchesStatus;
        });
    }, [runs, search, statusFilter]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const paginated = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

    function openCreate() {
        createForm.setData({ period_start: '', period_end: '', payable_date: '' });
        createForm.clearErrors();
        setCreateOpen(true);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/payroll-runs', { onSuccess: () => setCreateOpen(false) });
    }

    function confirmDelete() {
        router.delete(`/payroll-runs/${deleteTarget?.id}`, { onSuccess: () => setDeleteTarget(null) });
    }

    return (
        <>
            <Head title="Payroll Runs" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Payroll Runs</h1>
                    <Button onClick={openCreate}>New Payroll Run</Button>
                </div>

                {/* Search & Filter */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <Input
                        placeholder="Search by date…"
                        value={search}
                        onChange={e => { setSearch(e.target.value); setPage(1); }}
                        className="max-w-sm"
                    />
                    <Select value={statusFilter} onValueChange={v => { setStatusFilter(v); setPage(1); }}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="locked">Locked</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Period</TableHead>
                            <TableHead>Payable Date (yyyy/mm/dd)</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {paginated.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4} className="text-center text-muted-foreground py-8">
                                    No payroll runs found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            paginated.map((run) => (
                                <TableRow key={run.id}>
                                    <TableCell>{run.period_start} – {run.period_end}</TableCell>
                                    <TableCell>{run.payable_date}</TableCell>
                                    <TableCell>
                                        <Badge variant={run.status === 'locked' ? 'default' : 'secondary'}>
                                            {run.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="flex gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/payroll-runs/${run.id}`}>View</Link>
                                        </Button>
                                        {run.status === 'draft' && (
                                            <Button variant="destructive" size="sm" onClick={() => setDeleteTarget(run)}>
                                                Delete
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* Pagination */}
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {filtered.length === 0
                            ? 'No results'
                            : `${(page - 1) * PAGE_SIZE + 1}–${Math.min(page * PAGE_SIZE, filtered.length)} of ${filtered.length} run${filtered.length !== 1 ? 's' : ''}`}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => setPage(p => p - 1)} disabled={page === 1}>
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setPage(p => p + 1)} disabled={page === totalPages}>
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            {/* Create Dialog */}
            <Dialog open={createOpen} onOpenChange={(open) => { if (!open) setCreateOpen(false); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Payroll Run</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div>
                            <Label>Period Start</Label>
                            <Input type="date" value={createForm.data.period_start} onChange={e => createForm.setData('period_start', e.target.value)} />
                            <InputError message={createForm.errors.period_start} />
                        </div>
                        <div>
                            <Label>Period End</Label>
                            <Input type="date" value={createForm.data.period_end} onChange={e => createForm.setData('period_end', e.target.value)} />
                            <InputError message={createForm.errors.period_end} />
                        </div>
                        <div>
                            <Label>Payable Date</Label>
                            <Input type="date" value={createForm.data.payable_date} onChange={e => createForm.setData('payable_date', e.target.value)} />
                            <InputError message={createForm.errors.payable_date} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={createForm.processing}>Create Run</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Payroll Run</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete the payroll run for{' '}
                            <strong>{deleteTarget?.period_start} – {deleteTarget?.period_end}</strong>?
                            This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancel</Button>
                        <Button variant="destructive" onClick={confirmDelete}>Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PayrollIndex.layout = {
    breadcrumbs: [
        { title: 'Payroll Runs', href: index() },
    ],
};
