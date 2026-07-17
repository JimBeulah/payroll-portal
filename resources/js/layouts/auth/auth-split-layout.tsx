import { Link, usePage } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col bg-zinc-950 p-10 text-white lg:flex dark:border-r border-zinc-800 select-none overflow-hidden">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(30,41,59,0.4),rgba(9,9,11,1))]" />
                <div className="relative z-20 flex flex-1 flex-col items-center justify-center gap-6">
                    <div className="relative group">
                        <div className="absolute -inset-4 rounded-full bg-white/5 opacity-0 blur-xl transition duration-500 group-hover:opacity-100 group-hover:scale-110" />
                        <img
                            src="/payro-logo.png?v=1"
                            alt="PAYRO Logo"
                            className="relative h-20 w-auto object-contain transition-transform duration-500 group-hover:scale-105"
                        />
                    </div>
                    <div className="flex flex-col items-center gap-1.5 text-center">
                        <span className="text-sm font-semibold uppercase tracking-[0.3em] text-zinc-200">
                            {name}
                        </span>
                        <span className="text-xs text-zinc-500 tracking-wider">
                            Enterprise Payroll Management
                        </span>
                    </div>
                </div>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center gap-8 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <img
                            src="/payro-logo.png?v=1"
                            alt="PAYRO Logo"
                            className="h-20 w-auto object-contain"
                        />
                    </Link>
                    <div className="flex flex-col items-center gap-2 text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
