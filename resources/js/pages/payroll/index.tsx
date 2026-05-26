import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/payroll-runs';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

export default function PayrollIndex({ runs }: { runs: PayrollRun[] }) {
    return (
        <>
            <Head title="Payroll Runs" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Payroll Runs</h1>
                    <Button asChild><Link href="/payroll-runs/create">New Payroll Run</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Period</TableHead>
                            <TableHead>Payable Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {runs.map((run) => (
                            <TableRow key={run.id}>
                                <TableCell>{run.period_start} – {run.period_end}</TableCell>
                                <TableCell>{run.payable_date}</TableCell>
                                <TableCell>
                                    <Badge variant={run.status === 'locked' ? 'default' : 'secondary'}>
                                        {run.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/payroll-runs/${run.id}`}>View</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

PayrollIndex.layout = {
    breadcrumbs: [
        { title: 'Payroll Runs', href: index() },
    ],
};
