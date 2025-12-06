import React from 'react';
import { Input } from '@/Components/ui/input';
import { Search, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface SearchFilterProps {
    value?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}

export default function SearchFilter({
    value = '',
    onChange,
    placeholder = 'Search...',
    className = '',
}: SearchFilterProps) {
    return (
        <div className={`relative flex items-center ${className}`}>
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />

            <Input
                type="text"
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="pl-9 pr-8 w-full" // เพิ่ม pr-8 เพื่อเว้นที่ให้ปุ่ม X
            />

            {/* ปุ่ม Clear: แสดงเฉพาะเมื่อ value ไม่ว่าง */}
            {value && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="absolute right-1 top-1 h-7 w-7 text-muted-foreground hover:text-foreground"
                    onClick={() => onChange('')} // กดแล้วส่งค่าว่างกลับไป
                >
                    <X className="h-4 w-4" />
                    <span className="sr-only">Clear search</span>
                </Button>
            )}
        </div>
    );
}
