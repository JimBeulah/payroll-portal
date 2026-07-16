import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';

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
    const estimatedPay = selected && data.days_present && Number(data.days_present) > 0
        ? Number(selected.daily_rate) * Number(data.days_present)
        : null;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/payroll-runs/${payrollRunId}/entries`, {
            onSuccess: () => {
 reset(); onClose(); 
},
        });
    }

    function handleClose() {
        reset();
        onClose();
    }

    return (
        <Sheet open={open} onOpenChange={(o) => {
 if (!o) {
handleClose();
} 
}}>
            <SheetContent className="flex flex-col gap-0 p-0 sm:max-w-md">
                <SheetHeader className="px-6 pt-6 pb-4">
                    <SheetTitle className="text-lg">Add Employee</SheetTitle>
                    <SheetDescription>Add an employee to this payroll run manually.</SheetDescription>
                </SheetHeader>

                <Separator />

                <form onSubmit={submit} className="flex flex-col flex-1 overflow-y-auto">
                    <div className="px-6 py-4 space-y-4 flex-1">

                        <div className="space-y-1.5">
                            <Label htmlFor="employee_id">Employee</Label>
                            <select
                                id="employee_id"
                                value={data.employee_id}
                                onChange={e => setData('employee_id', e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">— Select employee —</option>
                                {employees.map(emp => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.name} · {emp.department}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employee_id} />
                        </div>

                        {selected && (
                            <div className="rounded-lg border bg-muted/40 px-4 py-3 space-y-1">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Selected Employee</p>
                                <p className="font-semibold">{selected.name}</p>
                                <div className="flex justify-between text-sm text-muted-foreground">
                                    <span>{selected.department}</span>
                                    <span>₱{Number(selected.daily_rate).toLocaleString('en-PH', { minimumFractionDigits: 2 })} / day</span>
                                </div>
                            </div>
                        )}

                        <div className="space-y-1.5">
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

                        {estimatedPay !== null && (
                            <>
                                <Separator />
                                <div className="rounded-lg border bg-muted/40 px-4 py-3">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2">Estimate</p>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-muted-foreground">Basic Pay</span>
                                        <span className="font-semibold">
                                            ₱{estimatedPay.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        OT, holidays, and deductions will be computed after attendance upload.
                                    </p>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="px-6 py-4 border-t bg-background">
                        <Button
                            type="submit"
                            disabled={processing || !data.employee_id || !data.days_present}
                            className="w-full"
                        >
                            {processing ? 'Adding…' : 'Add to Payroll'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
