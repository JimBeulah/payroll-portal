import { Head, router, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import { index } from '@/routes/payroll-runs';
import { formatDate } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Upload, Calculator } from 'lucide-react';
import PayrollSummaryTable, { PayrollEntry } from '@/components/payroll/payroll-summary-table';
import DeductionSheet from '@/components/payroll/deduction-sheet';
import ShiftCalendarGrid from '@/components/payroll/shift-calendar-grid';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

interface AttendanceUpload {
    id: number; filename: string; uploaded_at: string;
}

interface Employee {
    id: number; name: string; department: string; shift_start: string; shift_end: string;
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

interface DayAttendance {
    sw: string;
    ew: string;
    late_minutes: number;
    undertime_minutes: number;
    overtime_minutes: number;
}

interface Props {
    run: PayrollRun;
    entries: PayrollEntry[];
    uploads: AttendanceUpload[];
    employees: Employee[];
    manualAttendances: ManualAttendance[];
    attendanceData: Record<number, Record<string, DayAttendance>>;
}

export default function PayrollShow({ run, entries, uploads, employees, manualAttendances, attendanceData }: Props) {
    const { props } = usePage<{
        errors: Record<string, string>;
    }>();

    const [selectedEntry, setSelectedEntry] = useState<PayrollEntry | null>(null);
    const [uploading, setUploading] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    function handleFileSelected(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        const form = new FormData();
        form.append('file', file);
        router.post(`/payroll-runs/${run.id}/upload`, form as any, {
            onFinish: () => {
                setUploading(false);
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    }

    function compute() {
        router.post(`/payroll-runs/${run.id}/compute`);
    }

    function lock() {
        if (confirm('Lock this payroll run? This cannot be undone.')) {
            router.post(`/payroll-runs/${run.id}/lock`);
        }
    }

    function unlock() {
        if (confirm('Unlock this payroll run? Entries will be editable again.')) {
            router.post(`/payroll-runs/${run.id}/unlock`);
        }
    }

    function downloadAllSlips() {
        window.open(`/payroll-runs/${run.id}/payslips/download-all`, '_blank');
    }

    function downloadSlip(entryId: number) {
        window.open(`/payroll-entries/${entryId}/payslip`, '_blank');
    }

    const isLocked = run.status === 'locked';
    const errors = props.errors ?? {};

    return (
        <>
            <Head title={`Payroll Run ${formatDate(run.period_start)} – ${formatDate(run.period_end)}`} />
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Payroll: {formatDate(run.period_start)} – {formatDate(run.period_end)}
                        </h1>
                        <p className="text-muted-foreground">Payable: {formatDate(run.payable_date)}</p>
                    </div>
                    <div className="flex gap-2 items-center">
                        <Badge variant={isLocked ? 'default' : 'secondary'}>{run.status}</Badge>
                        {!isLocked && entries.length > 0 && (
                            <Button onClick={lock} variant="destructive">Lock Run</Button>
                        )}
                        {isLocked && (
                            <>
                                <Button onClick={downloadAllSlips}>Download All Payslips</Button>
                                <Button
                                    variant="outline"
                                    onClick={() => window.open(`/payroll-runs/${run.id}/payslips/print`, '_blank', 'noopener,noreferrer')}
                                >
                                    Print All Payslips
                                </Button>
                                <Button variant="outline"
                                    onClick={() => window.open(`/payroll-runs/${run.id}/export`, '_blank')}>
                                    Export Excel
                                </Button>
                                <Button onClick={unlock} variant="destructive">Unlock Run</Button>
                            </>
                        )}
                    </div>
                </div>

                {errors.file && (
                    <div className="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {errors.file}
                    </div>
                )}

                {!isLocked && (
                    <div className="border rounded-lg divide-y">
                        {/* Step 1 — Upload */}
                        <div className="p-4 space-y-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Step 1</p>
                            <div className="flex items-center gap-3">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept=".xlsx,.xls"
                                    className="hidden"
                                    onChange={handleFileSelected}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={uploading}
                                    onClick={() => fileRef.current?.click()}
                                >
                                    <Upload className="w-4 h-4 mr-2" />
                                    {uploading ? 'Uploading…' : 'Upload Attendance File'}
                                </Button>
                                <span className="text-xs text-muted-foreground">Accepts .xlsx / .xls</span>
                            </div>

                            {uploads.length > 0 && (
                                <div className="text-sm space-y-1">
                                    {uploads.map(u => (
                                        <div key={u.id} className="flex items-center gap-2">
                                            <span className="text-green-600">✓</span>
                                            <span className="font-medium">{u.filename}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(u.uploaded_at).toLocaleString()}
                                            </span>
                                            <button
                                                type="button"
                                                className="ml-1 text-xs text-red-500 hover:text-red-700 hover:underline"
                                                onClick={() => {
                                                    if (confirm(`Remove "${u.filename}"?`)) {
                                                        router.delete(`/attendance-uploads/${u.id}`);
                                                    }
                                                }}
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Step 2 — Manual Attendance */}
                        <div className="p-4 space-y-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Step 2 — Manual Attendance <span className="font-normal normal-case">(optional)</span></p>
                            <p className="text-xs text-muted-foreground">
                                Add entries for employees with reassigned or additional shifts not captured in the attendance file (e.g. second shifts, night shifts assigned by admin).
                            </p>
                            <ShiftCalendarGrid
                                payrollRunId={run.id}
                                employees={employees}
                                periodStart={run.period_start}
                                periodEnd={run.period_end}
                                manualAttendances={manualAttendances}
                                attendanceData={attendanceData}
                            />
                        </div>

                        {/* Step 3 — Compute */}
                        <div className="p-4 space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Step 3</p>
                            <div className="flex items-center gap-3">
                                <Button
                                    onClick={compute}
                                    disabled={uploads.length === 0}
                                >
                                    <Calculator className="w-4 h-4 mr-2" />
                                    Compute Payroll
                                </Button>
                                {uploads.length === 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        Upload an attendance file first
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {entries.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">Payroll Summary</h2>
                        </div>
                        <PayrollSummaryTable
                            entries={entries}
                            isLocked={isLocked}
                            onEdit={setSelectedEntry}
                            onDownloadSlip={downloadSlip}
                        />
                    </div>
                )}
            </div>

            <DeductionSheet
                entry={selectedEntry}
                open={selectedEntry !== null}
                onClose={() => setSelectedEntry(null)}
            />

        </>
    );
}

PayrollShow.layout = {
    breadcrumbs: [
        { title: 'Payroll Runs', href: index() },
        { title: 'Payroll Run', href: index() },
    ],
};
