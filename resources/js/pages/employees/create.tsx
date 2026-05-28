import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { index, create } from '@/routes/employees';

export default function EmployeeCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        employee_number: '',
        gender: '',
        department: '',
        daily_rate: '',
        shift_start: '08:00',
        shift_end: '17:00',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/employees');
    }

    return (
        <>
            <Head title="Add Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Add Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Employee Number</Label>
                            <Input value={data.employee_number} onChange={e => setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                            <InputError message={errors.employee_number} />
                        </div>
                        <div>
                            <Label>Gender</Label>
                            <Select value={data.gender} onValueChange={v => setData('gender', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Male">Male</SelectItem>
                                    <SelectItem value="Female">Female</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
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
                    <Button type="submit" disabled={processing}>Save Employee</Button>
                </form>
            </div>
        </>
    );
}

EmployeeCreate.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Add Employee', href: create() },
    ],
};
