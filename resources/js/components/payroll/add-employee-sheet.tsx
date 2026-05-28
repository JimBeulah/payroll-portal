import { useForm } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export interface AvailableEmployee {
    id: number;
    name: string;
    department: string;
    daily_rate: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    payrollRunId: number;
    employees: AvailableEmployee[];
}

export default function AddEmployeeSheet({ open, onClose, payrollRunId, employees }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '',
        days_present: '',
    });

    const selected = employees.find(e => String(e.id) === data.employee_id);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/payroll-runs/${payrollRunId}/entries`, {
            onSuccess: () => { reset(); onClose(); },
        });
    }

    function handleClose() {
        reset();
        onClose();
    }

    return (
        <Sheet open={open} onOpenChange={(o) => { if (!o) handleClose(); }}>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>Add Employee to Payroll</SheetTitle>
                </SheetHeader>
                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <Label htmlFor="employee_id">Employee</Label>
                        <select
                            id="employee_id"
                            value={data.employee_id}
                            onChange={e => setData('employee_id', e.target.value)}
                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="">— Select employee —</option>
                            {employees.map(emp => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.name} ({emp.department})
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.employee_id} />
                    </div>

                    {selected && (
                        <p className="text-sm text-muted-foreground">
                            Daily rate: ₱{Number(selected.daily_rate).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                        </p>
                    )}

                    <div>
                        <Label htmlFor="days_present">Days Present</Label>
                        <Input
                            id="days_present"
                            type="number"
                            min="1"
                            value={data.days_present}
                            onChange={e => setData('days_present', e.target.value)}
                            placeholder="e.g. 13"
                        />
                        <InputError message={errors.days_present} />
                    </div>

                    {selected && data.days_present && Number(data.days_present) > 0 && (
                        <div className="rounded-md bg-muted px-3 py-2 text-sm">
                            Estimated basic pay:{' '}
                            <span className="font-semibold">
                                ₱{(Number(selected.daily_rate) * Number(data.days_present)).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </span>
                        </div>
                    )}

                    <Button type="submit" disabled={processing || !data.employee_id || !data.days_present} className="w-full">
                        Add to Payroll
                    </Button>
                </form>
            </SheetContent>
        </Sheet>
    );
}
