<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;

class ProductSaleVisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('product_sale_visit.index');
    }

    public function getOwnProducts(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $search = $request->get('search', '');
        $perPage = (int) $request->get('per_page', 10);

        $query = Product::where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->where('kind_product', 0)
            ->select(['id', 'name', 'sku', 'image', 'product_sale_visit', 'assigned_competitors_product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('sku', 'desc')->paginate($perPage);

        $products->getCollection()->transform(function ($product) use ($business_id) {
            $competitorIds = json_decode($product->assigned_competitors_product, true) ?? [];
            $competitors = [];
            if (!empty($competitorIds)) {
                $competitors = Product::where('business_id', $business_id)
                    ->whereIn('id', $competitorIds)
                    ->where('kind_product', 1)
                    ->select(['id', 'name', 'sku'])
                    ->get()
                    ->toArray();
            }
            $product->competitors = $competitors;
            return $product;
        });

        return response()->json($products);
    }

    public function getOtherProducts(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $search = $request->get('search', '');
        $perPage = (int) $request->get('per_page', 20);

        $query = Product::where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->where('kind_product', 1)
            ->select(['id', 'name', 'sku', 'image', 'product_sale_visit']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('sku', 'desc')->paginate($perPage);

        return response()->json($products);
    }

    public function toggleVisibility(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $product_id = $request->input('product_id');
            $visible = $request->input('visible');

            $product = Product::where('business_id', $business_id)
                ->where('id', $product_id)
                ->firstOrFail();

            $product->product_sale_visit = $visible ? 1 : null;

            if (!$visible && $product->kind_product == 0) {
                $product->assigned_competitors_product = null;
            }

            $product->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Toggle visibility error: ' . $e->getMessage());
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function bindCompetitors(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $product_id = $request->input('product_id');
            $competitor_ids = $request->input('competitor_ids', []);

            $product = Product::where('business_id', $business_id)
                ->where('id', $product_id)
                ->where('kind_product', 0)
                ->firstOrFail();

            $product->assigned_competitors_product = !empty($competitor_ids)
                ? json_encode(array_map('intval', $competitor_ids))
                : null;
            $product->save();

            $competitors = [];
            if (!empty($competitor_ids)) {
                $competitors = Product::where('business_id', $business_id)
                    ->whereIn('id', $competitor_ids)
                    ->where('kind_product', 1)
                    ->select(['id', 'name', 'sku'])
                    ->get()
                    ->toArray();
            }

            return response()->json(['success' => true, 'competitors' => $competitors]);
        } catch (\Exception $e) {
            \Log::error('Bind competitors error: ' . $e->getMessage());
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function getAvailableCompetitors(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $search = $request->get('search', '');

        $query = Product::where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->where('kind_product', 1)
            ->select(['id', 'name', 'sku']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * Legacy bulk update — kept for backward compatibility.
     */
    public function updateSaleVisit(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $selected_products = $request->input('selected_products', []);
            $product_sale_visit = $request->input('product_sale_visit');

            if (empty($selected_products)) {
                return response()->json(['success' => false, 'msg' => 'No products selected.']);
            }

            if (is_string($selected_products)) {
                $selected_products = explode(',', $selected_products);
            }

            $visit_value = ($product_sale_visit == 1) ? 1 : null;

            $updated_count = Product::where('business_id', $business_id)
                ->whereIn('id', $selected_products)
                ->update(['product_sale_visit' => $visit_value]);

            $action_text = ($product_sale_visit == 1) ? 'set as Product For Sale Visit' : 'removed from Product For Sale Visit';

            return response()->json(['success' => true, 'msg' => "Successfully {$action_text} for {$updated_count} products."]);
        } catch (\Exception $e) {
            \Log::error('Product Sale Visit update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'msg' => 'An error occurred while updating products.']);
        }
    }
}
