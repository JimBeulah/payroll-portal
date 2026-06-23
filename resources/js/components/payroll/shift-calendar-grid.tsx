import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';

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
    payrollRunId: number;
    employees: Employee[];
    periodStart: string;
    periodEnd: string;
    manualAttendances: ManualAttendance[];
}

function toHHMM(value: string): string {
    return value.substring(0, 5);
}

function to12hr(hhmm: string): string {
    const [h, m] = hhmm.substring(0, 5).split(':').map(Number);
    const period = h >= 12 ? 'pm' : 'am';
    const hour = h % 12 === 0 ? 12 : h % 12;
    return `${hour}:${String(m).padStart(2, '0')}${period}`;
}

export default function ShiftCalendarGrid({
    payrollRunId,
    employees,
    periodStart,
    periodEnd,
    manualAttendances,
}: Props) {
    const [selectedEmployee, setSelectedEmployee] = useState<string>(employees[0]?.id.toString() ?? '');
    const [pickerDate, setPickerDate] = useState<string | null>(null);
    const [customShiftStart, setCustomShiftStart] = useState('');
    const [customShiftEnd, setCustomShiftEnd] = useState('');
    const [sw, setSw] = useState('');
    const [ew, setEw] = useState('');
    const [note, setNote] = useState('');
    const [isOverride, setIsOverride] = useState(true);
    const [processing, setProcessing] = useState(false);

    const start = new Date(periodStart);
    const end = new Date(periodEnd);

    // Get first and last day of calendar month
    const firstDay = new Date(start.getFullYear(), start.getMonth(), 1);
    const lastDay = new Date(end.getFullYear(), end.getMonth() + 1, 0);

    // Generate all dates to display (including padding from previous/next month)
    const dateArray: (Date | null)[] = [];
    const startingDayOfWeek = firstDay.getDay();
    for (let i = 0; i < startingDayOfWeek; i++) {
        dateArray.push(null);
    }
    for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
        dateArray.push(new Date(d));
    }

    const employee = employees.find(e => String(e.id) === selectedEmployee);
    const employeeAttendances = manualAttendances.filter(m => String(m.employee_id) === selectedEmployee);
    const attendanceByDate: Record<string, ManualAttendance> = {};
    employeeAttendances.forEach(a => {
        attendanceByDate[a.date] = a;
    });

    function formatDateString(d: Date): string {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function handleDateClick(date: Date | null) {
        if (!date || !employee) return;
        const dateStr = formatDateString(date);
        if (dateStr < periodStart || dateStr > periodEnd) return;
        setPickerDate(dateStr);
        const existing = attendanceByDate[dateStr];
        if (existing) {
            setCustomShiftStart(toHHMM(existing.shift_start));
            setCustomShiftEnd(toHHMM(existing.shift_end));
            setSw(existing.sw ? toHHMM(existing.sw) : '');
            setEw(existing.ew ? toHHMM(existing.ew) : '');
            setNote(existing.note ?? '');
            setIsOverride(existing.is_override);
        } else {
            setCustomShiftStart(toHHMM(employee.shift_start));
            setCustomShiftEnd(toHHMM(employee.shift_end));
            setSw('');
            setEw('');
            setNote('');
            setIsOverride(true);
        }
    }

    function addShift(shiftStart: string, shiftEnd: string) {
        if (!selectedEmployee || !pickerDate) return;
        const existing = attendanceByDate[pickerDate];

        const data = {
            employee_id: selectedEmployee,
            date: pickerDate,
            shift_start: shiftStart,
            shift_end: shiftEnd,
            sw: sw || '',
            ew: ew || '',
            note: note || '',
            is_override: isOverride,
        };

        setProcessing(true);
        if (existing) {
            router.put(`/payroll-manual-attendances/${existing.id}`, data, {
                onSuccess: () => setPickerDate(null),
                onFinish: () => setProcessing(false),
            });
        } else {
            router.post(`/payroll-runs/${payrollRunId}/manual-attendances`, data, {
                onSuccess: () => setPickerDate(null),
                onFinish: () => setProcessing(false),
            });
        }
    }

    function removeShift(date: string) {
        const existing = attendanceByDate[date];
        if (!existing || !confirm('Remove this shift entry?')) return;
        router.delete(`/payroll-manual-attendances/${existing.id}`, {
            onSuccess: () => setPickerDate(null),
        });
    }

    function isInPeriod(date: Date): boolean {
        const dateStr = formatDateString(date);
        return dateStr >= periodStart && dateStr <= periodEnd;
    }

    function isToday(date: Date): boolean {
        const now = new Date();
        return date.getFullYear() === now.getFullYear()
            && date.getMonth() === now.getMonth()
            && date.getDate() === now.getDate();
    }

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthName = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    return (
        <div className="space-y-4">
            {/* Employee selector */}
            <div className="max-w-xs">
                <label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground block mb-1">
                    Select Employee
                </label>
                <Select value={selectedEmployee} onValueChange={setSelectedEmployee}>
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
            </div>

            {/* Calendar */}
            <div className="border rounded-lg p-4">
                <h3 className="font-semibold mb-4 text-center">{monthName}</h3>

                {/* Day headers */}
                <div className="grid grid-cols-7 gap-2 mb-2">
                    {dayNames.map(day => (
                        <div key={day} className="text-xs font-semibold text-center text-muted-foreground py-2">
                            {day}
                        </div>
                    ))}
                </div>

                {/* Calendar grid */}
                <div className="grid grid-cols-7 gap-2">
                    {dateArray.map((date, idx) => {
                        const dateStr = date ? formatDateString(date) : null;
                        const attendance = dateStr ? attendanceByDate[dateStr] : null;
                        const inPeriod = date ? isInPeriod(date) : false;
                        const today = date ? isToday(date) : false;

                        return (
                            <div
                                key={idx}
                                onClick={() => handleDateClick(date)}
                                className={`
                                    aspect-square p-2 rounded border text-xs relative cursor-pointer
                                    transition-colors
                                    ${!date || !inPeriod
                                        ? 'bg-muted/30 border-transparent cursor-default opacity-40'
                                        : attendance
                                            ? 'bg-primary/10 border-primary/40 hover:bg-primary/20'
                                            : 'bg-background border-border hover:bg-muted'
                                    }
                                    ${today ? 'ring-1 ring-amber-500' : ''}
                                `}
                            >
                                <div className="font-medium mb-1">
                                    {date && date.getDate()}
                                </div>
                                {attendance && (
                                    <div className="text-[10px] font-medium truncate space-y-0.5">
                                        <div className="flex items-center gap-0.5">
                                            <span className="text-[8px] uppercase tracking-wide text-muted-foreground font-semibold">S</span>
                                            <span className="text-primary">{to12hr(attendance.shift_start)}–{to12hr(attendance.shift_end)}</span>
                                        </div>
                                        {(attendance.sw || attendance.ew) && (
                                            <div className="flex items-center gap-0.5">
                                                <span className="text-[8px] uppercase tracking-wide text-muted-foreground font-semibold">T</span>
                                                <span className="text-muted-foreground">
                                                    {attendance.sw ? to12hr(attendance.sw) : '?'}–{attendance.ew ? to12hr(attendance.ew) : '?'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Quick shift picker modal */}
            {pickerDate && employee && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-background rounded-lg shadow-lg w-full max-w-2xl">
                        {/* Header */}
                        <div className="flex justify-between items-center px-5 py-3 border-b">
                            <h4 className="font-semibold text-sm">
                                {employee.name} — {new Date(`${pickerDate}T00:00:00`).toLocaleDateString('en-US', {
                                    weekday: 'short', month: 'short', day: 'numeric',
                                })}
                            </h4>
                            <button onClick={() => setPickerDate(null)} className="text-muted-foreground hover:text-foreground">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Split body */}
                        <div className="flex divide-x">
                            {/* Left — presets */}
                            <div className="flex-1 p-4 space-y-3">
                                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Shift</p>
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">Day shift</p>
                                    <div className="grid grid-cols-3 gap-1.5">
                                        {[['08:00','17:00','8am–5pm'],['09:00','18:00','9am–6pm'],['10:00','19:00','10am–7pm']].map(([s,e,label]) => (
                                            <Button
                                                key={label} size="sm"
                                                variant={customShiftStart === s && customShiftEnd === e ? 'default' : 'outline'}
                                                onClick={() => { setCustomShiftStart(s); setCustomShiftEnd(e); setSw(s); setEw(e); }}
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">Night shift</p>
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {[['15:00','22:00','3pm–10pm'],['23:00','07:00','11pm–7am']].map(([s,e,label]) => (
                                            <Button
                                                key={label} size="sm"
                                                variant={customShiftStart === s && customShiftEnd === e ? 'default' : 'outline'}
                                                onClick={() => { setCustomShiftStart(s); setCustomShiftEnd(e); setSw(s); setEw(e); }}
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                                <div className="space-y-1 pt-1">
                                    <p className="text-xs text-muted-foreground">Custom</p>
                                    <div className="grid grid-cols-2 gap-1.5">
                                        <Input type="time" value={customShiftStart} onChange={e => { setCustomShiftStart(e.target.value); setSw(e.target.value); }} />
                                        <Input type="time" value={customShiftEnd} onChange={e => { setCustomShiftEnd(e.target.value); setEw(e.target.value); }} />
                                    </div>
                                </div>
                            </div>

                            {/* Right — details + save */}
                            <div className="flex-1 p-4 space-y-3 flex flex-col">
                                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Details</p>
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">Time in / out</p>
                                    <div className="grid grid-cols-2 gap-1.5">
                                        <div className="space-y-0.5">
                                            <label className="text-xs font-medium">SW (In)</label>
                                            <Input type="time" value={sw} onChange={e => setSw(e.target.value)} required />
                                        </div>
                                        <div className="space-y-0.5">
                                            <label className="text-xs font-medium">EW (Out)</label>
                                            <Input type="time" value={ew} onChange={e => setEw(e.target.value)} required />
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-0.5">
                                    <label className="text-xs font-medium">Note <span className="text-muted-foreground font-normal">(optional)</span></label>
                                    <Input
                                        type="text"
                                        placeholder="e.g. Called back for deadline"
                                        value={note}
                                        onChange={e => setNote(e.target.value)}
                                    />
                                </div>
                                <label className="flex items-start gap-2 rounded-md border p-2.5 cursor-pointer hover:bg-muted/50">
                                    <input type="checkbox" className="mt-0.5" checked={isOverride} onChange={e => setIsOverride(e.target.checked)} />
                                    <div>
                                        <p className="text-xs font-medium">Replace attendance file entry</p>
                                        <p className="text-xs text-muted-foreground">Reassigned shift — drops Excel entry. Uncheck for a second / callback shift.</p>
                                    </div>
                                </label>
                                <div className="mt-auto space-y-1.5 pt-2">
                                    <Button
                                        size="sm"
                                        className="w-full"
                                        onClick={() => addShift(customShiftStart, customShiftEnd)}
                                        disabled={processing || !customShiftStart || !customShiftEnd || !sw || !ew}
                                    >
                                        Save Entry
                                    </Button>
                                    {attendanceByDate[pickerDate] && (
                                        <Button size="sm" variant="destructive" className="w-full" onClick={() => removeShift(pickerDate)} disabled={processing}>
                                            Remove Entry
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
