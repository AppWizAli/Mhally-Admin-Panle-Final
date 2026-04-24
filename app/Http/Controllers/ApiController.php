<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function suppliers(Request $request)
    {
        $sort = $request->query('sort', 'default');
        $query = DB::table('suppliers')
            ->select('suppliers.*')
            ->where('status', 'active');

        if ($request->filled('city')) {
            $query->where('city', $request->query('city'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('business_name', 'like', $search)
                    ->orWhere('owner_name', 'like', $search)
                    ->orWhere('city', 'like', $search);
            });
        }

        if ($sort === 'low_min_order') {
            $query->orderBy('minimum_order_amount');
        } elseif ($sort === 'cheapest') {
            $query->orderBy(
                DB::raw('(SELECT MIN(price) FROM supplier_products WHERE supplier_products.supplier_id = suppliers.id AND supplier_products.status = "active")')
            );
        } else {
            $query->orderByDesc('is_verified')->orderBy('business_name');
        }

        return $this->ok($query->limit(100)->get());
    }

    public function products(Request $request)
    {
        $query = DB::table('supplier_products as sp')
            ->join('catalog_products as cp', 'cp.id', '=', 'sp.catalog_product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'cp.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('sp.status', 'active')
            ->select('sp.*', 'cp.name as catalog_name', 'cp.packaging', 'cp.unit_type', 'cp.image_url', 'c.name as category_name', 's.business_name as supplier_name', 's.city as supplier_city');

        if ($request->filled('supplier_id')) {
            $query->where('sp.supplier_id', (int) $request->query('supplier_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('cp.category_id', (int) $request->query('category_id'));
        }
        if ($request->filled('city')) {
            $query->where('s.city', $request->query('city'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('cp.name', 'like', $search)
                    ->orWhere('c.name', 'like', $search)
                    ->orWhere('s.business_name', 'like', $search);
            });
        }

        return $this->ok($query->orderByDesc('sp.is_featured')->limit(200)->get());
    }

    public function offers(Request $request)
    {
        $query = DB::table('offers as o')
            ->leftJoin('suppliers as s', 's.id', '=', 'o.supplier_id')
            ->leftJoin('catalog_products as cp', 'cp.id', '=', 'o.catalog_product_id')
            ->where('o.status', 'active')
            ->where(function ($builder) {
                $builder->whereNull('o.starts_at')->orWhere('o.starts_at', '<=', now());
            })
            ->where(function ($builder) {
                $builder->whereNull('o.ends_at')->orWhere('o.ends_at', '>=', now());
            })
            ->select('o.*', 's.business_name as supplier_name', 'cp.name as product_name');

        if ($request->filled('city')) {
            $city = $request->query('city');
            $query->where(function ($builder) use ($city) {
                $builder->whereNull('o.city')->orWhere('o.city', '')->orWhere('o.city', $city);
            });
        }

        return $this->ok($query->orderByDesc('o.id')->limit(50)->get());
    }

    public function createSupplierOffer(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'listing_id' => ['required', 'integer'],
            'catalog_product_id' => ['required', 'integer'],
            'title' => ['required', 'string'],
            'offer_price' => ['required', 'numeric', 'min:0.01'],
            'maximum_quantity' => ['nullable', 'integer'],
        ]);

        $id = DB::table('offers')->updateOrInsert(
            [
                'supplier_id' => $data['supplier_id'],
                'supplier_product_id' => $data['listing_id'],
            ],
            [
                'title' => $data['title'],
                'description' => $request->input('description', 'Supplier special offer'),
                'badge_label' => $request->input('badge_label', 'Special Offer'),
                'discount_label' => $request->input('discount_label', number_format((float) $data['offer_price'], 2) . ' PKR'),
                'supplier_product_id' => $data['listing_id'],
                'catalog_product_id' => $data['catalog_product_id'],
                'offer_price' => $data['offer_price'],
                'maximum_quantity' => $data['maximum_quantity'] ?? null,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->ok(['saved' => (bool) $id], 'Offer saved.');
    }

    private function ok($data, string $message = 'OK')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
