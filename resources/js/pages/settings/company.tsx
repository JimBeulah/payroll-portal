import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/company';

type Props = {
    company_name: string;
    logo_url: string | null;
};

export default function Company({ company_name, logo_url }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(logo_url);

    useEffect(() => {
        setPreview(logo_url);
    }, [logo_url]);

    const { data, setData, post, processing, errors } = useForm<{
        company_name: string;
        logo: File | null;
    }>({
        company_name,
        logo: null,
    });

    function handleLogoChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setData('logo', file);
        if (file) {
            setPreview(URL.createObjectURL(file));
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/company', {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Company settings" />

            <h1 className="sr-only">Company settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Company"
                    description="Update the company name and logo shown on payslips"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="company_name">Company Name</Label>
                        <Input
                            id="company_name"
                            name="company_name"
                            value={data.company_name}
                            onChange={e => setData('company_name', e.target.value)}
                            placeholder="Company name"
                        />
                        <InputError message={errors.company_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="logo">Payslip Logo</Label>

                        {preview && (
                            <div className="mb-2">
                                <img
                                    src={preview}
                                    alt="Current logo"
                                    className="h-16 w-auto rounded border object-contain"
                                />
                            </div>
                        )}

                        <Input
                            id="logo"
                            ref={fileInputRef}
                            type="file"
                            accept="image/png,image/jpeg,image/gif,image/svg+xml"
                            onChange={handleLogoChange}
                            className="cursor-pointer"
                        />
                        <p className="text-sm text-muted-foreground">
                            PNG, JPG, GIF or SVG. Max 2 MB.
                        </p>
                        <InputError message={errors.logo} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>Save</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Company.layout = {
    breadcrumbs: [
        {
            title: 'Company settings',
            href: edit(),
        },
    ],
};
