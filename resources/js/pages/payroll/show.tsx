import { Head, router, useForm } from '@inertiajs/react';
import { useState, useRef } from 'react';
import { index } from '@/routes/payroll-runs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PayrollSummaryTable, { PayrollEntry } from '@/components/payroll/payroll-summary-table';
import DeductionSheet from '@/components/payroll/deduction-sheet';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

interface Props {
    run: PayrollRun;
    entries: PayrollEntry[];
}

export default function PayrollShow({ run, entries }: Props) {
    const [selectedEntry, setSelectedEntry] = useState<PayrollEntry | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const { data, setData, processing } = useForm({ file: null as File | null });

    function uploadFile(e: React.FormEvent) {
        e.preventDefault();
        if (!data.file) return;
        const form = new FormData();
        form.append('file', data.file);
        router.post(`/payroll-runs/${run.id}/upload`, form as any);
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
                                <Button variant="outline"
                                    onClick={() => window.open(`/payroll-runs/${run.id}/export`, '_blank')}>
                                    Export Excel
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {!isLocked && (
                    <div className="border rounded-lg p-4 space-y-3">
                        <h2 className="font-semibold">1. Upload Attendance File</h2>
                        <form onSubmit={uploadFile} className="flex gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".xlsx,.xls"
                                className="flex-1 text-sm"
                                onChange={e => setData('file', e.target.files?.[0] ?? null)}
                            />
                            <Button type="submit" disabled={processing || !data.file}>Upload</Button>
                        </form>
                        <Button onClick={compute} variant="outline">2. Compute Payroll</Button>
                    </div>
                )}

                {entries.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="font-semibold">Payroll Summary</h2>
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
