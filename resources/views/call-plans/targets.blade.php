@extends('layouts.app')

@section('content')
<style>
    .target-page {
        padding: 18px;
        background: #f4f7fb;
        min-height: calc(100vh - 80px);
    }

    .target-header {
        background: linear-gradient(135deg, #236bd8, #163f91);
        color: #fff;
        border-radius: 14px;
        padding: 18px 22px;
        margin-bottom: 16px;
        box-shadow: 0 8px 22px rgba(35, 107, 216, .22);
    }

    .target-header h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
    }

    .target-header p {
        margin: 4px 0 0;
        font-size: 13px;
        opacity: .9;
    }

    .target-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e5ebf5;
        box-shadow: 0 6px 18px rgba(30, 41, 59, .08);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .target-card-header {
        padding: 14px 18px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
    }

    .target-card-header h4 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #17233c;
    }

    .target-card-body {
        padding: 18px;
    }

    .target-grid {
        display: grid;
        grid-template-columns: 1.2fr .8fr .8fr .8fr;
        gap: 14px;
        margin-bottom: 16px;
    }

    .target-form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #56647c;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .target-control {
        width: 100%;
        height: 40px;
        border: 1px solid #d9e2ef;
        border-radius: 9px;
        padding: 8px 11px;
        font-size: 14px;
        color: #1f2937;
        background: #fff;
        outline: none;
        transition: .2s;
    }

    .target-control:focus {
        border-color: #2f76df;
        box-shadow: 0 0 0 3px rgba(47, 118, 223, .12);
    }

    .product-box {
        border: 1px solid #e4ebf5;
        border-radius: 12px;
        background: #f8fbff;
        padding: 14px;
    }

    .product-box-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .product-box-title h5 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #24324b;
    }

    .target-row {
        display: grid;
        grid-template-columns: 1fr 220px 46px;
        gap: 10px;
        align-items: end;
        padding: 12px;
        border: 1px solid #e6edf7;
        border-radius: 11px;
        background: #fff;
        margin-bottom: 10px;
    }

    .btn-target {
        border: none;
        border-radius: 9px;
        height: 40px;
        padding: 0 16px;
        font-weight: 700;
        cursor: pointer;
        transition: .2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
    }

    .btn-target-primary {
        background: #236bd8;
        color: #fff;
    }

    .btn-target-primary:hover {
        background: #195cc0;
        color: #fff;
    }

    .btn-target-success {
        background: #19b96f;
        color: #fff;
    }

    .btn-target-success:hover {
        background: #119b5c;
        color: #fff;
    }

    .btn-target-danger {
        background: #ffeef0;
        color: #df3347;
        width: 40px;
        padding: 0;
    }

    .btn-target-danger:hover {
        background: #df3347;
        color: #fff;
    }

    .target-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
    }

    .saved-target {
        border: 1px solid #e6edf7;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        background: #fff;
    }

    .saved-target-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .saved-title {
        font-size: 16px;
        font-weight: 700;
        color: #17233c;
        margin-bottom: 4px;
    }

    .saved-meta {
        font-size: 13px;
        color: #718096;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        background: #eafaf0;
        color: #16a05d;
        font-size: 12px;
        font-weight: 700;
    }

    .target-item-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .target-item {
        background: #f8fbff;
        border: 1px solid #e6edf7;
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .target-item-name {
        font-size: 13px;
        color: #42526e;
    }

    .target-item-value {
        font-size: 13px;
        font-weight: 800;
        color: #236bd8;
    }

    .empty-state {
        text-align: center;
        padding: 35px 15px;
        color: #718096;
    }

    .empty-state-icon {
        font-size: 42px;
        margin-bottom: 8px;
    }

    .alert-target {
        border-radius: 12px;
        padding: 12px 15px;
        margin-bottom: 14px;
        font-size: 14px;
        font-weight: 600;
    }

    .alert-success-target {
        background: #eafaf0;
        color: #087443;
        border: 1px solid #bcebd2;
    }

    .alert-error-target {
        background: #fff0f0;
        color: #b42318;
        border: 1px solid #ffd0d0;
    }

    @media (max-width: 992px) {
        .target-grid {
            grid-template-columns: 1fr 1fr;
        }

        .target-row {
            grid-template-columns: 1fr;
        }

        .target-item-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .target-grid {
            grid-template-columns: 1fr;
        }

        .target-page {
            padding: 10px;
        }
    }
</style>

<div class="target-page">
    <div class="target-header">
        <h3>Sale Target Setting</h3>
        <p>Admin can input product target. AI will use this target to calculate expected today, actual sold, gap missing, and call/visit plan.</p>
    </div>

    @if(session('success'))
        <div class="alert-target alert-success-target">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert-target alert-error-target">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="target-card">
        <div class="target-card-header">
            <h4>🎯 Assign Product Sale Target</h4>
            <a href="{{ route('smart-call-plans.index') }}" class="btn-target btn-target-primary">
                ← Back to Plan
            </a>
        </div>

        <div class="target-card-body">
            <form action="{{ route('smart-call-plans.targets.store') }}" method="POST" id="targetForm">
                @csrf

                <div class="target-grid">
                    <div class="target-form-group">
                        <label>Title</label>
                        <input
                            type="text"
                            name="title"
                            class="target-control"
                            placeholder="Example: April Beverage Target"
                            value="{{ old('title') }}"
                        >
                    </div>

                    <div class="target-form-group">
                        <label>Target Period Start</label>
                        <input
                            type="date"
                            name="period_start"
                            class="target-control"
                            value="{{ old('period_start') }}"
                            required
                        >
                    </div>

                    <div class="target-form-group">
                        <label>Target Period End</label>
                        <input
                            type="date"
                            name="period_end"
                            class="target-control"
                            value="{{ old('period_end') }}"
                            required
                        >
                    </div>

                    <div class="target-form-group">
                        <label>Salesperson</label>
                        <select name="assigned_to" class="target-control">
                            <option value="">All Team / General</option>
                            @foreach($users as $user)
                                @php
                                    $userName = trim(($user->first_name ?? '') . ' ' . ($user->surname ?? ''));
                                    $userName = $userName ?: ($user->username ?? 'User #' . $user->id);
                                @endphp
                                <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                    {{ $userName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="product-box">
                    <div class="product-box-title">
                        <h5>📦 Product Target Rows</h5>
                        <button type="button" class="btn-target btn-target-success" onclick="addTargetRow()">
                            + Add Row
                        </button>
                    </div>

                    <div id="targetRows">
                        <div class="target-row">
                            <div class="target-form-group">
                                <label>Product</label>
                                <select name="items[0][product_id]" class="target-control" required>
                                    <option value="">Select product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="target-form-group">
                                <label>Monthly Target Qty</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="items[0][target_qty]"
                                    class="target-control"
                                    placeholder="15000"
                                    required
                                >
                            </div>

                            <button type="button" class="btn-target btn-target-danger" onclick="removeRow(this)">
                                ×
                            </button>
                        </div>
                    </div>
                </div>

                <div class="target-actions">
                    <button type="submit" class="btn-target btn-target-primary">
                        💾 Save Target
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="target-card">
        <div class="target-card-header">
            <h4>📋 Saved Targets</h4>
        </div>

        <div class="target-card-body">
            @forelse($targets as $target)
                <div class="saved-target">
                    <div class="saved-target-top">
                        <div>
                            <div class="saved-title">
                                {{ $target->title ?: 'Untitled Target' }}
                            </div>

                            <div class="saved-meta">
                                Period:
                                {{ optional($target->period_start)->format('d/m/Y') }}
                                -
                                {{ optional($target->period_end)->format('d/m/Y') }}
                                |
                                Sales:
                                {{ $target->assigned_to ? ($userNames[$target->assigned_to] ?? 'Unknown User') : 'All Team' }}
                            </div>
                        </div>

                        <span class="status-badge">
                            {{ ucfirst($target->status) }}
                        </span>
                    </div>

                    <div class="target-item-list">
                        @foreach($target->items as $item)
                            <div class="target-item">
                                <span class="target-item-name">
                                   {{ $productNames[$item->product_id] ?? 'Unknown Product' }}
                                </span>
                                <span class="target-item-value">
                                    {{ number_format($item->target_qty, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-state-icon">🎯</div>
                    <div>No target yet.</div>
                    <small>Create your first product sale target above.</small>
                </div>
            @endforelse

            @if(method_exists($targets, 'links'))
                <div style="margin-top: 12px;">
                    {{ $targets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    let targetRowIndex = 1;

    function addTargetRow() {
        const rows = document.getElementById('targetRows');

        const html = `
            <div class="target-row">
                <div class="target-form-group">
                    <label>Product</label>
                    <select name="items[${targetRowIndex}][product_id]" class="target-control" required>
                        <option value="">Select product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ addslashes($product->name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="target-form-group">
                    <label>Monthly Target Qty</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="items[${targetRowIndex}][target_qty]"
                        class="target-control"
                        placeholder="15000"
                        required
                    >
                </div>

                <button type="button" class="btn-target btn-target-danger" onclick="removeRow(this)">
                    ×
                </button>
            </div>
        `;

        rows.insertAdjacentHTML('beforeend', html);
        targetRowIndex++;
    }

    function removeRow(btn) {
        const totalRows = document.querySelectorAll('.target-row').length;

        if (totalRows <= 1) {
            alert('At least one product target row is required.');
            return;
        }

        btn.closest('.target-row').remove();
    }
</script>
@endsection