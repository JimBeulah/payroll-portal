import { useForm } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { PayrollEntry } from './payroll-summary-table';

interface Props {
    entry: PayrollEntry | null;
    open: boolean;
    onClose: () => void;
}

export default function DeductionSheet({ entry, open, onClose }: Props) {
    const { data, setData, put, processing, errors, reset } = useForm({
        cash_advance: entry?.cash_advance ?? '0',
        other_deductions: entry?.other_deductions ?? '0',
        first_release: entry?.first_release ?? '0',
        second_release: entry?.second_release ?? '0',
    });

    if (entry && data.cash_advance !== entry.cash_advance) {
        setData({
            cash_advance: entry.cash_advance,
            other_deductions: entry.other_deductions,
            first_release: entry.first_release,
            second_release: entry.second_release,
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!entry) return;
        put(`/payroll-entries/${entry.id}`, {
            onSuccess: () => { reset(); onClose(); },
        });
    }

    return (
        <Sheet open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>{entry?.employee.name}</SheetTitle>
                </SheetHeader>
                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <Label>Cash Advance (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.cash_advance}
                            onChange={e => setData('cash_advance', e.target.value)} />
                        <InputError message={errors.cash_advance} />
                    </div>
                    <div>
                        <Label>Other Deductions (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.other_deductions}
                            onChange={e => setData('other_deductions', e.target.value)} />
                        <InputError message={errors.other_deductions} />
                    </div>
                    <div>
                        <Label>1st Release (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.first_release}
                            onChange={e => setData('first_release', e.target.value)} />
                        <InputError message={errors.first_release} />
                    </div>
                    <div>
                        <Label>2nd Release (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.second_release}
                            onChange={e => setData('second_release', e.target.value)} />
                        <InputError message={errors.second_release} />
                    </div>
                    <Button type="submit" disabled={processing} className="w-full">Save</Button>
                </form>
            </SheetContent>
        </Sheet>
    );
}
