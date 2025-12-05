import * as React from "react"
import { Check, ChevronsUpDown } from "lucide-react"
import { cn } from "@/lib/utils"
import { Button } from "@/Components/ui/button"
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover"

interface Product {
    id: string;
    name: string;
    price: number;
    stock?: number; // ใส่ ? เพราะ Controller อาจจะยังไม่ได้ส่ง stock มา
}

interface ProductComboboxProps {
    products: Product[];
    value?: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    placeholder?: string;
    error?: string; // เพิ่ม prop error
}

export default function ProductCombobox({
    products = [], // Default เป็น empty array ป้องกัน crash
    value,
    onChange,
    disabled = false,
    placeholder = "Select product...",
    error
}: ProductComboboxProps) {
    const [open, setOpen] = React.useState(false)

    // ใช้ optional chaining (?.) ป้องกัน error ถ้า products เป็น undefined (กรณีลืมใส่ default)
    const selectedProduct = products?.find((product) => product.id === value)

    return (
        <div className="w-full">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        className={cn(
                            "w-full justify-between font-normal px-3",
                            !value && "text-muted-foreground",
                            disabled && "opacity-50 cursor-not-allowed bg-gray-50",
                            error && "border-red-500" // เปลี่ยนสีขอบถ้ามี error
                        )}
                    >
                        <span className="truncate">
                            {selectedProduct ? selectedProduct.name : placeholder}
                        </span>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[300px] p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Search product name or code..." />
                        <CommandList>
                            <CommandEmpty>No product found.</CommandEmpty>
                            <CommandGroup>
                                {products?.map((product) => (
                                    <CommandItem
                                        key={product.id}
                                        value={product.name}
                                        onSelect={() => {
                                            onChange(product.id)
                                            setOpen(false)
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                "mr-2 h-4 w-4",
                                                value === product.id ? "opacity-100" : "opacity-0"
                                            )}
                                        />
                                        <div className="flex flex-col">
                                            <span>{product.name}</span>
                                            <span className="text-xs text-gray-500">
                                                {/* ตรวจสอบว่ามี stock หรือไม่ก่อนแสดงผล */}
                                                {product.stock !== undefined ? `Stock: ${product.stock} | ` : ''}
                                                ฿{product.price.toLocaleString()}
                                            </span>
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    )
}
