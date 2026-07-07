<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Main App Settings page
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $business    = DB::table('business')->where('id', $business_id)->first();

        // System categories for filter dropdown
        $system_categories = DB::table('categories')
            ->where('business_id', $business_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Business locations for filter dropdown
        $business_locations = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get(['id', 'name']);

        // Global settings with safe defaults (columns may not exist yet on older DBs)
        $settings = [
            'enable_app_sale_category'       => isset($business->enable_app_sale_category)       ? (bool) $business->enable_app_sale_category       : false,
            'enable_user_product_visibility' => isset($business->enable_user_product_visibility) ? (bool) $business->enable_user_product_visibility : false,
            'show_all_product'               => isset($business->show_all_product)               ? (bool) $business->show_all_product               : true,
            'show_assigned_product'          => isset($business->show_assigned_product)          ? (bool) $business->show_assigned_product          : false,
            // Sale Control
            'allow_edit_sale_price'          => isset($business->allow_edit_sale_price)          ? (bool) $business->allow_edit_sale_price          : true,
            'allow_edit_discount'            => isset($business->allow_edit_discount)            ? (bool) $business->allow_edit_discount            : true,
            'show_stock_on_sale'             => isset($business->show_stock_on_sale)             ? (bool) $business->show_stock_on_sale             : false,
        ];

        return view('app_settings.index', compact('settings', 'system_categories', 'business_locations'));
    }

    /**
     * Save global toggle settings (enable_app_sale_category, etc.)
     */
    public function saveGlobalSettings(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        DB::table('business')->where('id', $business_id)->update([
            'enable_app_sale_category'       => $request->input('enable_app_sale_category') ? 1 : 0,
            'enable_user_product_visibility' => $request->input('enable_user_product_visibility') ? 1 : 0,
            'show_all_product'               => $request->input('display_mode') === 'all' ? 1 : 0,
            'show_assigned_product'          => $request->input('display_mode') === 'assigned' ? 1 : 0,
            // Sale Control
            'allow_edit_sale_price'          => $request->input('allow_edit_sale_price') ? 1 : 0,
            'allow_edit_discount'            => $request->input('allow_edit_discount') ? 1 : 0,
            'show_stock_on_sale'             => $request->input('show_stock_on_sale') ? 1 : 0,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * DataTable JSON — products list with assigned app categories
     */
    public function getProducts(Request $request)
    {
        $business_id       = $request->session()->get('user.business_id');
        $search            = $request->input('search.value', '');
        $category_filter   = $request->input('system_category_id', '');
        $app_cat_filter    = $request->input('app_category_id', '');

        $query = DB::table('products as p')
            ->leftJoin('categories as cat', 'p.category_id', '=', 'cat.id')
            ->where('p.business_id', $business_id)
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.category_id',
                DB::raw('COALESCE(cat.name, "—") as system_category')
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.sku', 'like', "%{$search}%");
            });
        }

        if (!empty($category_filter)) {
            $query->where('p.category_id', $category_filter);
        }

        $totalCount = (clone $query)->count();

        // Pagination
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length > 0) {
            $query->offset($start)->limit($length);
        }

        $products = $query->orderBy('p.name')->get();
        $productIds = $products->pluck('id')->toArray();

        // Batch fetch assigned app categories for these products
        $assignments = [];
        if (!empty($productIds)) {
            $rows = DB::table('product_app_categories as pac')
                ->join('app_categories as ac', 'pac.app_category_id', '=', 'ac.id')
                ->whereIn('pac.product_id', $productIds)
                ->select('pac.product_id', 'pac.app_category_id', 'pac.sort_order', 'ac.name as cat_name', 'ac.code as cat_code')
                ->orderBy('pac.sort_order')
                ->get();

            foreach ($rows as $r) {
                $assignments[$r->product_id][] = $r;
            }
        }

        // Apply app_category filter (after batch fetch, filter products that belong to this category)
        if (!empty($app_cat_filter)) {
            $products = $products->filter(function ($p) use ($assignments, $app_cat_filter) {
                if (empty($assignments[$p->id])) return false;
                foreach ($assignments[$p->id] as $a) {
                    if ($a->app_category_id == $app_cat_filter) return true;
                }
                return false;
            });
        }

        // Badge colours cycle
        $badge_colours = ['#f39c12', '#27ae60', '#2980b9', '#8e44ad', '#e74c3c', '#16a085'];

        $data = [];
        foreach ($products as $idx => $p) {
            $cats = $assignments[$p->id] ?? [];

            // Build assigned category badges
            $badges = '';
            foreach ($cats as $c) {
                $colour  = $badge_colours[$c->app_category_id % count($badge_colours)];
                $badges .= '<span class="label" style="background:'.$colour.';margin-right:3px;">'
                         . e($c->cat_name) . '</span>';
            }
            if (empty($badges)) $badges = '—';

            // Build sort order display (Code: Order per category)
            $sort_html = '';
            foreach ($cats as $c) {
                $sort_html .= '<span style="margin-right:6px;font-size:11px;">'
                            . '<b>' . e($c->cat_code) . '</b>: ' . $c->sort_order . '</span>';
            }
            if (empty($sort_html)) $sort_html = '—';

            $data[] = [
                'id'                => $p->id,
                'checkbox'          => '<input type="checkbox" class="product-checkbox" value="'.$p->id.'">',
                'name'              => e($p->name),
                'sku'               => e($p->sku ?? '—'),
                'system_category'   => e($p->system_category),
                'assigned_cats'     => $badges,
                'sort_order'        => $sort_html,
            ];
        }

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $totalCount,
            'recordsFiltered' => $totalCount,
            'data'            => array_values($data),
        ]);
    }

    /**
     * GET all app categories for this business
     */
    public function getCategories(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $cats = DB::table('app_categories as ac')
            ->where('ac.business_id', $business_id)
            ->select(
                'ac.id',
                'ac.name',
                'ac.code',
                DB::raw('(SELECT COUNT(*) FROM product_app_categories WHERE app_category_id = ac.id) as product_count')
            )
            ->orderBy('ac.id')
            ->get();

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /**
     * Create a new app category
     */
    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
        ]);

        $business_id = $request->session()->get('user.business_id');

        // Check duplicate code within this business
        $exists = DB::table('app_categories')
            ->where('business_id', $business_id)
            ->where('code', $request->input('code'))
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Category code already exists.'], 422);
        }

        $id = DB::table('app_categories')->insertGetId([
            'business_id' => $business_id,
            'name'        => $request->input('name'),
            'code'        => strtoupper(trim($request->input('code'))),
            'created_by'  => auth()->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $cat = DB::table('app_categories')->where('id', $id)->first();

        return response()->json(['success' => true, 'data' => $cat]);
    }

    /**
     * Update an existing app category (inline edit)
     */
    public function updateCategory(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
        ]);

        // Ensure category belongs to this business
        $cat = DB::table('app_categories')->where('id', $id)->where('business_id', $business_id)->first();
        if (!$cat) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        // Check duplicate code (exclude self)
        $exists = DB::table('app_categories')
            ->where('business_id', $business_id)
            ->where('code', $request->input('code'))
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Category code already exists.'], 422);
        }

        DB::table('app_categories')->where('id', $id)->update([
            'name'       => $request->input('name'),
            'code'       => strtoupper(trim($request->input('code'))),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete an app category (and its product assignments)
     */
    public function deleteCategory($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $cat = DB::table('app_categories')->where('id', $id)->where('business_id', $business_id)->first();
        if (!$cat) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('product_app_categories')->where('app_category_id', $id)->delete();
            DB::table('app_categories')->where('id', $id)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Assign selected products to an app category with auto-increment sort order
     */
    public function assignCategory(Request $request)
    {
        $request->validate([
            'product_ids'     => 'required|array|min:1',
            'app_category_id' => 'required|integer',
            'starting_sort'   => 'required|integer|min:1',
        ]);

        $business_id     = $request->session()->get('user.business_id');
        $app_category_id = $request->input('app_category_id');
        $starting_sort   = (int) $request->input('starting_sort', 1);
        $product_ids     = $request->input('product_ids');

        // Verify category belongs to this business
        $cat = DB::table('app_categories')->where('id', $app_category_id)->where('business_id', $business_id)->first();
        if (!$cat) {
            return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $sort = $starting_sort;
            foreach ($product_ids as $product_id) {
                // Upsert: insert or update sort_order
                $exists = DB::table('product_app_categories')
                    ->where('product_id', $product_id)
                    ->where('app_category_id', $app_category_id)
                    ->exists();

                if ($exists) {
                    DB::table('product_app_categories')
                        ->where('product_id', $product_id)
                        ->where('app_category_id', $app_category_id)
                        ->update(['sort_order' => $sort, 'updated_at' => now()]);
                } else {
                    DB::table('product_app_categories')->insert([
                        'product_id'      => $product_id,
                        'app_category_id' => $app_category_id,
                        'sort_order'      => $sort,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
                $sort++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Remove selected products from an app category.
     * If app_category_id is omitted, removes from ALL app categories.
     */
    public function removeFromCategory(Request $request)
    {
        $request->validate([
            'product_ids'     => 'required|array|min:1',
            'app_category_id' => 'nullable|integer',
        ]);

        $business_id     = $request->session()->get('user.business_id');
        $app_category_id = $request->input('app_category_id');
        $product_ids     = $request->input('product_ids');

        $query = DB::table('product_app_categories')
            ->whereIn('product_id', $product_ids);

        if (!empty($app_category_id)) {
            // Verify category belongs to this business
            $cat = DB::table('app_categories')
                ->where('id', $app_category_id)
                ->where('business_id', $business_id)
                ->first();
            if (!$cat) {
                return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
            }
            $query->where('app_category_id', $app_category_id);
        }
        // If no app_category_id → removes from ALL categories for these products

        $query->delete();

        return response()->json(['success' => true]);
    }

    // =========================================================================
    // USER PRODUCT VISIBILITY
    // =========================================================================

    /**
     * GET users belonging to this business (for dropdowns)
     */
    public function getUsers(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $users = DB::table('users')
            ->where('business_id', $business_id)
            ->select('id', 'first_name', 'surname', 'last_name', 'username')
            ->orderBy('first_name')
            ->get()
            ->map(function ($u) {
                $name = trim(implode(' ', array_filter([$u->surname, $u->first_name, $u->last_name])));
                $u->display_name = $name ?: $u->username;
                return $u;
            });

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * DataTable JSON — products with their user-visibility assignments
     */
    public function getUserProducts(Request $request)
    {
        $business_id     = $request->session()->get('user.business_id');
        $search          = $request->input('search.value', '');
        $category_filter = $request->input('system_category_id', '');
        $user_filter     = $request->input('user_id', '');
        $location_filter = $request->input('location_id', '');

        $query = DB::table('products as p')
            ->leftJoin('categories as cat', 'p.category_id', '=', 'cat.id')
            ->where('p.business_id', $business_id)
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.category_id',
                DB::raw('COALESCE(cat.name, "—") as system_category')
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.sku', 'like', "%{$search}%");
            });
        }

        // Filter products by business location (same as Products list — uses product_locations pivot)
        if (!empty($location_filter)) {
            $query->whereExists(function ($q) use ($location_filter) {
                $q->select(DB::raw(1))
                  ->from('product_locations as pl')
                  ->whereColumn('pl.product_id', 'p.id')
                  ->where('pl.location_id', $location_filter);
            });
        }

        if (!empty($category_filter)) {
            $query->where('p.category_id', $category_filter);
        }

        $totalCount = (clone $query)->count();

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length > 0) {
            $query->offset($start)->limit($length);
        }

        $products   = $query->orderBy('p.name')->get();
        $productIds = $products->pluck('id')->toArray();

        // Batch-fetch user assignments for this page
        $assignments = [];
        if (!empty($productIds)) {
            $rows = DB::table('product_user_visibilities as puv')
                ->join('users as u', 'puv.user_id', '=', 'u.id')
                ->whereIn('puv.product_id', $productIds)
                ->where('puv.business_id', $business_id)
                ->select(
                    'puv.product_id',
                    'puv.user_id',
                    DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as display_name"),
                    'u.username'
                )
                ->get();

            foreach ($rows as $r) {
                $assignments[$r->product_id][] = $r;
            }
        }

        // Apply user filter (keep only products assigned to that user)
        if (!empty($user_filter)) {
            $products = $products->filter(function ($p) use ($assignments, $user_filter) {
                if (empty($assignments[$p->id])) return false;
                foreach ($assignments[$p->id] as $a) {
                    if ($a->user_id == $user_filter) return true;
                }
                return false;
            });
        }

        $badge_colours = ['#e67e22', '#27ae60', '#8e44ad', '#e74c3c', '#2980b9', '#16a085', '#d35400', '#c0392b'];

        $data = [];
        foreach ($products as $p) {
            $users = $assignments[$p->id] ?? [];

            $badges = '';
            foreach ($users as $u) {
                $colour  = $badge_colours[$u->user_id % count($badge_colours)];
                $name    = trim($u->display_name) ?: $u->username;
                $badges .= '<span class="label" style="background:' . $colour . ';margin-right:3px;">'
                         . e($name) . '</span>';
            }
            if (empty($badges)) $badges = '—';

            $data[] = [
                'id'               => $p->id,
                'checkbox'         => '<input type="checkbox" class="user-product-checkbox" value="' . $p->id . '">',
                'name'             => e($p->name),
                'sku'              => e($p->sku ?? '—'),
                'system_category'  => e($p->system_category),
                'visible_to_users' => $badges,
            ];
        }

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $totalCount,
            'recordsFiltered' => $totalCount,
            'data'            => array_values($data),
        ]);
    }

    /**
     * Assign selected products to a user (upsert — skip if already assigned)
     */
    public function assignToUser(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'user_id'     => 'required|integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id     = $request->input('user_id');
        $product_ids = $request->input('product_ids');

        $user = DB::table('users')->where('id', $user_id)->where('business_id', $business_id)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        DB::beginTransaction();
        try {
            foreach ($product_ids as $product_id) {
                $exists = DB::table('product_user_visibilities')
                    ->where('product_id', $product_id)
                    ->where('user_id', $user_id)
                    ->exists();

                if (!$exists) {
                    DB::table('product_user_visibilities')->insert([
                        'product_id'  => $product_id,
                        'user_id'     => $user_id,
                        'business_id' => $business_id,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Remove selected products from a user's visibility list.
     * If user_id is omitted, removes from ALL users for those products.
     */
    public function removeFromUser(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'user_id'     => 'nullable|integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id     = $request->input('user_id');
        $product_ids = $request->input('product_ids');

        $query = DB::table('product_user_visibilities')
            ->whereIn('product_id', $product_ids)
            ->where('business_id', $business_id);

        if (!empty($user_id)) {
            $user = DB::table('users')->where('id', $user_id)->where('business_id', $business_id)->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found.'], 404);
            }
            $query->where('user_id', $user_id);
        }
        // If no user_id → removes from ALL users for these products

        $query->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Check which of the given product_ids are currently assigned to a user
     */
    public function checkUserAssignments(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $user_id     = $request->input('user_id');
        $product_ids = $request->input('product_ids', []);

        if (empty($user_id) || empty($product_ids)) {
            return response()->json(['success' => true, 'assigned' => []]);
        }

        $assigned = DB::table('product_user_visibilities')
            ->where('user_id', $user_id)
            ->where('business_id', $business_id)
            ->whereIn('product_id', $product_ids)
            ->pluck('product_id')
            ->toArray();

        return response()->json(['success' => true, 'assigned' => $assigned]);
    }

    /**
     * Preview sort order for the assign modal (returns product names + proposed sort)
     */
    public function previewSortOrder(Request $request)
    {
        $business_id  = $request->session()->get('user.business_id');
        $product_ids  = $request->input('product_ids', []);
        $starting_sort = (int) $request->input('starting_sort', 1);

        $products = DB::table('products')
            ->whereIn('id', $product_ids)
            ->where('business_id', $business_id)
            ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $product_ids)) . ')')
            ->pluck('name', 'id');

        $preview = [];
        $sort = $starting_sort;
        foreach ($product_ids as $pid) {
            $preview[] = [
                'product_id'   => $pid,
                'product_name' => $products[$pid] ?? '?',
                'sort_order'   => $sort++,
            ];
        }

        return response()->json(['success' => true, 'data' => $preview]);
    }
}
