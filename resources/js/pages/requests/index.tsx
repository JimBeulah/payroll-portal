import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import InputError from '@/components/input-error';

type Status = 'pending' | 'approved' | 'rejected';

interface CashAdvance {
    id: number;
    amount: string;
    needed_date: string;
    reason: string | null;
    status: Status;
    review_note: string | null;
    created_at: string;
}

interface LeaveRequest {
    id: number;
    type: 'leave' | 'absent';
    start_date: string;
    end_date: string;
    reason: string | null;
    status: Status;
    review_note: string | null;
    created_at: string;
}

interface EmployeeInfo {
    id: number;
    name: string;
    employee_number: string | null;
    department: string | null;
}

const statusVariant: Record<Status, 'secondary' | 'default' | 'destructive'> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
};

function StatusBadge({ status }: { status: Status }) {
    return <Badge variant={statusVariant[status]} className="capitalize">{status}</Badge>;
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function RequestsIndex({
    employee,
    cashAdvances,
    leaveRequests,
}: {
    employee: EmployeeInfo;
    cashAdvances: CashAdvance[];
    leaveRequests: LeaveRequest[];
}) {
    const [dialog, setDialog] = useState<'cash' | 'leave' | null>(null);

    const cashForm = useForm({ amount: '', needed_date: '', reason: '' });
    const leaveForm = useForm({ type: 'leave', start_date: '', end_date: '', reason: '' });

    function openCash() {
        cashForm.reset();
        cashForm.clearErrors();
        setDialog('cash');
    }

    function openLeave() {
        leaveForm.reset();
        leaveForm.clearErrors();
        setDialog('leave');
    }

    function submitCash(e: React.FormEvent) {
        e.preventDefault();
        cashForm.post('/my-requests/cash-advance', { onSuccess: () => setDialog(null) });
    }

    function submitLeave(e: React.FormEvent) {
        e.preventDefault();
        leaveForm.post('/my-requests/leave', { onSuccess: () => setDialog(null) });
    }

    return (
        <>
            <Head title="My Requests" />
            <div className="p-6 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold">My Requests</h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.name}
                        {employee.employee_number ? ` · ${employee.employee_number}` : ''}
                        {employee.department ? ` · ${employee.department}` : ''}
                    </p>
                </div>

                {/* Cash advances */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Cash Advance Requests</h2>
                        <Button onClick={openCash}>New Cash Advance</Button>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Amount</TableHead>
                                <TableHead>Needed Date</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Submitted</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cashAdvances.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        No cash advance requests yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                cashAdvances.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>₱{Number(r.amount).toLocaleString()}</TableCell>
                                        <TableCell>{formatDate(r.needed_date)}</TableCell>
                                        <TableCell className="max-w-xs truncate">{r.reason ?? <span className="text-muted-foreground">—</span>}</TableCell>
                                        <TableCell><StatusBadge status={r.status} /></TableCell>
                                        <TableCell>{formatDate(r.created_at)}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>

                {/* Leave / absence */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Leave / Absence Requests</h2>
                        <Button onClick={openLeave}>New Leave / Absence</Button>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leaveRequests.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        No leave or absence requests yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                leaveRequests.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell className="capitalize">{r.type}</TableCell>
                                        <TableCell>{formatDate(r.start_date)}</TableCell>
                                        <TableCell>{formatDate(r.end_date)}</TableCell>
                                        <TableCell className="max-w-xs truncate">{r.reason ?? <span className="text-muted-foreground">—</span>}</TableCell>
                                        <TableCell><StatusBadge status={r.status} /></TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>
            </div>

            {/* Cash advance dialog */}
            <Dialog open={dialog === 'cash'} onOpenChange={(open) => { if (!open) setDialog(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Cash Advance Request</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCash} className="space-y-4">
                        <div>
                            <Label>Amount (₱)</Label>
                            <Input type="number" step="0.01" min="0" value={cashForm.data.amount} onChange={(e) => cashForm.setData('amount', e.target.value)} />
                            <InputError message={cashForm.errors.amount} />
                        </div>
                        <div>
                            <Label>Needed Date</Label>
                            <Input type="date" value={cashForm.data.needed_date} onChange={(e) => cashForm.setData('needed_date', e.target.value)} />
                            <InputError message={cashForm.errors.needed_date} />
                        </div>
                        <div>
                            <Label>Reason <span className="text-muted-foreground font-normal">(optional)</span></Label>
                            <Input value={cashForm.data.reason} onChange={(e) => cashForm.setData('reason', e.target.value)} />
                            <InputError message={cashForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialog(null)}>Cancel</Button>
                            <Button type="submit" disabled={cashForm.processing}>Submit Request</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Leave / absence dialog */}
            <Dialog open={dialog === 'leave'} onOpenChange={(open) => { if (!open) setDialog(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Leave / Absence Request</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitLeave} className="space-y-4">
                        <div>
                            <Label>Type</Label>
                            <Select value={leaveForm.data.type} onValueChange={(v) => leaveForm.setData('type', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="leave">Leave</SelectItem>
                                    <SelectItem value="absent">Absent</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={leaveForm.errors.type} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>From</Label>
                                <Input type="date" value={leaveForm.data.start_date} onChange={(e) => leaveForm.setData('start_date', e.target.value)} />
                                <InputError message={leaveForm.errors.start_date} />
                            </div>
                            <div>
                                <Label>To</Label>
                                <Input type="date" value={leaveForm.data.end_date} onChange={(e) => leaveForm.setData('end_date', e.target.value)} />
                                <InputError message={leaveForm.errors.end_date} />
                            </div>
                        </div>
                        <div>
                            <Label>Reason <span className="text-muted-foreground font-normal">(optional)</span></Label>
                            <Input value={leaveForm.data.reason} onChange={(e) => leaveForm.setData('reason', e.target.value)} />
                            <InputError message={leaveForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialog(null)}>Cancel</Button>
                            <Button type="submit" disabled={leaveForm.processing}>Submit Request</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
