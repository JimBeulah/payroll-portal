import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { Auth } from '@/types/auth';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

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
    created_at: string;
}

interface LeaveRequest {
    id: number;
    start_date: string;
    end_date: string;
    reason: string;
    status: Status;
    review_note: string | null;
    employee: EmployeeRef | null;
    reviewer: { id: number; name: string } | null;
    created_at: string;
}

const statusVariant: Record<Status, 'secondary' | 'default' | 'destructive'> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
};

function StatusBadge({ status }: { status: Status }) {
    return (
        <Badge variant={statusVariant[status]} className="capitalize">
            {status}
        </Badge>
    );
}

function formatDate(value: string) {
    const [year, month, day] = value.split('T')[0].split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const monthAbbr = date.toLocaleDateString('en-US', { month: 'short' });
    return `${monthAbbr}. ${date.getDate()}, ${year}`;
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
    const { auth } = usePage<{ auth: Auth }>().props;
    const canEdit = auth.user?.role === 'admin' || auth.user?.role === 'hr';

    const [target, setTarget] = useState<ReviewTarget | null>(null);
    const form = useForm({ review_note: '' });
    const undoForm = useForm({});

    function undoReview(kind: RequestKind, id: number) {
        if (!window.confirm('Reject this request?')) {
            return;
        }

        undoForm.post(`/approvals/${kind}/${id}/reject`, {
            preserveScroll: true,
        });
    }

    const pendingCashAdvances = cashAdvances.filter(
        (r) => r.status === 'pending',
    ).length;
    const pendingLeaveRequests = leaveRequests.filter(
        (r) => r.status === 'pending',
    ).length;

    function openReview(
        kind: RequestKind,
        decision: Decision,
        id: number,
        label: string,
    ) {
        form.reset();
        form.clearErrors();
        setTarget({ kind, decision, id, label });
    }

    function submitReview(e: React.FormEvent) {
        e.preventDefault();

        if (!target) {
return;
}

        form.post(`/approvals/${target.kind}/${target.id}/${target.decision}`, {
            onSuccess: () => setTarget(null),
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Approvals" />
            <div className="space-y-8 p-6">
                <div>
                    <h1 className="text-2xl font-bold">Request Approvals</h1>
                    <p className="text-sm text-muted-foreground">
                        Review employee cash advance and leave requests.
                    </p>
                </div>

                <Tabs defaultValue="cash-advance" className="space-y-4">
                    <TabsList>
                        <TabsTrigger value="cash-advance">
                            Cash Advance Requests
                            {pendingCashAdvances > 0 && (
                                <Badge variant="secondary" className="ml-1.5">
                                    {pendingCashAdvances}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="leave">
                            Leave Requests
                            {pendingLeaveRequests > 0 && (
                                <Badge variant="secondary" className="ml-1.5">
                                    {pendingLeaveRequests}
                                </Badge>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="cash-advance" className="space-y-3">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Needed Date</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cashAdvances.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No cash advance requests.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    cashAdvances.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell>
                                                {r.employee?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                ₱
                                                {Number(
                                                    r.amount,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.needed_date)}
                                            </TableCell>
                                            <TableCell className="max-w-xs truncate">
                                                {r.reason ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={r.status}
                                                />
                                            </TableCell>
                                            <TableCell className="space-x-2 text-right">
                                                {canEdit && r.status === 'pending' ? (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                openReview(
                                                                    'cash-advance',
                                                                    'approve',
                                                                    r.id,
                                                                    `${r.employee?.name ?? 'employee'} — ₱${Number(r.amount).toLocaleString()}`,
                                                                )
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                openReview(
                                                                    'cash-advance',
                                                                    'reject',
                                                                    r.id,
                                                                    `${r.employee?.name ?? 'employee'} — ₱${Number(r.amount).toLocaleString()}`,
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <div className="flex flex-col items-end gap-1">
                                                        <span className="text-xs text-muted-foreground">
                                                            {r.reviewer
                                                                ? `by ${r.reviewer.name}`
                                                                : r.status === 'pending'
                                                                    ? 'Pending'
                                                                    : 'Reviewed'}
                                                        </span>
                                                        {canEdit && r.status === 'approved' && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    undoReview('cash-advance', r.id)
                                                                }
                                                            >
                                                                Undo (Reject)
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </TabsContent>

                    <TabsContent value="leave" className="space-y-3">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>From</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {leaveRequests.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No leave requests.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    leaveRequests.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell>
                                                {r.employee?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.start_date)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.end_date)}
                                            </TableCell>
                                            <TableCell className="max-w-xs truncate">
                                                {r.reason}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={r.status}
                                                />
                                            </TableCell>
                                            <TableCell className="space-x-2 text-right">
                                                {canEdit && r.status === 'pending' ? (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                openReview(
                                                                    'leave',
                                                                    'approve',
                                                                    r.id,
                                                                    `${r.employee?.name ?? 'employee'} — ${formatDate(r.start_date)}`,
                                                                )
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                openReview(
                                                                    'leave',
                                                                    'reject',
                                                                    r.id,
                                                                    `${r.employee?.name ?? 'employee'} — ${formatDate(r.start_date)}`,
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <div className="flex flex-col items-end gap-1">
                                                        <span className="text-xs text-muted-foreground">
                                                            {r.reviewer
                                                                ? `by ${r.reviewer.name}`
                                                                : r.status === 'pending'
                                                                    ? 'Pending'
                                                                    : 'Reviewed'}
                                                        </span>
                                                        {canEdit && r.status === 'approved' && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    undoReview('leave', r.id)
                                                                }
                                                            >
                                                                Undo (Reject)
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Review dialog */}
            <Dialog
                open={target !== null}
                onOpenChange={(open) => {
                    if (!open) {
setTarget(null);
}
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="capitalize">
                            {target?.decision} request
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitReview} className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {target?.label}
                        </p>
                        <div>
                            <Label>
                                Note{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                value={form.data.review_note}
                                onChange={(e) =>
                                    form.setData('review_note', e.target.value)
                                }
                                placeholder="Add a note for the employee…"
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    target?.decision === 'reject'
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={form.processing}
                            >
                                Confirm {target?.decision}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
