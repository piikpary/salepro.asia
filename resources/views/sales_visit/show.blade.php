<style>
  .photo-container { overflow-x: auto; white-space: nowrap; }
  .photo-container img { display: inline-block; vertical-align: top; }
  #map-show { height: 300px; width: 100%; }

  /* Product group tables */
  .visit-group { margin-bottom: 22px; }
  .visit-group-title { font-size: 15px; font-weight: 700; margin-bottom: 6px; color: #333; }

  .visit-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .visit-table th {
    background-color: #2DCE89;
    color: #fff;
    padding: 7px 10px;
    font-size: 13px;
    text-align: left;
  }
  .visit-table td {
    padding: 6px 10px;
    font-size: 13px;
    border-bottom: 1px solid #e0e0e0;
    text-align: left;
    vertical-align: middle;
  }
  .visit-table tr.own-row td      { background: #eafaf1; }
  .visit-table tr.comp-row td     { background: #f8f9fa; }
  .visit-table tr.unmapped-row td { background: #f8f9fa; }
  .visit-table tr.subtotal-row td {
    background: #D2D6DE;
    font-weight: 600;
    text-align: left;
  }
  /* Subtotal label cell right-aligned */
  .visit-table tr.subtotal-row td.subtotal-label { text-align: right; }

  /* Badges */
  .badge-own   { background:#3c8dbc; color:#fff; border-radius:4px; padding:1px 6px; font-size:11px; margin-left:5px; }
  .badge-other { background:#6c757d; color:#fff; border-radius:4px; padding:1px 6px; font-size:11px; margin-left:5px; }

  /* Win / Lose */
  .text-win  { color: #27ae60; font-weight:600; }
  .text-lose { color: #e74c3c; font-weight:600; }
  .text-tie  { color: #888;    font-weight:600; }

  /* Grand total bar — BOTTOM */
  .grand-total-bar {
    background: #f4f6f9;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px 20px;
    margin: 4px 0 18px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: nowrap;
    width: 100%;
    box-sizing: border-box;
  }
  .grand-total-bar .gt-item { font-size: 13px; white-space: nowrap; }
  .grand-total-bar .gt-item span { font-weight: 700; }
</style>

<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <h4 class="modal-title" id="modalTitle">
        @lang('Visit Details') (<b>Visit No:</b> {{ $visit->visit_no }})
      </h4>
    </div>

    <div class="modal-body">

      {{-- ── Header info ──────────────────────────────────── --}}
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right">
            <b>@lang('messages.date'):</b>
            {{ date('d-m-Y H:i:s', strtotime($visit->transaction_date)) }}
          </p>
        </div>
      </div>
      <div class="row">
        <div class="col-xs-4"><b>Visit ID:</b> #{{ $visit->visit_no }}</div>
        <div class="col-xs-4"><b>Customer Name:</b> {{ $visit->contact->name }}</div>
        <div class="col-xs-4"><b>User:</b> {{ $visit->sales_person->username }}</div>
      </div>
      <div style="height:5px;"></div>
      <div class="row">
        <div class="col-xs-4"><b>Checkin Distance:</b> {{ $visit->checkin_distance }}</div>
        <div class="col-xs-4"><b>Address:</b> {{ $visit->contact->contactMap->address ?? 'N/A' }}</div>
        <div class="col-xs-4"><b>Status:</b> {{ $visit->visit_status }}</div>
      </div>
      <div style="height:5px;"></div>
      <div class="row">
        <div class="col-xs-8"></div>
        <div class="col-xs-4"><b>Noted:</b> {{ $visit->transaction_note }}</div>
      </div>

      <hr style="margin:12px 0;">

      {{-- ── Pre-compute grand totals (no HTML output here) ── --}}
      @php
        $grandOwnQty   = 0;
        $grandOtherQty = 0;
        foreach ($groups as $g) {
          $grandOwnQty += (float) $g['own_line']->quantity;
          foreach ($g['competitors'] as $c) {
            $grandOtherQty += (float) $c->quantity;
          }
        }
        foreach ($unmapped as $u) {
          $grandOtherQty += (float) $u->quantity;
        }
        $grandTotal    = $grandOwnQty + $grandOtherQty;
        $grandOwnPct   = $grandTotal > 0 ? round($grandOwnQty   / $grandTotal * 100, 2) : 0;
        $grandOtherPct = $grandTotal > 0 ? round($grandOtherQty / $grandTotal * 100, 2) : 0;
        if ($grandOwnPct == 50)      { $grandOverall = 'TIE';  $grandColor = '#f39c12'; }
        elseif ($grandOwnPct > 50)   { $grandOverall = 'WIN';  $grandColor = '#27ae60'; }
        else                         { $grandOverall = 'LOSE'; $grandColor = '#e74c3c'; }
      @endphp

      {{-- ══════════════════════════════════════════════════
           1…N  OWN PRODUCT GROUPS
      ══════════════════════════════════════════════════ --}}
      @foreach ($groups as $groupIndex => $group)
        @php
          $ownLine  = $group['own_line'];
          $ownName  = $ownLine->product ? $ownLine->product->name : 'Product not found';
          $ownQty   = (float) $ownLine->quantity;

          $groupTotal = $ownQty;
          foreach ($group['competitors'] as $c) {
            $groupTotal += (float) $c->quantity;
          }

          $ownSharePct = $groupTotal > 0 ? round($ownQty / $groupTotal * 100, 2) : 0;
          if ($ownSharePct == 50)    { $subtotalColor = '#f39c12'; $subtotalLabel = 'Tie Market (Own: 50%)'; }
          elseif ($ownSharePct > 50) { $subtotalColor = '#27ae60'; $subtotalLabel = 'Win Market (Own: '  . $ownSharePct . '%)'; }
          else                       { $subtotalColor = '#e74c3c'; $subtotalLabel = 'Lose Market (Own: ' . $ownSharePct . '%)'; }
        @endphp

        <div class="visit-group">
          <div class="visit-group-title">{{ $groupIndex + 1 }}. {{ $ownName }} Analysis</div>
          <table class="visit-table">
            <colgroup>
              <col style="width:4%">
              <col style="width:52%">
              <col style="width:18%">
              <col style="width:26%">
            </colgroup>
            <thead>
              <tr>
                <th>#</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>1-vs-1 Comparison</th>
              </tr>
            </thead>
            <tbody>
              {{-- Own product (Base Target) --}}
              <tr class="own-row">
                <td>1</td>
                <td><strong>{{ $ownName }}</strong><span class="badge-own">Own</span></td>
                <td>{{ number_format($ownQty, 4) }}</td>
                <td class="text-tie">Base Target</td>
              </tr>

              {{-- Linked competitors --}}
              @foreach ($group['competitors'] as $ci => $comp)
                @php
                  $compName = $comp->product ? $comp->product->name : 'Product not found';
                  $compQty  = (float) $comp->quantity;
                  $diff     = $ownQty - $compQty;
                  if ($diff > 0)      $compLabel = '<span class="text-win">Win (+'  . number_format($diff, 4) . ')</span>';
                  elseif ($diff < 0)  $compLabel = '<span class="text-lose">Lose (' . number_format($diff, 4) . ')</span>';
                  else                $compLabel = '<span class="text-tie">Tie (0.0000)</span>';
                @endphp
                <tr class="comp-row">
                  <td>{{ $ci + 2 }}</td>
                  <td><span style="color:#aaa; margin-right:4px;">&#x2514;</span> {{ $compName }}</td>
                  <td>{{ number_format($compQty, 4) }}</td>
                  <td>{!! $compLabel !!}</td>
                </tr>
              @endforeach

              {{-- Subtotal --}}
              <tr class="subtotal-row">
                <td colspan="2" class="subtotal-label">Sub total (Market Size):</td>
                <td><strong style="color:{{ $subtotalColor }};">{{ number_format($groupTotal, 4) }}</strong></td>
                <td><strong style="color:{{ $subtotalColor }};">{{ $subtotalLabel }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      @endforeach

      {{-- ══════════════════════════════════════════════════
           OTHER / UNMAPPED PRODUCTS
      ══════════════════════════════════════════════════ --}}
      @if (count($unmapped) > 0)
        @php
          $unmappedTotal = 0;
          foreach ($unmapped as $u) { $unmappedTotal += (float) $u->quantity; }
          $sectionNum = count($groups) + 1;
        @endphp
        <div class="visit-group">
          <div class="visit-group-title">{{ $sectionNum }}. Other / Unmapped Products</div>
          <table class="visit-table">
            <colgroup>
              <col style="width:4%">
              <col style="width:78%">
              <col style="width:18%">
            </colgroup>
            <thead>
              <tr>
                <th>#</th>
                <th>Product</th>
                <th>Quantity</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($unmapped as $ui => $uLine)
                <tr class="unmapped-row">
                  <td>{{ $ui + 1 }}</td>
                  <td>
                    {{ $uLine->product ? $uLine->product->name : 'Product not found' }}
                    <span class="badge-other">Other</span>
                  </td>
                  <td>{{ number_format((float) $uLine->quantity, 4) }}</td>
                </tr>
              @endforeach
              <tr class="subtotal-row">
                <td colspan="2" class="subtotal-label">Sub total:</td>
                <td><strong style="color:#e74c3c;">{{ number_format($unmappedTotal, 4) }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      @endif

      {{-- ══════════════════════════════════════════════════
           VISIT GRAND TOTAL BAR  ← BOTTOM (after all tables)
      ══════════════════════════════════════════════════ --}}
      <div class="grand-total-bar">
        <div class="gt-item">
          <i class="fa fa-info-circle text-primary"></i>
          &nbsp;<b>Visit Grand Total:</b>
        </div>
        <div class="gt-item">
          Total Own:
          <span style="color:#27ae60;">{{ number_format($grandOwnQty, 4) }}</span>
          ({{ $grandOwnPct }}%)
        </div>
        <div class="gt-item">
          Total Other:
          <span>{{ number_format($grandOtherQty, 4) }}</span>
          ({{ $grandOtherPct }}%)
        </div>
        <div class="gt-item">
          Market Size: <span>{{ number_format($grandTotal, 4) }}</span>
        </div>
        <div class="gt-item">
          <strong style="color:{{ $grandColor }}; font-size:14px;">
            OVERALL {{ $grandOverall }}
          </strong>
        </div>
      </div>

      {{-- ── Photos & Map ──────────────────────────────────── --}}
      <div class="row" style="margin-top:6px;">
        <div class="col-xs-6">
          <h5>Photos:</h5>
          <div class="photo-container">
            @foreach ($images as $image)
              <img src="{{ asset($image) }}" style="height:300px; margin-right:10px;">
            @endforeach
          </div>
        </div>
        <div class="col-xs-6">
          <h5>Map:</h5>
          <div id="map-show"></div>
        </div>
      </div>

    </div>{{-- /modal-body --}}

    <div class="modal-footer">
      <button type="button" class="btn btn-default no-print" data-dismiss="modal">
        @lang('messages.close')
      </button>
    </div>
  </div>
</div>

<script type="text/javascript">
  function initMapData() {
    var saleLatLong = "{{ $visit->sale_latlong ?? null }}".split(',');
    var saleCoords = { lat: parseFloat(saleLatLong[0]), lng: parseFloat(saleLatLong[1]) };
    return new google.maps.Map(document.getElementById('map-show'), {
      zoom: 17, center: saleCoords,
      disableDefaultUI: true, gestureHandling: 'none', zoomControl: false
    });
  }

  function setupMap() {
    var map = initMapData();
    var saleLatLong    = "{{ $visit->sale_latlong ?? null }}".split(',');
    var contactLatLong = "{{ $visit->contact->contactMap->points ?? null }}".split(',');
    var saleCoords     = { lat: parseFloat(saleLatLong[0]), lng: parseFloat(saleLatLong[1]) };
    var contactCoords  = null;
    if (contactLatLong && contactLatLong[0] !== '') {
      contactCoords = { lat: parseFloat(contactLatLong[0]), lng: parseFloat(contactLatLong[1]) };
    }
    var contactIcon = {
      url: '/public/images/Icon.svg',
      scaledSize: new google.maps.Size(45, 45),
      origin: new google.maps.Point(0, 0),
      anchor: new google.maps.Point(15, 15)
    };
    var saleMarker = new google.maps.Marker({
      position: saleCoords, map: map,
      title: "Sale Rep: {{ $visit->sales_person->username }}"
    });
    var saleIW = new google.maps.InfoWindow({
      content: '<div style="font-size:14px;padding:5px;"><b>Sale Rep: {{ $visit->sales_person->username }}</b></div>'
    });
    saleIW.open(map, saleMarker);

    if (contactCoords) {
      var contactMarker = new google.maps.Marker({
        position: contactCoords, map: map,
        title: "Outlet: {{ $visit->contact->name }}", icon: contactIcon
      });
      var contactIW = new google.maps.InfoWindow({
        content: '<div style="font-size:14px;padding:5px;"><b>Outlet: {{ $visit->contact->name }}</b></div>'
      });
      contactIW.open(map, contactMarker);
    }

    google.maps.event.addListener(map, 'idle', function () {
      document.querySelectorAll('.gm-ui-hover-effect').forEach(function (b) { b.style.display = 'none'; });
    });

    if (contactCoords && (saleCoords.lat !== contactCoords.lat || saleCoords.lng !== contactCoords.lng)) {
      var bounds = new google.maps.LatLngBounds();
      bounds.extend(saleCoords);
      bounds.extend(contactCoords);
      map.fitBounds(bounds);
      google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
        if (map.getZoom() > 17) map.setZoom(17);
      });
    }
    google.maps.event.trigger(map, 'resize');
  }

  $(document).ready(function () {
    $('#modalTitle').closest('.modal').on('shown.bs.modal', function () {
      if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        setupMap();
      } else {
        setTimeout(function () { if (typeof google !== 'undefined') setupMap(); }, 500);
      }
    });
  });
</script>
