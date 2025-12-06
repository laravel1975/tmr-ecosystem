<?php

namespace TmrEcosystem\Purchase\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use TmrEcosystem\Purchase\Domain\Models\Vendor;
use TmrEcosystem\Purchase\Presentation\Http\Requests\StoreVendorRequest;
use TmrEcosystem\Purchase\Presentation\Http\Requests\UpdateVendorRequest;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            $direction = $request->input('direction', 'asc');
            // Validate direction
            if (!in_array(strtolower($direction), ['asc', 'desc'])) {
                $direction = 'asc';
            }
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        return Inertia::render('Purchase/Vendors/Index', [
            'vendors' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Purchase/Vendors/Create');
    }

    public function store(StoreVendorRequest $request)
    {
        Vendor::create($request->validated());

        return redirect()->route('purchase.vendors.index')
            ->with('success', 'Vendor created successfully.');
    }

    public function edit(Vendor $vendor)
    {
        return Inertia::render('Purchase/Vendors/Edit', [
            'vendor' => $vendor
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor)
    {
        $vendor->update($request->validated());

        return redirect()->route('purchase.vendors.index')
            ->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return redirect()->back()
            ->with('success', 'Vendor deleted successfully.');
    }
}
