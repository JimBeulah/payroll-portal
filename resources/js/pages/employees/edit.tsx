import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { index } from '@/routes/employees';

interface Employee {
    id: number; name: string; employee_number: string | null; gender: string | null;
    department: string; daily_rate: string; shift_start: string; shift_end: string; is_active: boolean;
}

export default function EmployeeEdit({ employee }: { employee: Employee }) {
    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        employee_number: employee.employee_number ?? '',
        gender: employee.gender ?? '',
        department: employee.department,
        daily_rate: employee.daily_rate,
        shift_start: employee.shift_start.slice(0, 5),
        shift_end: employee.shift_end.slice(0, 5),
        is_active: employee.is_active,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/employees/${employee.id}`);
    }

    return (
        <>
            <Head title="Edit Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Edit Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Employee Number <span className="text-muted-foreground font-normal">(optional)</span></Label>
                            <Input value={data.employee_number} onChange={e => setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                            <InputError message={errors.employee_number} />
                        </div>
                        <div>
                            <Label>Gender <span className="text-muted-foreground font-normal">(optional)</span></Label>
                            <Select value={data.gender || '_none'} onValueChange={v => setData('gender', v === '_none' ? '' : v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="_none">— None —</SelectItem>
                                    <SelectItem value="Male">Male</SelectItem>
                                    <SelectItem value="Female">Female</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
                    </div>
                    <div>
                        <Label>Department <span className="text-muted-foreground font-normal">(optional)</span></Label>
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
                    <div>
                        <Label>Status</Label>
                        <Select value={data.is_active ? 'active' : 'inactive'} onValueChange={v => setData('is_active', v === 'active')}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.is_active} />
                    </div>
                    <Button type="submit" disabled={processing}>Update Employee</Button>
                </form>
            </div>
        </>
    );
}

EmployeeEdit.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Edit Employee', href: index() },
    ],
};
