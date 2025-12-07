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
import { ChevronDown } from 'lucide-react';

/*
|--------------------------------------------------------------------------
| (2. นี่คือ Component เมนูของ Maintenance BC)
|--------------------------------------------------------------------------
| (3. เพิ่ม export default)
*/
export default function ManufacturingNavigationMenu() {
    return (
        <NavigationMenu>
            <NavigationMenuList>
                {/* (เมนู 1: Dashboard ของ Maintenance) */}
                <NavigationMenuItem>
                    <Link
                        href={route('manufacturing.dashboard.index')}
                        className={cn(
                            navigationMenuTriggerStyle(),
                            route().current('maintenance.dashboard.index') ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        Dashboard
                    </Link>
                </NavigationMenuItem>

                {/* (เมนู 2: Work Orders) */}
                <NavigationMenuItem>
                    <Link
                        href={route('manufacturing.production-orders.index')}
                        className={cn(
                            navigationMenuTriggerStyle(),
                            route().current('manufacturing.production-orders.index') ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        Manufactoring Orders
                    </Link>
                </NavigationMenuItem>

                {/* (เมนู 3: Requests) */}
                <NavigationMenuItem>
                    <Link
                        href={route('manufacturing.boms.index')}
                        className={cn(
                            navigationMenuTriggerStyle(),
                            route().current('manufacturing.boms.index') ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        BoMs
                    </Link>
                </NavigationMenuItem>
            </NavigationMenuList>
        </NavigationMenu>
    );
};
