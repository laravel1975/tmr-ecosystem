import React from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

// (1. Import ทุกอย่างที่เมนูนี้ต้องการ)
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/Components/ui/navigation-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { ChevronDown, FileText } from 'lucide-react';
import NavLink from '@/Components/NavLink';

/*
|--------------------------------------------------------------------------
| (2. นี่คือ Component เมนูของ Maintenance BC)
|--------------------------------------------------------------------------
| (3. เพิ่ม export default)
*/
export default function SalesNavigationMenu() {
    return (
        <NavigationMenu>
            <NavigationMenuList>
                {/* (เมนู 1: Dashboard ของ Sales) */}
                <NavigationMenuItem>
                    <Link
                        href={route('sales.dashboard')}
                        className={cn(
                            navigationMenuTriggerStyle(),
                            route().current('sales.dashboard') ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        Dashboard
                    </Link>
                </NavigationMenuItem>

                {/* (เมนู 3: Orders Reference (Dropdown)) */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className={cn(
                            "h-10 px-4 py-2 text-sm font-medium",
                            (route().current('sales.index'))
                                ? 'bg-accent text-accent-foreground' : ''
                        )}>
                            Orders <ChevronDown className="relative top-[1px] ml-1 h-3 w-3" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem asChild>
                            <Link href="#">Quotations</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={route('sales.index')}>Sale Orders</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <NavLink
                    href={route('sales.approvals.index')}
                    active={route().current('sales.approvals.index')}
                >
                    <div className="flex items-center gap-2">
                        <FileText className="w-4 h-4" />
                        <span>Approval Tasks</span>
                    </div>
                </NavLink>

            </NavigationMenuList>
        </NavigationMenu>
    );
};
