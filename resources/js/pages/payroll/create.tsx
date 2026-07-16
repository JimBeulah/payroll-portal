import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, create } from '@/routes/payroll-runs';

export default function PayrollCreate() {
    const { data, setData, post, processing, errors } = useForm({
        period_start: '', period_end: '', payable_date: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/payroll-runs');
    }

    return (
        <>
            <Head title="New Payroll Run" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">New Payroll Run</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Period Start</Label>
                        <Input type="date" value={data.period_start} onChange={e => setData('period_start', e.target.value)} />
                        <InputError message={errors.period_start} />
                    </div>
                    <div>
                        <Label>Period End</Label>
                        <Input type="date" value={data.period_end} onChange={e => setData('period_end', e.target.value)} />
                        <InputError message={errors.period_end} />
                    </div>
                    <div>
                        <Label>Payable Date</Label>
                        <Input type="date" value={data.payable_date} onChange={e => setData('payable_date', e.target.value)} />
                        <InputError message={errors.payable_date} />
                    </div>
                    <Button type="submit" disabled={processing}>Create Run</Button>
                </form>
            </div>
        </>
    );
}

PayrollCreate.layout = {
    breadcrumbs: [
        { title: 'Payroll Runs', href: index() },
        { title: 'New Payroll Run', href: create() },
    ],
};
