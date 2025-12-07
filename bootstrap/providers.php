<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    TmrEcosystem\Inventory\Infrastructure\Providers\InventoryServiceProvider::class,
    TmrEcosystem\Warehouse\Infrastructure\Providers\WarehouseServiceProvider::class,
    TmrEcosystem\Stock\Infrastructure\Providers\StockServiceProvider::class,
    TmrEcosystem\Maintenance\Infrastructure\Providers\MaintenanceServiceProvider::class,
    TmrEcosystem\Sales\Infrastructure\Providers\SalesServiceProvider::class,
    TmrEcosystem\Communication\Infrastructure\Providers\CommunicationServiceProvider::class,
    TmrEcosystem\Customers\Infrastructure\Providers\CustomerServiceProvider::class,
    TmrEcosystem\Logistics\Infrastructure\Providers\LogisticsServiceProvider::class,
    TmrEcosystem\Shared\Infrastructure\Providers\SharedServiceProvider::class,
    TmrEcosystem\Approval\Infrastructure\Providers\ApprovalServiceProvider::class,
    TmrEcosystem\Purchase\Infrastructure\Providers\PurchaseServiceProvider::class,
    TmrEcosystem\Manufacturing\Infrastructure\Providers\ManufacturingServiceProvider::class,
];
