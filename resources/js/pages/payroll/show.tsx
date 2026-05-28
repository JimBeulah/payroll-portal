import { Head, router, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import { index } from '@/routes/payroll-runs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Upload, Calculator } from 'lucide-react';
import PayrollSummaryTable, { PayrollEntry } from '@/components/payroll/payroll-summary-table';
import DeductionSheet from '@/components/payroll/deduction-sheet';
import AddEmployeeSheet, { AvailableEmployee } from '@/components/payroll/add-employee-sheet';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

interface AttendanceUpload {
    id: number; filename: string; uploaded_at: string;
}

interface Props {
    run: PayrollRun;
    entries: PayrollEntry[];
    uploads: AttendanceUpload[];
    availableEmployees: AvailableEmployee[];
}

export default function PayrollShow({ run, entries, uploads, availableEmployees }: Props) {
    const { props } = usePage<{
        errors: Record<string, string>;
    }>();

    const [selectedEntry, setSelectedEntry] = useState<PayrollEntry | null>(null);
    const [addEmployeeOpen, setAddEmployeeOpen] = useState(false);
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
            <Head title={`Payroll Run ${run.period_start} – ${run.period_end}`} />
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Payroll: {run.period_start} – {run.period_end}
                        </h1>
                        <p className="text-muted-foreground">Payable: {run.payable_date}</p>
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
                                    onClick={() => window.open(`/payroll-runs/${run.id}/payslips/print`, '_blank')}
                                >
                                    Print All Payslips
                                </Button>
                                <Button variant="outline"
                                    onClick={() => window.open(`/payroll-runs/${run.id}/export`, '_blank')}>
                                    Export Excel
                                </Button>
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

                        {/* Step 2 — Compute */}
                        <div className="p-4 space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Step 2</p>
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
                            {!isLocked && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setAddEmployeeOpen(true)}
                                    disabled={availableEmployees.length === 0}
                                >
                                    + Add Employee
                                </Button>
                            )}
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

            <AddEmployeeSheet
                open={addEmployeeOpen}
                onClose={() => setAddEmployeeOpen(false)}
                payrollRunId={run.id}
                employees={availableEmployees}
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
