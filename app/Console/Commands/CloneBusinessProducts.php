<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CloneBusinessProducts extends Command
{
    protected $signature = 'business:clone-products 
                            {from_business_id : Source business ID}
                            {to_business_id : Target business ID}
                            {--from_location_id= : Source location ID for stock clone}
                            {--to_location_id= : Target location ID for stock clone}
                            {--skip-stock : Skip variation_location_details}
                            {--skip-images : Skip media/images}';

    protected $description = 'Clone product master data from one business to another business safely';

    public function handle()
    {
        $fromBusinessId = (int) $this->argument('from_business_id');
        $toBusinessId = (int) $this->argument('to_business_id');

        $fromLocationId = $this->option('from_location_id') ? (int) $this->option('from_location_id') : null;
        $toLocationId = $this->option('to_location_id') ? (int) $this->option('to_location_id') : null;

        $skipStock = (bool) $this->option('skip-stock');
        $skipImages = (bool) $this->option('skip-images');

        if ($fromBusinessId === $toBusinessId) {
            $this->error('Source business and target business cannot be the same.');
            return Command::FAILURE;
        }

        $fromBusiness = DB::table('business')->where('id', $fromBusinessId)->first()
            ?: DB::table('businesses')->where('id', $fromBusinessId)->first();

        $toBusiness = DB::table('business')->where('id', $toBusinessId)->first()
            ?: DB::table('businesses')->where('id', $toBusinessId)->first();

        if (!$fromBusiness || !$toBusiness) {
            $this->error('Source business or target business not found.');
            return Command::FAILURE;
        }

        if (!$skipStock && Schema::hasTable('variation_location_details')) {
            if (!$fromLocationId || !$toLocationId) {
                $this->warn('Stock clone needs --from_location_id and --to_location_id.');
                $this->warn('Stock will be skipped.');
                $skipStock = true;
            }
        }

        DB::beginTransaction();

        try {
            $this->info('Starting product clone...');
            $this->info("From business: {$fromBusinessId}");
            $this->info("To business: {$toBusinessId}");

            /**
             * Mapping arrays
             */
            $brandMap = [];
            $categoryMap = [];
            $unitMap = [];
            $taxRateMap = [];
            $productMap = [];
            $productVariationMap = [];
            $variationMap = [];

            /**
             * LEVEL 1: master/reference tables
             */
            $this->info('Level 1: Cloning brands...');
            $brandMap = $this->cloneSimpleBusinessTable('brands', $fromBusinessId, $toBusinessId);

            $this->info('Level 1: Cloning categories...');
            $categoryMap = $this->cloneSimpleBusinessTable('categories', $fromBusinessId, $toBusinessId);

            $this->info('Level 1: Cloning units...');
            $unitMap = $this->cloneSimpleBusinessTable('units', $fromBusinessId, $toBusinessId);

            $this->info('Level 1: Cloning tax_rates...');
            $taxRateMap = $this->cloneSimpleBusinessTable('tax_rates', $fromBusinessId, $toBusinessId);

            /**
             * LEVEL 2: products
             */
            $this->info('Level 2: Cloning products...');

            $products = DB::table('products')
                ->where('business_id', $fromBusinessId)
                ->orderBy('id')
                ->get();

            foreach ($products as $product) {
                $oldProductId = $product->id;

                $newProduct = (array) $product;
                unset($newProduct['id']);

                $newProduct['business_id'] = $toBusinessId;

                if (isset($newProduct['brand_id']) && isset($brandMap[$product->brand_id])) {
                    $newProduct['brand_id'] = $brandMap[$product->brand_id];
                }

                if (isset($newProduct['category_id']) && isset($categoryMap[$product->category_id])) {
                    $newProduct['category_id'] = $categoryMap[$product->category_id];
                }

                if (isset($newProduct['sub_category_id']) && isset($categoryMap[$product->sub_category_id])) {
                    $newProduct['sub_category_id'] = $categoryMap[$product->sub_category_id];
                }

                if (isset($newProduct['unit_id']) && isset($unitMap[$product->unit_id])) {
                    $newProduct['unit_id'] = $unitMap[$product->unit_id];
                }

                if (isset($newProduct['secondary_unit_id']) && isset($unitMap[$product->secondary_unit_id])) {
                    $newProduct['secondary_unit_id'] = $unitMap[$product->secondary_unit_id];
                }

                if (isset($newProduct['tax']) && isset($taxRateMap[$product->tax])) {
                    $newProduct['tax'] = $taxRateMap[$product->tax];
                }

                if (array_key_exists('created_at', $newProduct)) {
                    $newProduct['created_at'] = now();
                }

                if (array_key_exists('updated_at', $newProduct)) {
                    $newProduct['updated_at'] = now();
                }

                /**
                 * Avoid duplicate SKU issue.
                 * SalePro product sku can be unique sometimes.
                 */
                if (isset($newProduct['sku']) && !empty($newProduct['sku'])) {
                    $newProduct['sku'] = $newProduct['sku'] . '-B' . $toBusinessId;
                }

                $newProductId = DB::table('products')->insertGetId($newProduct);
                $productMap[$oldProductId] = $newProductId;
            }

            $this->info('Products cloned: ' . count($productMap));

            /**
             * LEVEL 2: product_variations
             */
            $this->info('Level 2: Cloning product_variations...');

            if (Schema::hasTable('product_variations')) {
                $productVariations = DB::table('product_variations')
                    ->whereIn('product_id', array_keys($productMap))
                    ->orderBy('id')
                    ->get();

                foreach ($productVariations as $productVariation) {
                    $oldProductVariationId = $productVariation->id;

                    $newProductVariation = (array) $productVariation;
                    unset($newProductVariation['id']);

                    $newProductVariation['product_id'] = $productMap[$productVariation->product_id];

                    if (array_key_exists('created_at', $newProductVariation)) {
                        $newProductVariation['created_at'] = now();
                    }

                    if (array_key_exists('updated_at', $newProductVariation)) {
                        $newProductVariation['updated_at'] = now();
                    }

                    $newProductVariationId = DB::table('product_variations')->insertGetId($newProductVariation);
                    $productVariationMap[$oldProductVariationId] = $newProductVariationId;
                }
            }

            $this->info('Product variations cloned: ' . count($productVariationMap));

            /**
             * LEVEL 2: variations
             */
            $this->info('Level 2: Cloning variations...');

            if (Schema::hasTable('variations')) {
                $variations = DB::table('variations')
                    ->whereIn('product_id', array_keys($productMap))
                    ->orderBy('id')
                    ->get();

                foreach ($variations as $variation) {
                    $oldVariationId = $variation->id;

                    $newVariation = (array) $variation;
                    unset($newVariation['id']);

                    $newVariation['product_id'] = $productMap[$variation->product_id];

                    if (
                        isset($variation->product_variation_id)
                        && isset($productVariationMap[$variation->product_variation_id])
                    ) {
                        $newVariation['product_variation_id'] = $productVariationMap[$variation->product_variation_id];
                    }

                    /**
                     * Avoid duplicate sub_sku issue.
                     */
                    if (isset($newVariation['sub_sku']) && !empty($newVariation['sub_sku'])) {
                        $newVariation['sub_sku'] = $newVariation['sub_sku'] . '-B' . $toBusinessId;
                    }

                    if (array_key_exists('created_at', $newVariation)) {
                        $newVariation['created_at'] = now();
                    }

                    if (array_key_exists('updated_at', $newVariation)) {
                        $newVariation['updated_at'] = now();
                    }

                    $newVariationId = DB::table('variations')->insertGetId($newVariation);
                    $variationMap[$oldVariationId] = $newVariationId;
                }
            }

            $this->info('Variations cloned: ' . count($variationMap));

            /**
             * LEVEL 3: variation_location_details / stock
             */
            if (!$skipStock && Schema::hasTable('variation_location_details')) {
                $this->info('Level 3: Cloning variation_location_details stock...');

                $stockRows = DB::table('variation_location_details')
                    ->whereIn('variation_id', array_keys($variationMap))
                    ->where('location_id', $fromLocationId)
                    ->orderBy('id')
                    ->get();

                $stockCount = 0;

                foreach ($stockRows as $stock) {
                    $newStock = (array) $stock;
                    unset($newStock['id']);

                    $newStock['product_id'] = $productMap[$stock->product_id] ?? $stock->product_id;
                    $newStock['variation_id'] = $variationMap[$stock->variation_id];
                    $newStock['location_id'] = $toLocationId;

                    if (array_key_exists('created_at', $newStock)) {
                        $newStock['created_at'] = now();
                    }

                    if (array_key_exists('updated_at', $newStock)) {
                        $newStock['updated_at'] = now();
                    }

                    DB::table('variation_location_details')->insert($newStock);
                    $stockCount++;
                }

                $this->info('Stock rows cloned: ' . $stockCount);
            } else {
                $this->warn('Stock clone skipped.');
            }

            /**
             * LEVEL 3: product_locations if table exists
             */
            if (Schema::hasTable('product_locations')) {
                $this->info('Level 3: Cloning product_locations...');

                if ($fromLocationId && $toLocationId) {
                    $productLocationRows = DB::table('product_locations')
                        ->whereIn('product_id', array_keys($productMap))
                        ->where('location_id', $fromLocationId)
                        ->get();

                    $productLocationCount = 0;

                    foreach ($productLocationRows as $row) {
                        $newRow = (array) $row;
                        unset($newRow['id']);

                        $newRow['product_id'] = $productMap[$row->product_id];
                        $newRow['location_id'] = $toLocationId;

                        if (array_key_exists('created_at', $newRow)) {
                            $newRow['created_at'] = now();
                        }

                        if (array_key_exists('updated_at', $newRow)) {
                            $newRow['updated_at'] = now();
                        }

                        DB::table('product_locations')->insert($newRow);
                        $productLocationCount++;
                    }

                    $this->info('Product location rows cloned: ' . $productLocationCount);
                } else {
                    $this->warn('product_locations skipped because location IDs were not provided.');
                }
            }

            /**
             * LEVEL 3: media / images
             */
            if (!$skipImages && Schema::hasTable('media')) {
                $this->info('Level 3: Cloning media/images...');

                $mediaCount = 0;

                foreach ($productMap as $oldProductId => $newProductId) {
                    $mediaRows = DB::table('media')
                        ->where('model_type', 'App\\Product')
                        ->where('model_id', $oldProductId)
                        ->get();

                    foreach ($mediaRows as $media) {
                        $newMedia = (array) $media;
                        unset($newMedia['id']);

                        $newMedia['model_id'] = $newProductId;

                        if (array_key_exists('created_at', $newMedia)) {
                            $newMedia['created_at'] = now();
                        }

                        if (array_key_exists('updated_at', $newMedia)) {
                            $newMedia['updated_at'] = now();
                        }

                        DB::table('media')->insert($newMedia);
                        $mediaCount++;
                    }
                }

                $this->info('Media rows cloned: ' . $mediaCount);
            } else {
                $this->warn('Images/media clone skipped.');
            }

            DB::commit();

            $this->info('Clone completed successfully.');
            $this->info('Summary:');
            $this->info('Brands: ' . count($brandMap));
            $this->info('Categories: ' . count($categoryMap));
            $this->info('Units: ' . count($unitMap));
            $this->info('Tax rates: ' . count($taxRateMap));
            $this->info('Products: ' . count($productMap));
            $this->info('Product variations: ' . count($productVariationMap));
            $this->info('Variations: ' . count($variationMap));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Clone failed. Nothing was saved because transaction rollback was applied.');
            $this->error($e->getMessage());
            $this->error($e->getFile() . ':' . $e->getLine());

            return Command::FAILURE;
        }
    }

    private function cloneSimpleBusinessTable(string $table, int $fromBusinessId, int $toBusinessId): array
    {
        $map = [];

        if (!Schema::hasTable($table)) {
            $this->warn("Table {$table} does not exist. Skipped.");
            return $map;
        }

        if (!Schema::hasColumn($table, 'business_id')) {
            $this->warn("Table {$table} has no business_id column. Skipped.");
            return $map;
        }

        $rows = DB::table($table)
            ->where('business_id', $fromBusinessId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $oldId = $row->id;

            $newRow = (array) $row;
            unset($newRow['id']);

            $newRow['business_id'] = $toBusinessId;

            if (array_key_exists('created_at', $newRow)) {
                $newRow['created_at'] = now();
            }

            if (array_key_exists('updated_at', $newRow)) {
                $newRow['updated_at'] = now();
            }

            $newId = DB::table($table)->insertGetId($newRow);
            $map[$oldId] = $newId;
        }

        $this->info("{$table} cloned: " . count($map));

        return $map;
    }
}