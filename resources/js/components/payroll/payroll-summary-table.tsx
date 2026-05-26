import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';

export interface PayrollEntry {
    id: number;
    employee: { name: string; department: string };
    days_present: number;
    total_basic_pay: string;
    overtime_pay: string;
    late_deduction: string;
    undertime_deduction: string;
    holiday_pay: string;
    gross_pay: string;
    cash_advance: string;
    other_deductions: string;
    total_deductions: string;
    net_pay: string;
    first_release: string;
    second_release: string;
}

function fmt(v: string) {
    return `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

interface Props {
    entries: PayrollEntry[];
    isLocked: boolean;
    onEdit?: (entry: PayrollEntry) => void;
    onDownloadSlip?: (entryId: number) => void;
}

export default function PayrollSummaryTable({ entries, isLocked, onEdit, onDownloadSlip }: Props) {
    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Dept</TableHead>
                        <TableHead className="text-right">Days</TableHead>
                        <TableHead className="text-right">Basic Pay</TableHead>
                        <TableHead className="text-right">OT Pay</TableHead>
                        <TableHead className="text-right">Holiday</TableHead>
                        <TableHead className="text-right">Late</TableHead>
                        <TableHead className="text-right">Undertime</TableHead>
                        <TableHead className="text-right">Gross Pay</TableHead>
                        <TableHead className="text-right">Deductions</TableHead>
                        <TableHead className="text-right">Net Pay</TableHead>
                        <TableHead className="text-right">1st Release</TableHead>
                        <TableHead className="text-right">2nd Release</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {entries.map((e) => (
                        <TableRow key={e.id}>
                            <TableCell className="font-medium">{e.employee.name}</TableCell>
                            <TableCell>{e.employee.department}</TableCell>
                            <TableCell className="text-right">{e.days_present}</TableCell>
                            <TableCell className="text-right">{fmt(e.total_basic_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.overtime_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.holiday_pay)}</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.late_deduction)})</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.undertime_deduction)})</TableCell>
                            <TableCell className="text-right font-semibold">{fmt(e.gross_pay)}</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.total_deductions)})</TableCell>
                            <TableCell className="text-right font-bold">{fmt(e.net_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.first_release)}</TableCell>
                            <TableCell className="text-right">{fmt(e.second_release)}</TableCell>
                            <TableCell className="space-x-1">
                                {!isLocked && onEdit && (
                                    <Button variant="outline" size="sm" onClick={() => onEdit(e)}>
                                        Edit
                                    </Button>
                                )}
                                {isLocked && onDownloadSlip && (
                                    <Button variant="outline" size="sm" onClick={() => onDownloadSlip(e.id)}>
                                        Payslip
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
