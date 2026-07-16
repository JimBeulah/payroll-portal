import { Head, router } from '@inertiajs/react';
import EmployeeAttendanceCalendar from '@/components/attendance/employee-attendance-calendar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface EmployeeInfo {
    id: number;
    name: string;
    employee_number: string | null;
    department: string | null;
}

interface PayrollRunOption {
    id: number;
    period_start: string;
    period_end: string;
}

interface ManualAttendance {
    id: number;
    date: string;
    sw: string | null;
    ew: string | null;
    shift_start: string;
    shift_end: string;
    is_override: boolean;
}

interface DayAttendance {
    sw: string;
    ew: string;
    late_minutes: number;
    undertime_minutes: number;
    overtime_minutes: number;
}

interface LeaveDay {
    reason: string | null;
}

interface Holiday {
    date: string;
    name: string;
    type: 'regular' | 'special';
}

function formatPeriodLabel(run: PayrollRunOption) {
    const start = new Date(`${run.period_start}T00:00:00`);
    const end = new Date(`${run.period_end}T00:00:00`);
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    return `${start.toLocaleDateString(undefined, opts)} – ${end.toLocaleDateString(undefined, opts)}`;
}

export default function AttendanceIndex({
    employee,
    payrollRuns,
    selectedRun,
    manualAttendances,
    attendanceData,
    leaveData,
    holidays,
}: {
    employee: EmployeeInfo;
    payrollRuns: PayrollRunOption[];
    selectedRun: PayrollRunOption;
    manualAttendances: ManualAttendance[];
    attendanceData: Record<string, DayAttendance>;
    leaveData: Record<string, LeaveDay>;
    holidays: Holiday[];
}) {
    function handleRunChange(value: string) {
        router.get('/my-attendance', { run: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="My Attendance" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">My Attendance</h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.name}
                        {employee.employee_number ? ` · ${employee.employee_number}` : ''}
                        {employee.department ? ` · ${employee.department}` : ''}
                    </p>
                </div>

                <div className="max-w-xs">
                    <label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground block mb-1">
                        Payroll Period
                    </label>
                    <Select value={String(selectedRun.id)} onValueChange={handleRunChange}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {payrollRuns.map((run) => (
                                <SelectItem key={run.id} value={String(run.id)}>
                                    {formatPeriodLabel(run)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <EmployeeAttendanceCalendar
                    periodStart={selectedRun.period_start}
                    periodEnd={selectedRun.period_end}
                    manualAttendances={manualAttendances}
                    attendanceData={attendanceData}
                    leaveData={leaveData}
                    holidays={holidays}
                />
            </div>
        </>
    );
}
