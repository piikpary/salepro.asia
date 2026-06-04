@forelse($products as $product)
    <div class="col-md-3 col-xs-4 product_list no-print">
        <div class="product_box" data-variation_id="{{$product->id}}" title="{{$product->name}} @if($product->type == 'variable')- {{$product->variation}} @endif {{ '(' . $product->sub_sku . ')'}} @if(!empty($show_prices)) @lang('lang_v1.default') - @format_currency($product->selling_price) @foreach($product->group_prices as $group_price) @if(array_key_exists($group_price->price_group_id, $allowed_group_prices)) {{$allowed_group_prices[$group_price->price_group_id]}} - @format_currency($group_price->price_inc_tax) @endif @endforeach @endif">

        @php
            $s3BaseUrl = 'https://piik-data.sgp1.digitaloceanspaces.com/piik-data/salepro/public/image/';

            if (count($product->media) > 0) {
                $imageUrl = $product->media->first()->display_url;
            } elseif (!empty($product->product_image)) {
                $imageUrl = $s3BaseUrl . $business_id . '/' . $product->product_image;
            } else {
                $imageUrl = asset('/img/default.png');
            }
        @endphp

        <div class="image-container" 
            style="background-image: url('{{ $imageUrl }}');
            background-repeat: no-repeat; background-position: center;
            background-size: contain;">
        </div>

        <div class="text_div">
            <small class="text text-muted">{{$product->name}} 
            @if($product->type == 'variable')
                - {{$product->variation}}
            @endif
            </small>

            <small class="text-muted">
                ({{$product->sub_sku}})
            </small>
        </div>
            
        </div>
    </div>
@empty
    <input type="hidden" id="no_products_found">
    <div class="col-md-12">
        <h4 class="text-center">
            @lang('lang_v1.no_products_to_display')
        </h4>
    </div>
@endforelse