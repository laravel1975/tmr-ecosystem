import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { NavigationMenu, NavigationMenuItem, NavigationMenuList } from '@/Components/ui/navigation-menu';
import { cn } from '@/lib/utils';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { ChevronDown } from 'lucide-react';

export default function PurchaseNavigationMenu() {
    // ใช้ route().current() เพื่อเช็ค Active State
    const isOrderActive = route().current('purchase.orders.*');
    const isVendorActive = route().current('purchase.vendors.*');

    return (
        <NavigationMenu>
            <NavigationMenuList>
                {/* Dashboard Link (Placeholder for now) */}
                <NavigationMenuItem>
                    <Link
                        href={route('purchase.orders.index')} // เปลี่ยนเป็น dashboard route ในอนาคต
                        className={cn(
                            "h-10 px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground rounded-md flex items-center",
                            // route().current('purchase.dashboard') ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        Dashboard
                    </Link>
                </NavigationMenuItem>

                {/* Orders Menu */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className={cn(
                            "h-10 px-4 py-2 text-sm font-medium",
                            isOrderActive ? 'bg-accent text-accent-foreground' : ''
                        )}>
                            Orders <ChevronDown className="relative top-[1px] ml-1 h-3 w-3" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem asChild>
                            <Link href={route('purchase.orders.index')}>All Purchase Orders</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={route('purchase.orders.create')}>Create Order</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Vendors Menu (แก้ไขแล้ว) */}
                <NavigationMenuItem>
                    <Link
                        href={route('purchase.vendors.index')}
                        className={cn(
                            "h-10 px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground rounded-md flex items-center",
                            isVendorActive ? 'bg-accent text-accent-foreground' : ''
                        )}
                    >
                        Vendors
                    </Link>
                </NavigationMenuItem>

            </NavigationMenuList>
        </NavigationMenu>
    );
}
