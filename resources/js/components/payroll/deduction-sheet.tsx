import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/input-error';
import { PayrollEntry } from './payroll-summary-table';

interface Props {
    entry: PayrollEntry | null;
    open: boolean;
    onClose: () => void;
}

function fmt(v: string) {
    return `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

function StatRow({ label, value, red }: { label: string; value: string; red?: boolean }) {
    return (
        <div className="flex justify-between items-center text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className={red ? 'text-red-500 font-medium' : 'font-medium'}>{value}</span>
        </div>
    );
}

export default function DeductionSheet({ entry, open, onClose }: Props) {
    const { data, setData, put, processing, errors, reset } = useForm({
        cash_advance: '0',
        other_deductions: '0',
    });

    useEffect(() => {
        if (entry) {
            setData({
                cash_advance: entry.cash_advance,
                other_deductions: entry.other_deductions,
            });
        }
    }, [entry?.id]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!entry) return;
        put(`/payroll-entries/${entry.id}`, {
            onSuccess: () => { reset(); onClose(); },
        });
    }

    return (
        <Sheet open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <SheetContent className="flex flex-col gap-0 p-0 sm:max-w-md">
                <SheetHeader className="px-6 pt-6 pb-4">
                    <SheetTitle className="text-lg">{entry?.employee?.name ?? '(deleted employee)'}</SheetTitle>
                    <SheetDescription>{entry?.employee?.department ?? '—'}</SheetDescription>
                </SheetHeader>

                <Separator />

                {/* Pay summary */}
                {entry && (
                    <div className="px-6 py-4 bg-muted/40 space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-3">Pay Summary</p>
                        <StatRow label="Days Present" value={String(entry.days_present)} />
                        <StatRow label="Basic Pay" value={fmt(entry.total_basic_pay)} />
                        <StatRow label="OT Pay" value={fmt(entry.overtime_pay)} />
                        <StatRow label="Holiday Pay" value={fmt(entry.holiday_pay)} />
                        <StatRow label="Late Deduction" value={`(${fmt(entry.late_deduction)})`} red />
                        <StatRow label="Undertime Deduction" value={`(${fmt(entry.undertime_deduction)})`} red />
                        <Separator className="my-1" />
                        <div className="flex justify-between items-center text-sm font-semibold">
                            <span>Gross Pay</span>
                            <span>{fmt(entry.gross_pay)}</span>
                        </div>
                    </div>
                )}

                <Separator />

                {/* Editable fields */}
                <form onSubmit={submit} className="flex flex-col flex-1 overflow-y-auto">
                    <div className="px-6 py-4 space-y-4 flex-1">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Adjustments</p>

                        <div className="space-y-1.5">
                            <Label htmlFor="cash_advance">Cash Advance (₱)</Label>
                            <Input
                                id="cash_advance"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.cash_advance}
                                onChange={e => setData('cash_advance', e.target.value)}
                            />
                            <InputError message={errors.cash_advance} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="other_deductions">Other Deductions (₱)</Label>
                            <Input
                                id="other_deductions"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.other_deductions}
                                onChange={e => setData('other_deductions', e.target.value)}
                            />
                            <InputError message={errors.other_deductions} />
                        </div>

                    </div>

                    <div className="px-6 py-4 border-t bg-background">
                        <Button type="submit" disabled={processing} className="w-full">
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
