import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { index } from '@/routes/holidays';

interface Holiday { id: number; name: string; date: string; type: string; }

export default function HolidayEdit({ holiday }: { holiday: Holiday }) {
    const { data, setData, put, processing, errors } = useForm({
        name: holiday.name, date: holiday.date, type: holiday.type,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/holidays/${holiday.id}`);
    }

    return (
        <>
            <Head title="Edit Holiday" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">Edit Holiday</h1>
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
                    <Button type="submit" disabled={processing}>Update Holiday</Button>
                </form>
            </div>
        </>
    );
}

HolidayEdit.layout = {
    breadcrumbs: [
        { title: 'Holidays', href: index() },
        { title: 'Edit Holiday', href: index() },
    ],
};
