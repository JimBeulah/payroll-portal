import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Employee {
    id: number;
    name: string;
    department: string;
    shift_start: string;
    shift_end: string;
}

interface ManualAttendance {
    id: number;
    employee_id: number;
    date: string;
    sw: string | null;
    ew: string | null;
    shift_start: string;
    shift_end: string;
    note: string | null;
    is_override: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    payrollRunId: number;
    employees: Employee[];
    periodStart: string;
    periodEnd: string;
    editing?: ManualAttendance | null;
}

function toHHMM(value: string): string {
    return value.substring(0, 5);
}

export default function ManualAttendanceModal({ open, onClose, payrollRunId, employees, periodStart, periodEnd, editing }: Props) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        employee_id: '',
        date: '',
        sw: '',
        ew: '',
        shift_start: '',
        shift_end: '',
        note: '',
        is_override: false,
    });

    useEffect(() => {
        if (editing) {
            setData({
                employee_id: String(editing.employee_id),
                date: editing.date,
                sw: editing.sw ?? '',
                ew: editing.ew ?? '',
                shift_start: toHHMM(editing.shift_start),
                shift_end: toHHMM(editing.shift_end),
                note: editing.note ?? '',
                is_override: editing.is_override,
            });
        } else {
            reset();
        }
    }, [editing, open]);

    function handleEmployeeChange(id: string) {
        const emp = employees.find(e => String(e.id) === id);
        setData({
            ...data,
            employee_id: id,
            shift_start: emp ? toHHMM(emp.shift_start) : '',
            shift_end: emp ? toHHMM(emp.shift_end) : '',
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (editing) {
            put(`/payroll-manual-attendances/${editing.id}`, {
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        } else {
            post(`/payroll-runs/${payrollRunId}/manual-attendances`, {
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        }
    }

    function handleClose() {
        reset();
        onClose();
    }

    return (
        <Dialog open={open} onOpenChange={v => !v && handleClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{editing ? 'Edit Manual Attendance' : 'Add Manual Attendance'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    {/* Employee */}
                    <div className="space-y-1">
                        <Label>Employee</Label>
                        <Select value={data.employee_id} onValueChange={handleEmployeeChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select employee…" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map(e => (
                                    <SelectItem key={e.id} value={String(e.id)}>
                                        {e.name} — {e.department}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.employee_id && <p className="text-xs text-red-500">{errors.employee_id}</p>}
                    </div>

                    {/* Date */}
                    <div className="space-y-1">
                        <Label>Date</Label>
                        <Input
                            type="date"
                            min={periodStart}
                            max={periodEnd}
                            value={data.date}
                            onChange={e => setData('date', e.target.value)}
                        />
                        {errors.date && <p className="text-xs text-red-500">{errors.date}</p>}
                    </div>

                    {/* Shift for this day */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label>Shift Start <span className="text-xs text-muted-foreground">(this day)</span></Label>
                            <Input
                                type="time"
                                value={data.shift_start}
                                onChange={e => setData('shift_start', e.target.value)}
                            />
                            {errors.shift_start && <p className="text-xs text-red-500">{errors.shift_start}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Shift End <span className="text-xs text-muted-foreground">(this day)</span></Label>
                            <Input
                                type="time"
                                value={data.shift_end}
                                onChange={e => setData('shift_end', e.target.value)}
                            />
                            {errors.shift_end && <p className="text-xs text-red-500">{errors.shift_end}</p>}
                        </div>
                    </div>

                    {/* Actual punch times */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label>Time In (SW)</Label>
                            <Input
                                type="time"
                                value={data.sw}
                                onChange={e => setData('sw', e.target.value)}
                            />
                            {errors.sw && <p className="text-xs text-red-500">{errors.sw}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Time Out (EW)</Label>
                            <Input
                                type="time"
                                value={data.ew}
                                onChange={e => setData('ew', e.target.value)}
                            />
                            {errors.ew && <p className="text-xs text-red-500">{errors.ew}</p>}
                        </div>
                    </div>

                    {/* Note */}
                    <div className="space-y-1">
                        <Label>Note <span className="text-xs text-muted-foreground">(optional)</span></Label>
                        <Input
                            placeholder="e.g. Called back for deadline"
                            value={data.note}
                            onChange={e => setData('note', e.target.value)}
                        />
                        {errors.note && <p className="text-xs text-red-500">{errors.note}</p>}
                    </div>

                    {/* Override toggle */}
                    <label className="flex items-start gap-3 rounded-md border p-3 cursor-pointer hover:bg-muted/50">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={data.is_override}
                            onChange={e => setData('is_override', e.target.checked)}
                        />
                        <div>
                            <p className="text-sm font-medium">Replace attendance file entry for this date</p>
                            <p className="text-xs text-muted-foreground">
                                Check this only when the employee was <strong>reassigned to a different shift</strong> — it drops the Excel entry for this date and uses this entry instead.
                                Leave unchecked for a <strong>second / callback shift</strong> on top of their regular day.
                            </p>
                        </div>
                    </label>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={handleClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>
                            {editing ? 'Save Changes' : 'Add Entry'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
