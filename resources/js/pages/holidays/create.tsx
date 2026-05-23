import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';

export default function HolidayCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', date: '', type: 'regular',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/holidays');
    }

    return (
        <AppLayout>
            <Head title="Add Holiday" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">Add Holiday</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Date</Label>
                        <Input type="date" value={data.date} onChange={e => setData('date', e.target.value)} />
                        <InputError message={errors.date} />
                    </div>
                    <div>
                        <Label>Type</Label>
                        <Select value={data.type} onValueChange={v => setData('type', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="regular">Regular Holiday (2× pay)</SelectItem>
                                <SelectItem value="special">Special Holiday (1.3× pay)</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>
                    <Button type="submit" disabled={processing}>Save Holiday</Button>
                </form>
            </div>
        </AppLayout>
    );
}
