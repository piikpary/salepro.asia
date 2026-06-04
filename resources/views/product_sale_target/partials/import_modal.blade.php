<div class="modal-header" style="background:#2e75b6; padding:14px 20px; border-radius:4px 4px 0 0;">
    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
    <h4 class="modal-title" style="color:#fff; font-weight:600;">
        <i class="fa fa-file-excel-o"></i> Import Targets from Excel
    </h4>
</div>

<div class="modal-body" style="padding:24px;">

    {{-- Step 1: Download Template --}}
    <div style="background:#eaf4fb; border:1px solid #b8d4e8; border-radius:6px; padding:16px; margin-bottom:20px;">
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <i class="fa fa-info-circle" style="color:#2e75b6; font-size:20px; margin-top:2px;"></i>
            <div>
                <strong style="color:#1a5276;">Step 1: Download the Template</strong>
                <p style="margin:4px 0 10px; color:#555; font-size:13px;">
                    Please use our standard Excel format to ensure data is imported correctly.
                </p>
                <a href="{{ route('product-sale-targets.download-template') }}"
                   class="btn btn-primary btn-sm">
                    <i class="fa fa-download"></i> Download Template.xlsx
                </a>
            </div>
        </div>
    </div>

    {{-- Template column guide --}}
    <div style="margin-bottom:20px;">
        <p style="font-weight:600; font-size:13px; color:#333; margin-bottom:8px;">
            <i class="fa fa-table"></i> Template Columns:
        </p>
        <table class="table table-bordered table-condensed" style="font-size:12px;">
            <thead style="background:#f4f6f9;">
                <tr>
                    <th>Column</th>
                    <th>Field</th>
                    <th>Example</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>A</td><td><code>salesperson_username</code></td><td>john_doe</td><td>Must match the <strong>username</strong> field (not first name)</td></tr>
                <tr><td>B</td><td><code>start_date</code></td><td>2026-04-01</td><td>Format: YYYY-MM-DD</td></tr>
                <tr><td>C</td><td><code>end_date</code></td><td>2026-04-30</td><td>Format: YYYY-MM-DD</td></tr>
                <tr><td>D</td><td><code>product_sku</code></td><td>SKU-001</td><td>Must match an existing product SKU</td></tr>
                <tr><td>E</td><td><code>target_qty</code></td><td>100</td><td>Number ≥ 0</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Step 2: Upload --}}
    {{-- File input lives OUTSIDE the dropzone to prevent click-bubble loop --}}
    <input type="file" id="import-file-input" accept=".xlsx,.xls" style="display:none;">

    <div>
        <strong style="font-size:13px; color:#333;">Step 2: Upload your filled file</strong>
        <div id="import-dropzone"
             style="margin-top:10px; border:2px dashed #aab7c4; border-radius:8px;
                    padding:30px 20px; text-align:center; cursor:pointer;
                    background:#fafbfc; transition:border-color 0.2s, background 0.2s;">
            <i class="fa fa-cloud-upload" style="font-size:36px; color:#2e75b6; margin-bottom:8px;"></i>
            <p style="margin:0; font-size:14px; color:#555;">
                Drag and drop your Excel file here
            </p>
            <p style="margin:4px 0 0; font-size:12px; color:#aaa;">
                or click to browse from your computer (.xlsx, .xls)
            </p>
        </div>

        {{-- Selected file info --}}
        <div id="import-file-info" style="display:none; margin-top:10px;
             background:#e8f8f0; border:1px solid #a9dfbf; border-radius:6px; padding:10px 14px;">
            <i class="fa fa-file-excel-o" style="color:#27ae60;"></i>
            <span id="import-file-name" style="margin-left:6px; font-weight:600; color:#1e8449;"></span>
            <button type="button" id="import-file-clear"
                    style="float:right; background:none; border:none; color:#e74c3c; cursor:pointer; font-size:16px;">
                <i class="fa fa-times-circle"></i>
            </button>
        </div>

        {{-- Errors --}}
        <div id="import-errors" style="display:none; margin-top:12px;
             background:#fdf2f2; border:1px solid #f5c6c6; border-radius:6px; padding:12px 14px;">
            <strong style="color:#c0392b;"><i class="fa fa-exclamation-triangle"></i> Import Errors:</strong>
            <ul id="import-error-list" style="margin:8px 0 0; padding-left:20px; font-size:13px; color:#922b21;"></ul>
        </div>
    </div>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
    <button type="button" id="btn-confirm-import" class="btn btn-success" disabled>
        <i class="fa fa-check"></i> Confirm Import
    </button>
</div>

<script>
$(document).ready(function () {

    var selectedFile = null;

    // ── Dropzone click → open file picker ─────────────
    $('#import-dropzone').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $('#import-file-input')[0].click();
    });

    // Prevent bubbled clicks on the input from re-triggering dropzone
    $('#import-file-input').on('click', function (e) {
        e.stopPropagation();
    });

    // ── Drag over styling ──────────────────────────────
    $('#import-dropzone').on('dragover', function (e) {
        e.preventDefault();
        $(this).css({ 'border-color': '#2e75b6', 'background': '#eaf4fb' });
    }).on('dragleave', function () {
        $(this).css({ 'border-color': '#aab7c4', 'background': '#fafbfc' });
    }).on('drop', function (e) {
        e.preventDefault();
        $(this).css({ 'border-color': '#aab7c4', 'background': '#fafbfc' });
        var file = e.originalEvent.dataTransfer.files[0];
        if (file) { setFile(file); }
    });

    // ── File input change ──────────────────────────────
    $('#import-file-input').on('change', function () {
        if (this.files[0]) { setFile(this.files[0]); }
    });

    // ── Clear selected file ────────────────────────────
    $('#import-file-clear').on('click', function (e) {
        e.stopPropagation();
        clearFile();
    });

    function setFile(file) {
        var allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'application/vnd.ms-excel'];
        var ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'xlsx' && ext !== 'xls') {
            toastr.error('Only .xlsx or .xls files are accepted.');
            return;
        }
        selectedFile = file;
        $('#import-file-name').text(file.name);
        $('#import-file-info').show();
        $('#import-errors').hide();
        $('#btn-confirm-import').prop('disabled', false);
    }

    function clearFile() {
        selectedFile = null;
        $('#import-file-input').val('');
        $('#import-file-info').hide();
        $('#import-errors').hide();
        $('#btn-confirm-import').prop('disabled', true);
    }

    // ── Confirm Import ─────────────────────────────────
    $('#btn-confirm-import').on('click', function () {
        if (!selectedFile) { return; }

        var formData = new FormData();
        formData.append('import_file', selectedFile);
        formData.append('_token', '{{ csrf_token() }}');

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

        $.ajax({
            url: '{{ route("product-sale-targets.import") }}',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Import');
                if (res.success) {
                    toastr.success(res.msg);
                    $('.sale_target_import_modal').modal('hide');
                    $('#sale_targets_table').DataTable().ajax.reload();
                } else {
                    if (res.errors && res.errors.length) {
                        $('#import-error-list').empty();
                        $.each(res.errors, function (i, err) {
                            $('#import-error-list').append('<li>' + err + '</li>');
                        });
                        $('#import-errors').show();
                        if (res.imported > 0) {
                            toastr.warning(res.imported + ' row(s) imported, but some rows had errors.');
                            $('#sale_targets_table').DataTable().ajax.reload();
                        }
                    } else {
                        toastr.error(res.msg);
                    }
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Import');
                toastr.error('Something went wrong. Please try again.');
            }
        });
    });

});
</script>
