<?php

namespace App\Http\Controllers;

use App\Product;
use App\ProductSaleTarget;
use App\ProductSaleTargetDetail;
use App\User;
use App\Variation;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ProductSaleTargetController extends Controller
{
    /**
     * Display the main page with Assign Targets and Assigned List tabs.
     */
    public function index()
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $users = User::where('business_id', $business_id)
            ->select('id', 'first_name', 'last_name', 'username')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(function ($u) {
                return [$u->id => $u->first_name . ' ' . $u->last_name];
            });

        return view('product_sale_target.index', compact('users'));
    }

    /**
     * AJAX: DataTables for Assigned List tab.
     */
    public function assignedList(Request $request)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $targets = ProductSaleTarget::where('product_sale_targets.business_id', $business_id)
            ->whereNull('product_sale_targets.deleted_at')
            ->join('users', 'users.id', '=', 'product_sale_targets.user_id')
            ->select(
                'product_sale_targets.id',
                'product_sale_targets.start_date',
                'product_sale_targets.end_date',
                'product_sale_targets.status',
                \DB::raw("COALESCE(NULLIF(users.first_name,''), users.username) as salesperson_name"),
                \DB::raw("users.username as salesperson_code")
            );

        return DataTables::of($targets)
            ->addColumn('target_period', function ($row) {
                return \Carbon\Carbon::parse($row->start_date)->format('d M Y')
                    . ' - '
                    . \Carbon\Carbon::parse($row->end_date)->format('d M Y');
            })
            ->addColumn('product_targets', function ($row) {
                $details = ProductSaleTargetDetail::where('product_sale_target_id', $row->id)
                    ->with(['product', 'variation'])
                    ->get();
                $html = '';
                foreach ($details as $detail) {
                    $name = optional($detail->product)->name ?? '-';
                    $sku  = optional($detail->variation)->sub_sku ?? '';
                    $html .= '<span class="label label-info" style="margin:2px;display:inline-block;">'
                           . $name . ($sku ? ' (' . $sku . ')' : '')
                           . ' <b>' . (int) $detail->target_qty . '</b></span> ';
                }
                return $html;
            })
            ->addColumn('status_badge', function ($row) {
                if ($row->status === 'active') {
                    return '<span class="label label-success">Active</span>';
                }
                return '<span class="label label-danger">Inactive</span>';
            })
            ->addColumn('action', function ($row) {
                $edit_url   = action([\App\Http\Controllers\ProductSaleTargetController::class, 'edit'], [$row->id]);
                $delete_url = action([\App\Http\Controllers\ProductSaleTargetController::class, 'destroy'], [$row->id]);
                return '<button data-href="' . $edit_url . '" class="btn btn-xs btn-primary btn-modal-sale-target"
                            data-container=".sale_target_modal">
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <button data-href="' . $delete_url . '" class="btn btn-xs btn-danger delete-sale-target">
                            <i class="fa fa-trash"></i> Delete
                        </button>';
            })
            ->rawColumns(['product_targets', 'status_badge', 'action'])
            ->make(true);
    }

    /**
     * AJAX: Generate the assignment grid based on selected users, products, period.
     */
    public function generateGrid(Request $request)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id  = $request->session()->get('user.business_id');
        $user_ids     = $request->input('user_ids', []);
        $variation_ids = $request->input('variation_ids', []);
        $start_date   = $request->input('start_date');
        $end_date     = $request->input('end_date');

        if (empty($user_ids) || empty($variation_ids) || empty($start_date) || empty($end_date)) {
            return response()->json(['success' => false, 'msg' => 'Please select period, sales and products.']);
        }

        $users = User::where('business_id', $business_id)
            ->whereIn('id', $user_ids)
            ->select('id', 'first_name', 'last_name', 'username')
            ->get();

        $variations = Variation::whereIn('variations.id', $variation_ids)
            ->join('products', 'products.id', '=', 'variations.product_id')
            ->where('products.business_id', $business_id)
            ->select('variations.id', 'variations.sub_sku', 'products.name as product_name')
            ->get();

        // Load existing active (non-deleted) targets for pre-fill only
        $existing = [];
        $existing_targets = ProductSaleTarget::where('business_id', $business_id)
            ->where('start_date', $start_date)
            ->where('end_date', $end_date)
            ->whereIn('user_id', $user_ids)
            ->whereNull('deleted_at')
            ->with('details')
            ->get();

        foreach ($existing_targets as $target) {
            foreach ($target->details as $detail) {
                $existing[$target->user_id][$detail->variation_id] = $detail->target_qty;
            }
        }

        $html = view('product_sale_target.partials.grid', compact(
            'users', 'variations', 'existing', 'start_date', 'end_date'
        ))->render();

        return response()->json(['success' => true, 'html' => $html]);
    }

    /**
     * AJAX: Save targets from the grid.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id  = $request->session()->get('user.business_id');
            $created_by   = $request->session()->get('user.id');
            $start_date   = $request->input('start_date');
            $end_date     = $request->input('end_date');
            $targets_data = $request->input('targets', []);
            $user_ids     = array_keys($targets_data);

            // ── Duplicate check: find salespersons who already have an active target for this period ──
            $conflicts = ProductSaleTarget::where('product_sale_targets.business_id', $business_id)
                ->where('product_sale_targets.start_date', $start_date)
                ->where('product_sale_targets.end_date', $end_date)
                ->whereIn('product_sale_targets.user_id', $user_ids)
                ->whereNull('product_sale_targets.deleted_at')
                ->join('users', 'users.id', '=', 'product_sale_targets.user_id')
                ->select('product_sale_targets.user_id',
                         \DB::raw("COALESCE(NULLIF(users.first_name,''), users.username) as salesperson_name"))
                ->get();

            if ($conflicts->isNotEmpty()) {
                $names = $conflicts->pluck('salesperson_name')->implode(', ');
                return response()->json([
                    'success' => false,
                    'msg'     => "Cannot save: [ {$names} ] already have an active sale target for this period. Please delete their existing target first.",
                ]);
            }

            // ── Validate: every salesperson must have at least one qty > 0 ──
            foreach ($targets_data as $user_id => $variations) {
                $hasQty = collect($variations)->filter(fn($qty) => (float) $qty > 0)->isNotEmpty();
                if (! $hasQty) {
                    return response()->json([
                        'success' => false,
                        'msg'     => 'Please fill in at least one product quantity (> 0) for each salesperson before saving.',
                    ]);
                }
            }

            foreach ($targets_data as $user_id => $variations) {
                // Always create a brand new record — deleted records stay as history only
                $target = ProductSaleTarget::create([
                    'business_id' => $business_id,
                    'user_id'     => $user_id,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                    'status'      => 'active',
                    'created_by'  => $created_by,
                ]);

                foreach ($variations as $variation_id => $qty) {
                    $qty = (float) $qty;
                    if ($qty <= 0) {
                        continue;
                    }

                    $variation = Variation::find($variation_id);
                    if (! $variation) {
                        continue;
                    }

                    ProductSaleTargetDetail::updateOrCreate(
                        [
                            'product_sale_target_id' => $target->id,
                            'variation_id'           => $variation_id,
                        ],
                        [
                            'product_id' => $variation->product_id,
                            'target_qty' => $qty,
                        ]
                    );
                }
            }

            $output = ['success' => true, 'msg' => 'Sale targets saved successfully.'];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * AJAX: Show edit modal for a specific target.
     */
    public function edit($id)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $target = ProductSaleTarget::where('business_id', $business_id)
            ->with(['details.product', 'details.variation', 'user'])
            ->findOrFail($id);

        return view('product_sale_target.partials.edit_modal', compact('target'));
    }

    /**
     * AJAX: Update a specific target's details.
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $target = ProductSaleTarget::where('business_id', $business_id)->findOrFail($id);

            // Update period if provided
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $new_start = $request->input('start_date');
                $new_end   = $request->input('end_date');

                // Check if another active target already exists for the same salesperson + period (exclude current record)
                $conflict = ProductSaleTarget::where('business_id', $business_id)
                    ->where('user_id', $target->user_id)
                    ->where('start_date', $new_start)
                    ->where('end_date', $new_end)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $id)
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'msg'     => 'Cannot update: this salesperson already has an active sale target for the selected period.',
                    ]);
                }

                $target->update([
                    'start_date' => $new_start,
                    'end_date'   => $new_end,
                ]);
            }

            $details = $request->input('details', []);
            foreach ($details as $detail_id => $qty) {
                ProductSaleTargetDetail::where('product_sale_target_id', $target->id)
                    ->where('id', $detail_id)
                    ->update(['target_qty' => (float) $qty]);
            }

            // Handle new products added in modal
            $new_variations = $request->input('new_variation_ids', []);
            foreach ($new_variations as $variation_id) {
                $qty = (float) ($request->input('new_qty')[$variation_id] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $variation = Variation::find($variation_id);
                if (! $variation) {
                    continue;
                }
                ProductSaleTargetDetail::updateOrCreate(
                    ['product_sale_target_id' => $target->id, 'variation_id' => $variation_id],
                    ['product_id' => $variation->product_id, 'target_qty' => $qty]
                );
            }

            $output = ['success' => true, 'msg' => 'Target updated successfully.'];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * AJAX: Delete a target detail row.
     */
    public function destroyDetail(Request $request, $id)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $detail = ProductSaleTargetDetail::whereHas('productSaleTarget', function ($q) use ($business_id) {
                $q->where('business_id', $business_id);
            })->findOrFail($id);
            $detail->delete();

            $output = ['success' => true, 'msg' => 'Product removed.'];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * AJAX: Soft-delete a full target (sets deleted_at and deleted_by).
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $target = ProductSaleTarget::where('business_id', $business_id)
                ->whereNull('deleted_at')
                ->findOrFail($id);

            $target->update([
                'deleted_at' => \Carbon\Carbon::now(),
                'deleted_by' => auth()->id(),
            ]);

            $output = ['success' => true, 'msg' => 'Target deleted successfully.'];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * AJAX: Search products/variations by keyword.
     */
    public function searchProducts(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $keyword     = $request->input('q', '');

        $query = Variation::join('products', 'products.id', '=', 'variations.product_id')
            ->where('products.business_id', $business_id)
            ->where('products.type', 'single')
            ->where(function ($q) use ($keyword) {
                $q->where('products.name', 'like', '%' . $keyword . '%')
                  ->orWhere('variations.sub_sku', 'like', '%' . $keyword . '%');
            });

        $variations = $query->select(
                'variations.id',
                'variations.sub_sku',
                \DB::raw("CONCAT(products.name, ' (', variations.sub_sku, ')') as text"),
                'products.name as product_name'
            )
            ->limit(20)
            ->get();

        return response()->json(['results' => $variations]);
    }

    /**
     * Parse a date value from Excel — handles:
     *  - Excel date serial (float/int)
     *  - String formats: YYYY-MM-DD, DD-MM-YY, DD-MM-YYYY, DD/MM/YYYY, MM/DD/YYYY
     */
    private function parseExcelDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel stores dates as numeric serials
        if (is_numeric($value)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        $value = trim($value);

        // Try common string formats
        $formats = ['Y-m-d', 'd-m-Y', 'd-m-y', 'd/m/Y', 'd/m/y', 'm/d/Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value);
            if ($dt && $dt->format($fmt) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        // Last resort: Carbon parse
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Show import modal.
     */
    public function importModal()
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        return view('product_sale_target.partials.import_modal');
    }

    /**
     * Download Excel template.
     */
    public function downloadTemplate()
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sale Target Template');

        // ── Expected columns ──────────────────────────────────
        $headers = [
            'A1' => 'salesperson_username',
            'B1' => 'start_date',
            'C1' => 'end_date',
            'D1' => 'product_sku',
            'E1' => 'target_qty',
        ];

        // Header row styling
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF2E75B6']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                              'color' => ['argb' => 'FFB8CCE4']]],
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // ── Sample data rows ──────────────────────────────────
        $samples = [
            2 => ['john_doe',  '2026-04-01', '2026-04-30', 'SKU-001', 100],
            3 => ['john_doe',  '2026-04-01', '2026-04-30', 'SKU-002', 50],
            4 => ['jane_smith','2026-04-01', '2026-04-30', 'SKU-001', 80],
        ];

        $sampleStyle = [
            'font' => ['italic' => true, 'color' => ['argb' => 'FF888888']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'startColor' => ['argb' => 'FFF2F2F2']],
        ];

        foreach ($samples as $row => $values) {
            $sheet->setCellValue('A' . $row, $values[0]);
            $sheet->setCellValue('B' . $row, $values[1]);
            $sheet->setCellValue('C' . $row, $values[2]);
            $sheet->setCellValue('D' . $row, $values[3]);
            $sheet->setCellValue('E' . $row, $values[4]);
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($sampleStyle);
        }

        // Column widths
        foreach (['A' => 25, 'B' => 15, 'C' => 15, 'D' => 20, 'E' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Freeze header row so it stays visible while scrolling
        $sheet->freezePane('A2');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $filename = 'sale_target_template.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    /**
     * Import sale targets from uploaded Excel file.
     */
    public function import(Request $request)
    {
        if (! auth()->user()->can('product_sale_target.access')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls']);

        $business_id = $request->session()->get('user.business_id');
        $created_by  = auth()->id();

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('import_file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            // Use raw values (false for formatValues) so Excel date serials come as floats
            $rows        = $sheet->toArray(null, true, false, true);

            // ── Validate headers (row 1) ───────────────────────
            $expectedHeaders = ['A' => 'salesperson_username', 'B' => 'start_date',
                                 'C' => 'end_date', 'D' => 'product_sku', 'E' => 'target_qty'];

            $headerRow = array_map('trim', $rows[1] ?? []);
            foreach ($expectedHeaders as $col => $expected) {
                $actual = strtolower(trim($headerRow[$col] ?? ''));
                if ($actual !== $expected) {
                    return response()->json([
                        'success' => false,
                        'msg'     => "Invalid template format. Column {$col} must be \"{$expected}\" but found \"{$actual}\". Please use the official template.",
                    ]);
                }
            }

            // ── Build lookup maps ──────────────────────────────
            $userMap = User::where('business_id', $business_id)
                ->get()
                ->keyBy('username');

            $variationMap = Variation::join('products', 'products.id', '=', 'variations.product_id')
                ->where('products.business_id', $business_id)
                ->select('variations.id', 'variations.sub_sku', 'variations.product_id')
                ->get()
                ->keyBy('sub_sku');

            // ── Process data rows (skip row 1 header) ─────────
            $errors         = [];
            $imported       = 0;
            // Track targets created in this import: "userId|start|end" => target (or null = blocked)
            $sessionTargets = [];

            foreach ($rows as $rowNum => $row) {
                if ($rowNum == 1) {
                    continue; // skip header
                }

                $username = trim($row['A'] ?? '');
                $sku      = trim($row['D'] ?? '');
                $qty      = trim($row['E'] ?? '');

                // Skip completely empty rows
                if ($username === '' && $sku === '' && $qty === '') {
                    continue;
                }

                // Validate fields
                if (! isset($userMap[$username])) {
                    $errors[] = "Row {$rowNum}: Salesperson username \"{$username}\" not found.";
                    continue;
                }
                $parsedStart = $this->parseExcelDate($row['B']);
                $parsedEnd   = $this->parseExcelDate($row['C']);
                if (! $parsedStart) {
                    $errors[] = "Row {$rowNum}: Invalid start_date. Use YYYY-MM-DD format.";
                    continue;
                }
                if (! $parsedEnd) {
                    $errors[] = "Row {$rowNum}: Invalid end_date. Use YYYY-MM-DD format.";
                    continue;
                }
                $start_date = $parsedStart;
                $end_date   = $parsedEnd;

                if (! isset($variationMap[$sku])) {
                    $errors[] = "Row {$rowNum}: Product SKU \"{$sku}\" not found.";
                    continue;
                }
                if (! is_numeric($qty) || (float) $qty < 0) {
                    $errors[] = "Row {$rowNum}: target_qty \"{$qty}\" must be a number >= 0.";
                    continue;
                }

                $user      = $userMap[$username];
                $variation = $variationMap[$sku];

                // ── Group key: one target per salesperson + period ──────
                $groupKey = $user->id . '|' . $start_date . '|' . $end_date;

                if (array_key_exists($groupKey, $sessionTargets)) {
                    // Already handled this group in this import session
                    $target = $sessionTargets[$groupKey];
                    if ($target === null) {
                        continue; // blocked group — pre-existing active target
                    }
                } else {
                    // First time seeing this salesperson+period — check DB for pre-existing active target
                    $activeTarget = ProductSaleTarget::where('business_id', $business_id)
                        ->where('user_id', $user->id)
                        ->where('start_date', $start_date)
                        ->where('end_date', $end_date)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($activeTarget) {
                        $errors[] = "Row {$rowNum}: Salesperson \"{$username}\" already has an active target for {$start_date} ~ {$end_date}. Delete the existing target first.";
                        $sessionTargets[$groupKey] = null; // mark group blocked
                        continue;
                    }

                    // Create a brand new target — deleted records stay as history only
                    $target = ProductSaleTarget::create([
                        'business_id' => $business_id,
                        'user_id'     => $user->id,
                        'start_date'  => $start_date,
                        'end_date'    => $end_date,
                        'status'      => 'active',
                        'created_by'  => $created_by,
                    ]);
                    $sessionTargets[$groupKey] = $target;
                }

                ProductSaleTargetDetail::updateOrCreate(
                    [
                        'product_sale_target_id' => $target->id,
                        'variation_id'           => $variation->id,
                    ],
                    [
                        'product_id' => $variation->product_id,
                        'target_qty' => (float) $qty,
                    ]
                );

                $imported++;
            }

            if (! empty($errors)) {
                return response()->json([
                    'success' => false,
                    'msg'     => 'Import completed with errors.',
                    'errors'  => $errors,
                    'imported'=> $imported,
                ]);
            }

            return response()->json([
                'success'  => true,
                'msg'      => "{$imported} target row(s) imported successfully.",
                'imported' => $imported,
            ]);

        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return response()->json(['success' => false, 'msg' => 'Failed to read file. Please use the official template.']);
        }
    }
}
