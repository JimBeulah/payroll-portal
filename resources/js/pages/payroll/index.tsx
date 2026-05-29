import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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

export default function PayrollIndex({ runs }: { runs: PayrollRun[] }) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<PayrollRun | null>(null);

    const createForm = useForm({ period_start: '', period_end: '', payable_date: '' });

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
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Period</TableHead>
                            <TableHead>Payable Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {runs.map((run) => (
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
                        ))}
                    </TableBody>
                </Table>
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
