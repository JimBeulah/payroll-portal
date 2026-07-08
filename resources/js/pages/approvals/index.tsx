import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Status = 'pending' | 'approved' | 'rejected';
type RequestKind = 'cash-advance' | 'leave';
type Decision = 'approve' | 'reject';

interface EmployeeRef {
    id: number;
    name: string;
    department: string | null;
}

interface CashAdvance {
    id: number;
    amount: string;
    needed_date: string;
    reason: string | null;
    status: Status;
    review_note: string | null;
    employee: EmployeeRef | null;
    reviewer: { id: number; name: string } | null;
}

interface LeaveRequest {
    id: number;
    type: 'leave' | 'absent';
    start_date: string;
    end_date: string;
    reason: string | null;
    status: Status;
    review_note: string | null;
    employee: EmployeeRef | null;
    reviewer: { id: number; name: string } | null;
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

interface ReviewTarget {
    kind: RequestKind;
    decision: Decision;
    id: number;
    label: string;
}

export default function ApprovalsIndex({
    cashAdvances,
    leaveRequests,
}: {
    cashAdvances: CashAdvance[];
    leaveRequests: LeaveRequest[];
}) {
    const [target, setTarget] = useState<ReviewTarget | null>(null);
    const form = useForm({ review_note: '' });

    function openReview(kind: RequestKind, decision: Decision, id: number, label: string) {
        form.reset();
        form.clearErrors();
        setTarget({ kind, decision, id, label });
    }

    function submitReview(e: React.FormEvent) {
        e.preventDefault();
        if (!target) return;
        form.post(`/approvals/${target.kind}/${target.id}/${target.decision}`, {
            onSuccess: () => setTarget(null),
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Approvals" />
            <div className="p-6 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold">Request Approvals</h1>
                    <p className="text-sm text-muted-foreground">Review employee cash advance and leave/absence requests.</p>
                </div>

                {/* Cash advances */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Cash Advance Requests</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead>Needed Date</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cashAdvances.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground py-8">No cash advance requests.</TableCell>
                                </TableRow>
                            ) : (
                                cashAdvances.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>{r.employee?.name ?? '—'}</TableCell>
                                        <TableCell>₱{Number(r.amount).toLocaleString()}</TableCell>
                                        <TableCell>{formatDate(r.needed_date)}</TableCell>
                                        <TableCell className="max-w-xs truncate">{r.reason ?? <span className="text-muted-foreground">—</span>}</TableCell>
                                        <TableCell><StatusBadge status={r.status} /></TableCell>
                                        <TableCell className="text-right space-x-2">
                                            {r.status === 'pending' ? (
                                                <>
                                                    <Button size="sm" onClick={() => openReview('cash-advance', 'approve', r.id, `${r.employee?.name ?? 'employee'} — ₱${Number(r.amount).toLocaleString()}`)}>Approve</Button>
                                                    <Button size="sm" variant="destructive" onClick={() => openReview('cash-advance', 'reject', r.id, `${r.employee?.name ?? 'employee'} — ₱${Number(r.amount).toLocaleString()}`)}>Reject</Button>
                                                </>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">{r.reviewer ? `by ${r.reviewer.name}` : 'Reviewed'}</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>

                {/* Leave / absence */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Leave / Absence Requests</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leaveRequests.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground py-8">No leave or absence requests.</TableCell>
                                </TableRow>
                            ) : (
                                leaveRequests.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>{r.employee?.name ?? '—'}</TableCell>
                                        <TableCell className="capitalize">{r.type}</TableCell>
                                        <TableCell>{formatDate(r.start_date)}</TableCell>
                                        <TableCell>{formatDate(r.end_date)}</TableCell>
                                        <TableCell><StatusBadge status={r.status} /></TableCell>
                                        <TableCell className="text-right space-x-2">
                                            {r.status === 'pending' ? (
                                                <>
                                                    <Button size="sm" onClick={() => openReview('leave', 'approve', r.id, `${r.employee?.name ?? 'employee'} — ${r.type}`)}>Approve</Button>
                                                    <Button size="sm" variant="destructive" onClick={() => openReview('leave', 'reject', r.id, `${r.employee?.name ?? 'employee'} — ${r.type}`)}>Reject</Button>
                                                </>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">{r.reviewer ? `by ${r.reviewer.name}` : 'Reviewed'}</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>
            </div>

            {/* Review dialog */}
            <Dialog open={target !== null} onOpenChange={(open) => { if (!open) setTarget(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="capitalize">
                            {target?.decision} request
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitReview} className="space-y-4">
                        <p className="text-sm text-muted-foreground">{target?.label}</p>
                        <div>
                            <Label>Note <span className="text-muted-foreground font-normal">(optional)</span></Label>
                            <Input value={form.data.review_note} onChange={(e) => form.setData('review_note', e.target.value)} placeholder="Add a note for the employee…" />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setTarget(null)}>Cancel</Button>
                            <Button type="submit" variant={target?.decision === 'reject' ? 'destructive' : 'default'} disabled={form.processing}>
                                Confirm {target?.decision}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
