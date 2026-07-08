import { Link, usePage } from '@inertiajs/react';
import { Banknote, CalendarDays, ClipboardList, FileText, LayoutGrid, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

// Nav items for admin/HR (full payroll management).
const managerNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Employees',
        href: '/employees',
        icon: Users,
    },
    {
        title: 'Holidays',
        href: '/holidays',
        icon: CalendarDays,
    },
    {
        title: 'Payroll Runs',
        href: '/payroll-runs',
        icon: Banknote,
    },
    {
        title: 'Approvals',
        href: '/approvals',
        icon: ClipboardList,
    },
];

// Nav items for employees (self-service request portal only).
const employeeNavItems: NavItem[] = [
    {
        title: 'My Requests',
        href: '/my-requests',
        icon: FileText,
    },
];

export function AppSidebar() {
    const { auth, pendingRequests } = usePage().props;
    const role = auth.user?.role;
    const canView = role === 'admin' || role === 'hr' || role === 'overseer';

    let mainNavItems = canView ? managerNavItems : employeeNavItems;

    // Add badge to Approvals item if there are pending requests
    if (canView && pendingRequests) {
        const totalPending = (pendingRequests.cashAdvance || 0) + (pendingRequests.leave || 0);
        mainNavItems = mainNavItems.map((item) =>
            item.title === 'Approvals'
                ? { ...item, badge: totalPending > 0 ? totalPending : null }
                : item,
        );
    }

    const homeHref = canView ? dashboard() : '/my-requests';

    return (
        <Sidebar collapsible="icon" variant="floating">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
