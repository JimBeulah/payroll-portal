import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Employee {
    id: number;
    name: string;
    department: string;
    daily_rate: string;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
}

export default function EmployeesIndex({ employees }: { employees: Employee[] }) {
    function destroy(id: number) {
        if (confirm('Delete this employee?')) {
            router.delete(`/employees/${id}`);
        }
    }

    return (
        <AppLayout>
            <Head title="Employees" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Employees</h1>
                    <Button asChild><Link href="/employees/create">Add Employee</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Daily Rate</TableHead>
                            <TableHead>Shift</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {employees.map((emp) => (
                            <TableRow key={emp.id}>
                                <TableCell>{emp.name}</TableCell>
                                <TableCell>{emp.department}</TableCell>
                                <TableCell>₱{Number(emp.daily_rate).toLocaleString()}</TableCell>
                                <TableCell>{emp.shift_start} – {emp.shift_end}</TableCell>
                                <TableCell>{emp.is_active ? 'Active' : 'Inactive'}</TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/employees/${emp.id}/edit`}>Edit</Link>
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => destroy(emp.id)}>
                                        Delete
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
