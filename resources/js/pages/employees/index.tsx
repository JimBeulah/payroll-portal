import { Head } from '@inertiajs/react';

interface Employee {
    id: number;
    name: string;
    department: string;
    daily_rate: number;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
}

interface Props {
    employees: Employee[];
}

export default function EmployeesIndex({ employees }: Props) {
    return (
        <>
            <Head title="Employees" />
            <div>
                <h1>Employees</h1>
            </div>
        </>
    );
}
