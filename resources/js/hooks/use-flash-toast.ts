import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type PageFlash = {
    success?: string;
    error?: string;
    unmatched?: string[];
};

export function useFlashToast(): void {
    useEffect(() => {
        const offNavigate = router.on('navigate', (event) => {
            const flash = (event as any).detail?.page?.props?.flash as PageFlash | undefined;

            if (flash?.success) {
toast.success(flash.success);
}

            if (flash?.error) {
toast.error(flash.error);
}

            if (flash?.unmatched?.length) {
                toast.warning(`${flash.unmatched.length} employee(s) could not be matched`, {
                    description: flash.unmatched.join(', '),
                });
            }
        });

        const offFlash = router.on('flash', (event) => {
            const flashData = (event as CustomEvent).detail?.flash;
            const data = flashData?.toast as FlashToast | undefined;

            if (!data) {
return;
}

            toast[data.type](data.message);
        });

        return () => {
            offNavigate();
            offFlash();
        };
    }, []);
}
