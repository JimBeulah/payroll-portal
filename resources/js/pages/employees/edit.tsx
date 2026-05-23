import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

interface Employee {
    id: number; name: string; department: string;
    daily_rate: string; shift_start: string; shift_end: string;
}

export default function EmployeeEdit({ employee }: { employee: Employee }) {
    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        department: employee.department,
        daily_rate: employee.daily_rate,
        shift_start: employee.shift_start.slice(0, 5),
        shift_end: employee.shift_end.slice(0, 5),
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/employees/${employee.id}`);
    }

    return (
        <AppLayout>
            <Head title="Edit Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Edit Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Department</Label>
                        <Input value={data.department} onChange={e => setData('department', e.target.value)} />
                        <InputError message={errors.department} />
                    </div>
                    <div>
                        <Label>Daily Rate (₱)</Label>
                        <Input type="number" step="0.01" value={data.daily_rate} onChange={e => setData('daily_rate', e.target.value)} />
                        <InputError message={errors.daily_rate} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Shift Start</Label>
                            <Input type="time" value={data.shift_start} onChange={e => setData('shift_start', e.target.value)} />
                            <InputError message={errors.shift_start} />
                        </div>
                        <div>
                            <Label>Shift End</Label>
                            <Input type="time" value={data.shift_end} onChange={e => setData('shift_end', e.target.value)} />
                            <InputError message={errors.shift_end} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>Update Employee</Button>
                </form>
            </div>
        </AppLayout>
    );
}
