<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Connector\Transformers\ProductResource;

/**
 * @group SaleApp Products
 * @authenticated
 *
 * Mobile API — returns products grouped by categories based on App Settings.
 * User is identified by Bearer token only. No query parameters required.
 */
class SaleAppProductController extends ApiController
{
    /**
     * GET /connector/api/mobile/saleapp-products
     *
     * Returns category groups with products for the authenticated user.
     * Behaviour is controlled by two business settings:
     *   - enable_app_sale_category
     *   - enable_user_product_visibility
     *
     * Product structure is identical to GET /connector/api/product.
     * Each product gains one extra field: app_sort_order (null when not using app categories).
     */
    public function index()
    {
        $user        = Auth::user();
        $user_id     = $user->id;
        $business_id = $user->business_id;

        // ── Business settings ────────────────────────────────────────────────
        $business = DB::table('business')->where('id', $business_id)->first();

        $enable_app_sale_category       = !empty($business->enable_app_sale_category);
        $enable_user_product_visibility = !empty($business->enable_user_product_visibility);
        $show_all_product               = isset($business->show_all_product) ? (bool) $business->show_all_product : true;
        $display_mode                   = $show_all_product ? 'all' : 'assigned';

        $pos_settings    = json_decode($business->pos_settings ?? '{}', true) ?? [];
        $allow_overselling = !empty($pos_settings['allow_overselling']);

        $settings = [
            'enable_app_sale_category'       => $enable_app_sale_category,
            'enable_user_product_visibility' => $enable_user_product_visibility,
            'default_product_display_mode'   => $display_mode,
            // Sale Control
            'allow_edit_sale_price'          => isset($business->allow_edit_sale_price) ? (bool) $business->allow_edit_sale_price : true,
            'allow_edit_discount'            => isset($business->allow_edit_discount)   ? (bool) $business->allow_edit_discount   : true,
            'show_stock_on_sale'             => !empty($business->show_stock_on_sale),
            'allow_overselling'              => $allow_overselling,
        ];

        $category_source = $enable_app_sale_category ? 'app_sale_category' : 'system_category';

        // ── Eager-load relations — identical to ProductController::__getProducts() ──
        $with = [
            'product_variations.variations.variation_location_details',
            'brand',
            'unit',
            'category',
            'sub_category',
            'product_tax',
            'product_variations.variations.media',
            'product_locations',
        ];

        // ── Base product query (active + sellable) ────────────────────────────
        $base_query = Product::where('products.business_id', $business_id)
            ->where('products.not_for_selling', 0)
            ->where('products.is_inactive', 0)
            ->with($with);

        // ── User product visibility ───────────────────────────────────────────
        $user_assigned_ids = null;
        $has_user_products = true;

        if ($enable_user_product_visibility) {
            $user_assigned_ids = DB::table('product_user_visibilities')
                ->where('user_id', $user_id)
                ->where('business_id', $business_id)
                ->pluck('product_id')
                ->toArray();

            $has_user_products = !empty($user_assigned_ids);
        }

        // ── ROUTE A: App Sale Category ON ─────────────────────────────────────
        if ($enable_app_sale_category) {

            $app_categories = DB::table('app_categories')
                ->where('business_id', $business_id)
                ->orderBy('id')
                ->get();

            $pac_rows = DB::table('product_app_categories as pac')
                ->join('app_categories as ac', 'pac.app_category_id', '=', 'ac.id')
                ->where('ac.business_id', $business_id)
                ->select('pac.product_id', 'pac.app_category_id', 'pac.sort_order')
                ->orderBy('pac.app_category_id')
                ->orderBy('pac.sort_order')
                ->orderBy('pac.product_id')
                ->get();

            $pac_by_cat          = [];
            $all_app_product_ids = $pac_rows->pluck('product_id')->unique()->toArray();

            foreach ($pac_rows as $row) {
                $pac_by_cat[$row->app_category_id][] = $row;
            }

            // Intersect with user-assigned products when visibility ON
            $effective_product_ids = $all_app_product_ids;

            if ($enable_user_product_visibility) {
                if (!$has_user_products) {
                    if ($display_mode === 'assigned') {
                        return response()->json([
                            'success'         => true,
                            'mode'            => 'assigned_only_empty',
                            'category_source' => $category_source,
                            'message'         => 'No products assigned to this user.',
                            'settings'        => $settings,
                            'data'            => [],
                        ]);
                    }
                    // Failsafe: show all category products
                } else {
                    $effective_product_ids = array_values(
                        array_intersect($all_app_product_ids, $user_assigned_ids)
                    );
                }
            }

            $products_collection = (clone $base_query)
                ->whereIn('products.id', $effective_product_ids)
                ->get();

            $this->attachCompetitorMap($products_collection, $business_id);

            $com_stock_map = $this->buildComStockMap($products_collection, $business_id);
            $products_map  = $products_collection->keyBy('id');

            $data = [];
            foreach ($app_categories as $idx => $cat) {
                $rows_for_cat = $pac_by_cat[$cat->id] ?? [];

                $visible = [];
                foreach ($rows_for_cat as $row) {
                    if (!isset($products_map[$row->product_id])) {
                        continue;
                    }
                    $prod_array                   = (new ProductResource($products_map[$row->product_id]))->toArray(request());
                    $prod_array['app_sort_order'] = $row->sort_order;
                    $prod_array['com_stock']      = $com_stock_map[$row->product_id] ?? [];
                    $visible[]                    = $prod_array;
                }

                if (empty($visible)) {
                    continue;
                }

                $data[] = [
                    'id'         => $cat->id,
                    'name'       => $cat->name,
                    'code'       => $cat->code,
                    'sort_order' => $idx + 1,
                    'products'   => $visible,
                ];
            }

            return response()->json([
                'success'         => true,
                'mode'            => 'app_sale_category',
                'category_source' => $category_source,
                'message'         => null,
                'settings'        => $settings,
                'data'            => $data,
            ]);
        }

        // ── ROUTE B: App Sale Category OFF (system categories) ────────────────

        if ($enable_user_product_visibility) {
            if (!$has_user_products) {
                if ($display_mode === 'assigned') {
                    return response()->json([
                        'success'         => true,
                        'mode'            => 'assigned_only_empty',
                        'category_source' => $category_source,
                        'message'         => 'No products assigned to this user.',
                        'settings'        => $settings,
                        'data'            => [],
                    ]);
                }
                // Failsafe: show all products
            } else {
                $base_query->whereIn('products.id', $user_assigned_ids);
            }
        }

        $products_collection = $base_query->orderBy('products.name')->get();

        $this->attachCompetitorMap($products_collection, $business_id);

        $com_stock_map = $this->buildComStockMap($products_collection, $business_id);

        $cat_groups = [];
        foreach ($products_collection as $product) {
            $cat_id   = $product->category_id ?? 0;
            $cat_name = optional($product->category)->name ?? 'Uncategorized';

            if (!isset($cat_groups[$cat_id])) {
                $cat_groups[$cat_id] = [
                    'id'         => 'system_' . $cat_id,
                    'name'       => $cat_name,
                    'sort_order' => null,
                    'products'   => [],
                ];
            }

            $prod_array                   = (new ProductResource($product))->toArray(request());
            $prod_array['app_sort_order'] = null;
            $prod_array['com_stock']      = $com_stock_map[$product->id] ?? [];

            $cat_groups[$cat_id]['products'][] = $prod_array;
        }

        usort($cat_groups, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        foreach ($cat_groups as $i => &$cg) {
            $cg['sort_order'] = $i + 1;
        }
        unset($cg);

        $mode = ($enable_user_product_visibility && $has_user_products) ? 'user_visibility' : 'legacy';

        return response()->json([
            'success'         => true,
            'mode'            => $mode,
            'category_source' => $category_source,
            'message'         => null,
            'settings'        => $settings,
            'data'            => array_values($cat_groups),
        ]);
    }

    /**
     * Build com_stock map for combo / combo_single products.
     *
     * Returns [ product_id => [ ['main_product_id', 'main_product_name',
     *                            'variation_id', 'qty_available',
     *                            'combo_quantity', 'stock_available'], ... ] ]
     *
     * stock_available = floor( sum(qty_available across locations) / combo_quantity )
     */
    private function buildComStockMap($products_collection, $business_id)
    {
        $combo_products = $products_collection->filter(function ($p) {
            return in_array($p->type, ['combo', 'combo_single']);
        });

        if ($combo_products->isEmpty()) {
            return [];
        }

        $combo_product_ids = $combo_products->pluck('id')->toArray();

        // 1. Get all variations of the combo products that have combo_variations
        $variations = DB::table('variations')
            ->whereIn('product_id', $combo_product_ids)
            ->whereNotNull('combo_variations')
            ->where('combo_variations', '!=', '[]')
            ->where('combo_variations', '!=', '')
            ->get(['id', 'product_id', 'combo_variations']);

        if ($variations->isEmpty()) {
            return [];
        }

        // 2. Parse combo_variations → collect referenced single variation IDs + quantities
        //    combo_map[combo_product_id][single_variation_id] = quantity
        $combo_map          = [];
        $single_var_ids_all = [];

        foreach ($variations as $var) {
            $cv_list = json_decode($var->combo_variations, true) ?? [];
            foreach ($cv_list as $cv) {
                $single_var_id = (int) $cv['variation_id'];
                $quantity      = (float) $cv['quantity'];
                if ($quantity <= 0) {
                    continue;
                }
                // Keep smallest quantity if same variation appears in multiple combo variations
                if (!isset($combo_map[$var->product_id][$single_var_id])) {
                    $combo_map[$var->product_id][$single_var_id] = $quantity;
                }
                $single_var_ids_all[] = $single_var_id;
            }
        }

        $single_var_ids_all = array_unique($single_var_ids_all);

        if (empty($single_var_ids_all)) {
            return [];
        }

        // 3. Map single variation_id → its product_id
        $single_variations      = DB::table('variations')
            ->whereIn('id', $single_var_ids_all)
            ->get(['id', 'product_id']);
        $variation_to_product   = $single_variations->pluck('product_id', 'id')->toArray();

        // 4. Sum qty_available across all locations per single variation
        $stock_rows    = DB::table('variation_location_details')
            ->whereIn('variation_id', $single_var_ids_all)
            ->select('variation_id', DB::raw('SUM(qty_available) as total_qty'))
            ->groupBy('variation_id')
            ->get();
        $variation_stock = $stock_rows->pluck('total_qty', 'variation_id')->toArray();

        // 5. Get names and unit_id for the single (main) products
        $single_product_ids = array_unique(array_values($variation_to_product));
        $single_products    = DB::table('products')
            ->whereIn('id', $single_product_ids)
            ->get(['id', 'name', 'unit_id']);
        $product_names    = $single_products->pluck('name', 'id')->toArray();
        $product_unit_ids = $single_products->pluck('unit_id', 'id')->toArray();

        // 5b. Get short_name from units table
        $unit_ids        = array_unique(array_filter(array_values($product_unit_ids)));
        $unit_short_names = DB::table('units')
            ->whereIn('id', $unit_ids)
            ->pluck('short_name', 'id')
            ->toArray();

        // 6. Build the final map
        $com_stock_map = [];

        foreach ($combo_map as $combo_product_id => $single_var_quantities) {
            $com_stock_map[$combo_product_id] = [];

            foreach ($single_var_quantities as $single_var_id => $combo_qty) {
                $main_product_id = $variation_to_product[$single_var_id] ?? null;
                $qty_available   = (float) ($variation_stock[$single_var_id] ?? 0);
                $full      = (int) floor($qty_available / $combo_qty);
                $remainder = (int) ($qty_available - ($combo_qty * $full));

                $unit_id        = $main_product_id ? ($product_unit_ids[$main_product_id] ?? null) : null;
                $unit_short_name = $unit_id ? ($unit_short_names[$unit_id] ?? null) : null;

                $com_stock_map[$combo_product_id][] = [
                    'main_product_id'          => $main_product_id,
                    'main_product_name'        => $main_product_id ? ($product_names[$main_product_id] ?? null) : null,
                    'variation_id'             => $single_var_id,
                    'qty_available'            => $qty_available,
                    'combo_quantity'           => $combo_qty,
                    'stock_available'          => $remainder > 0 ? "{$full},{$remainder}" : (string) $full,
                    'component_unit_short_name' => $unit_short_name,
                ];
            }
        }

        return $com_stock_map;
    }

    /**
     * Attach mapped_own_product_id to each product.
     * Mirrors competitor map logic in ProductController::__getProducts().
     */
    private function attachCompetitorMap($products_collection, $business_id)
    {
        $ownProductsWithComp = Product::where('business_id', $business_id)
            ->where('kind_product', 0)
            ->whereNotNull('assigned_competitors_product')
            ->select(['id', 'assigned_competitors_product'])
            ->get();

        $competitorMap = [];
        foreach ($ownProductsWithComp as $op) {
            $compIds = json_decode($op->assigned_competitors_product, true) ?? [];
            foreach ($compIds as $cid) {
                $competitorMap[(int) $cid] = $op->id;
            }
        }

        foreach ($products_collection as $product) {
            $product->mapped_own_product_id = ($product->kind_product == 1)
                ? ($competitorMap[$product->id] ?? null)
                : null;
        }
    }
}
