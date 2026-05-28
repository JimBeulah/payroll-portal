import { useSidebar } from '@/components/ui/sidebar';

export default function AppLogo() {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';

    return (
        <>
            <div className={`flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden ${collapsed ? 'mx-auto' : ''}`}>
                <img src="/payroll-logo.png?v=2" alt="Payroll Portal Logo" className="size-8 object-contain" />
            </div>
            {!collapsed && (
                <div className="ml-1 grid flex-1 text-left text-sm">
                    <span className="mb-0.5 truncate leading-tight font-semibold">
                        Payroll Portal
                    </span>
                </div>
            )}
        </>
    );
}
