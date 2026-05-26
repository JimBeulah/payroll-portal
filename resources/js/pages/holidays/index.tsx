import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/holidays';

interface Holiday {
    id: number; name: string; date: string; type: 'regular' | 'special';
}

export default function HolidaysIndex({ holidays }: { holidays: Holiday[] }) {
    function destroy(id: number) {
        if (confirm('Delete this holiday?')) router.delete(`/holidays/${id}`);
    }

    return (
        <>
            <Head title="Holidays" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Holidays</h1>
                    <Button asChild><Link href="/holidays/create">Add Holiday</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {holidays.map((h) => (
                            <TableRow key={h.id}>
                                <TableCell>{h.name}</TableCell>
                                <TableCell>{h.date}</TableCell>
                                <TableCell>
                                    <Badge variant={h.type === 'regular' ? 'default' : 'secondary'}>
                                        {h.type === 'regular' ? 'Regular (2×)' : 'Special (1.3×)'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/holidays/${h.id}/edit`}>Edit</Link>
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => destroy(h.id)}>
                                        Delete
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

HolidaysIndex.layout = {
    breadcrumbs: [
        { title: 'Holidays', href: index() },
    ],
};
