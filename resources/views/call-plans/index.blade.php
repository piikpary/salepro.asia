@extends('layouts.app')

@section('title', 'Smart Call Plan')

@section('content')
<style>
    .scp-page {
        padding: 18px 18px 24px;
        background: #eef2f7;
        min-height: calc(100vh - 60px);
    }

    .scp-title-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .scp-title-left h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        color: #1f2d3d;
    }

    .scp-title-left p {
        margin: 4px 0 0;
        color: #7b8aa0;
        font-size: 13px;
        line-height: 1.5;
    }

    .scp-title-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .scp-btn-generate {
        border: none;
        border-radius: 12px;
        height: 42px;
        padding: 0 18px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(34, 197, 94, 0.22);
        transition: .2s ease;
        cursor: pointer;
    }

    .scp-btn-generate:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(34, 197, 94, 0.28);
    }

    .scp-btn-generate:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .scp-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .scp-summary-card {
        background: #fff;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 3px 12px rgba(31, 45, 61, 0.06);
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 88px;
    }

    .scp-summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .scp-summary-icon.total {
        background: #e8f1ff;
        color: #2d6cdf;
    }

    .scp-summary-icon.completed {
        background: #e7f8ed;
        color: #28a745;
    }

    .scp-summary-icon.pending {
        background: #fff6dc;
        color: #f0a000;
    }

    .scp-summary-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .scp-summary-card h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.1;
        font-weight: 700;
        color: #142033;
    }

    .scp-summary-card p {
        margin: 0;
        color: #7b8aa0;
        font-size: 13px;
    }

    .scp-progress-wrap {
        background: #fff;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 14px;
        box-shadow: 0 3px 12px rgba(31, 45, 61, 0.06);
    }

    .scp-progress-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 8px;
    }

    .scp-progress-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #23344d;
    }

    .scp-progress-title-icon {
        color: #2d6cdf;
        font-size: 14px;
    }

    .scp-progress-percent {
        color: #2d6cdf;
        font-size: 14px;
        font-weight: 700;
    }

    .scp-progress-bar {
        width: 100%;
        height: 8px;
        background: #e6ebf2;
        border-radius: 999px;
        overflow: hidden;
    }

    .scp-progress-fill {
        height: 100%;
        background: #2d6cdf;
        border-radius: 999px;
        transition: width .25s ease;
    }

    .scp-progress-stats {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #7b8aa0;
        margin-top: 10px;
    }

    .scp-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
        vertical-align: middle;
    }

    .scp-dot.completed { background: #28a745; }
    .scp-dot.follow { background: #f39c12; }
    .scp-dot.pending { background: #c0c7d2; }
    .scp-dot.skipped { background: #2d6cdf; }

    .scp-filter-box {
        background: #fff;
        border-radius: 14px;
        padding: 12px;
        box-shadow: 0 3px 12px rgba(31, 45, 61, 0.06);
        margin-bottom: 14px;
    }

    .scp-filter-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .scp-filter-tabs {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .scp-filter-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        border: 1px solid #d8e0ea;
        background: #f5f7fb;
        color: #6d7b92;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: .2s ease;
    }

    .scp-filter-pill .scp-pill-count {
        min-width: 20px;
        height: 20px;
        border-radius: 999px;
        background: rgba(255,255,255,.75);
        padding: 0 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
    }

    .scp-filter-pill.active,
    .scp-filter-pill:hover {
        background: #2d6cdf;
        border-color: #2d6cdf;
        color: #fff;
        text-decoration: none;
    }

    .scp-filter-pill.active .scp-pill-count,
    .scp-filter-pill:hover .scp-pill-count {
        background: rgba(255,255,255,.18);
        color: #fff;
    }

    .scp-filter-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .scp-select,
    .scp-search {
        height: 38px;
        border: 1px solid #d9e2ec;
        border-radius: 10px;
        padding: 0 12px;
        background: #fff;
        font-size: 13px;
        color: #334155;
        outline: none;
    }

    .scp-select {
        min-width: 120px;
    }

    .scp-search {
        min-width: 220px;
    }

    .scp-btn-primary {
        background: #2d6cdf;
        color: #fff;
        border: none;
        border-radius: 10px;
        height: 38px;
        padding: 0 18px;
        font-weight: 700;
        font-size: 13px;
        transition: .2s ease;
    }

    .scp-btn-primary:hover {
        background: #1f5ed4;
        color: #fff;
    }

    .scp-board {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        align-items: start;
    }

    .scp-column {
        background: #eaf0f6;
        border-radius: 14px;
        min-height: 470px;
        padding: 0;
        overflow: hidden;
        border: 1px solid #dde5ef;
    }

    .scp-column.to-call {
        border-top: 3px solid #2d6cdf;
    }

    .scp-column.follow-up {
        border-top: 3px solid #f39c12;
    }

    .scp-column.completed {
        border-top: 3px solid #28a745;
    }

    .scp-column.skipped {
        border-top: 3px solid #c7ced8;
    }

    .scp-column-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 12px 10px;
        font-size: 14px;
        font-weight: 700;
        color: #334155;
    }

    .scp-column-head h5 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #334155;
    }

    .scp-count {
        min-width: 22px;
        height: 22px;
        border-radius: 999px;
        background: rgba(255,255,255,.9);
        color: #7b8aa0;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        padding: 0 7px;
    }

    .scp-column-body {
        padding: 0 12px 12px;
        max-height: 620px;
        overflow-y: auto;
    }

    .scp-card {
        background: #fff;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 2px 10px rgba(31, 45, 61, 0.05);
        border: 1px solid #edf1f6;
        position: relative;
    }

    .scp-card.high {
        border-left: 3px solid #ff5b5b;
    }

    .scp-card.medium {
        border-left: 3px solid #f0a000;
    }

    .scp-card.low {
        border-left: 3px solid #28a745;
    }

    .scp-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 8px;
    }

    .scp-company {
        font-size: 15px;
        font-weight: 700;
        color: #1f2d3d;
        line-height: 1.35;
    }

    .scp-priority {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        white-space: nowrap;
        letter-spacing: .3px;
    }

    .scp-priority.high {
        background: #ffe5e5;
        color: #ef4444;
    }

    .scp-priority.medium {
        background: #fff2da;
        color: #f59e0b;
    }

    .scp-priority.low {
        background: #e6f8eb;
        color: #16a34a;
    }

    .scp-meta {
        font-size: 12px;
        color: #607086;
        margin-bottom: 5px;
        line-height: 1.5;
    }

    .scp-meta strong {
        color: #526377;
        font-weight: 700;
        display: inline-block;
        min-width: 125px;
    }

    .scp-ai-reason {
        margin-top: 8px;
        background: #f6f8fb;
        border: 1px solid #e8edf4;
        border-radius: 9px;
        padding: 8px 10px;
        font-size: 12px;
        color: #526377;
        line-height: 1.55;
        min-height: 44px;
    }

    .scp-generate-count {
        width: 110px;
        height: 42px;
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        padding: 0 12px;
        background: #fff;
        font-size: 13px;
        color: #334155;
        outline: none;
    }

    .scp-completed-box {
        margin-top: 9px;
        border: 1px solid #bfe6cc;
        background: #effaf3;
        color: #28a745;
        border-radius: 8px;
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        padding: 8px 10px;
    }

    .scp-card-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .scp-btn-log,
    .scp-btn-skip {
        flex: 1;
        height: 34px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        transition: .2s ease;
    }

    .scp-btn-log {
        background: #2d6cdf;
        color: #fff;
    }

    .scp-btn-log:hover {
        background: #1f5ed4;
    }

    .scp-btn-skip {
        background: #eef2f7;
        color: #6b7280;
    }

    .scp-btn-skip:hover {
        background: #e2e8f0;
    }

    .scp-empty {
        background: rgba(255,255,255,.68);
        border: 1px dashed #cdd7e3;
        border-radius: 12px;
        padding: 24px 12px;
        text-align: center;
        font-size: 13px;
        color: #99a6b6;
        margin-top: 4px;
    }

    .scp-modal-mask {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 20px;
    }

    .scp-modal {
        width: 100%;
        max-width: 560px;
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }

    .scp-modal-head {
        background: #2d6cdf;
        color: #fff;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .scp-modal-head h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
    }

    .scp-modal-close {
        background: transparent;
        border: none;
        color: #fff;
        font-size: 22px;
        line-height: 1;
    }

    .scp-modal-body {
        padding: 18px;
    }

    .scp-modal-body label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 6px;
    }

    .scp-modal-body input,
    .scp-modal-body select,
    .scp-modal-body textarea {
        width: 100%;
        border: 1px solid #dbe2ea;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
        font-size: 13px;
        color: #334155;
        background: #fff;
    }

    .scp-modal-body textarea {
        min-height: 110px;
        resize: vertical;
    }

    .scp-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 6px;
    }

    .scp-btn-cancel {
        border: none;
        background: #eef2f7;
        color: #475569;
        border-radius: 10px;
        height: 40px;
        padding: 0 16px;
        font-weight: 700;
    }

    .scp-btn-save {
        border: none;
        background: #28a745;
        color: #fff;
        border-radius: 10px;
        height: 40px;
        padding: 0 16px;
        font-weight: 700;
    }

    .scp-ai-float-btn {
        position: fixed;
        right: 22px;
        bottom: 22px;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(180deg, #ff9b6a, #ff6b3d);
        box-shadow: 0 10px 28px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 99999;
        transition: .2s ease;
        border: none;
    }

    .scp-ai-float-btn:hover {
        transform: translateY(-2px) scale(1.03);
    }

    .scp-ai-float-btn span {
        color: #fff;
        font-size: 27px;
        font-weight: 700;
        line-height: 1;
        letter-spacing: 2px;
    }

    .scp-ai-chat-popup {
        position: fixed;
        right: 24px;
        bottom: 98px;
        width: 365px;
        max-width: calc(100vw - 30px);
        height: 500px;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
        overflow: hidden;
        z-index: 99998;
        display: none;
        flex-direction: column;
    }

    .scp-ai-chat-popup.active {
        display: flex;
    }

    .scp-ai-chat-header {
        background: #2d6cdf;
        color: #fff;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .scp-ai-chat-title {
        font-size: 15px;
        font-weight: 700;
    }

    .scp-ai-chat-subtitle {
        font-size: 12px;
        opacity: .85;
        margin-top: 2px;
    }

    .scp-ai-chat-close {
        border: none;
        background: transparent;
        color: #fff;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
    }

    .scp-ai-chat-body {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        background: #f8fafc;
    }

    .scp-ai-chat-footer {
        padding: 12px;
        border-top: 1px solid #e2e8f0;
        background: #fff;
    }

    .scp-ai-input-row {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .scp-ai-chat-input {
        flex: 1;
        width: auto;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 12px;
        resize: none;
        outline: none;
        font-size: 14px;
        min-height: 54px;
        max-height: 140px;
        color: #1e293b;
    }

    .scp-ai-send-btn {
        width: 46px;
        height: 46px;
        border: none;
        border-radius: 12px;
        background: #2d6cdf;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: .2s ease;
    }

    .scp-ai-send-btn:hover {
        background: #1f5ed4;
    }

    .scp-ai-send-btn svg {
        display: block;
    }

    .scp-chat-row {
        display: flex;
        margin-bottom: 12px;
    }

    .scp-chat-row.user {
        justify-content: flex-end;
    }

    .scp-chat-row.assistant {
        justify-content: flex-start;
    }

    .scp-chat-bubble {
        max-width: 82%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 13px;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .scp-chat-row.user .scp-chat-bubble {
        background: #2d6cdf;
        color: #fff;
        border-bottom-right-radius: 6px;
    }

    .scp-chat-row.assistant .scp-chat-bubble {
        background: #fff;
        color: #334155;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 6px;
    }

    .scp-chat-loading {
        display: none;
        margin-bottom: 8px;
        font-size: 12px;
        color: #2d6cdf;
        font-weight: 600;
    }

    @media (max-width: 1200px) {
        .scp-summary {
            grid-template-columns: 1fr;
        }

        .scp-board {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .scp-title-actions {
            width: 100%;
        }

        .scp-generate-count,
        .scp-btn-generate {
            width: 100%;
        }

        .scp-board {
            grid-template-columns: 1fr;
        }

        .scp-filter-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .scp-filter-controls {
            width: 100%;
        }

        .scp-select,
        .scp-search,
        .scp-btn-primary {
            width: 100%;
        }

        .scp-ai-chat-popup {
            right: 12px;
            bottom: 88px;
            width: calc(100vw - 24px);
            height: 460px;
        }

        .scp-chat-bubble {
            max-width: 100%;
        }
    }
</style>

@php
    $total = $board['summary']['total'] ?? 0;
    $completed = $board['summary']['completed'] ?? 0;
    $pending = $board['summary']['pending'] ?? 0;
    $followUpCount = isset($board['follow_up']) ? $board['follow_up']->count() : 0;
    $skippedCount = isset($board['skipped']) ? $board['skipped']->count() : 0;
    $progressPercent = $total > 0 ? round(($completed / $total) * 100) : 0;
    $selectedGroupId = $selectedGroupId ?? request('customer_group_id');

    $columns = [
        'to_call' => 'To Call',
        'follow_up' => 'Follow Up',
        'completed' => 'Completed',
        'skipped' => 'Skipped / No Answer',
    ];

    $allBoardItems = collect();

    foreach (['to_call', 'follow_up', 'completed', 'skipped'] as $boardKey) {
        $allBoardItems = $allBoardItems->merge(collect($board[$boardKey] ?? []));
    }

    $groupCounts = [];

    foreach (($customerGroups ?? []) as $group) {
        $groupCounts[$group->id] = $allBoardItems->filter(function ($item) use ($group) {
            return (string) optional($item->customer)->source_customer_group_id === (string) $group->id;
        })->count();
    }

    $allTabCount = $board['all_total'] ?? $total;

    function scpFormatDateTime($value) {
        if (empty($value)) {
            return '_';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y h:i A');
        } catch (\Throwable $e) {
            return '_';
        }
    }
@endphp

<div class="scp-page">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="scp-title-bar">
        <div class="scp-title-left">
            <h2>Smart Call Plan</h2>
            <p>AI analyzed customer data and generated today's action plan.</p>
        </div>

        <div class="scp-title-actions">
            <form
                id="generateCallPlanForm"
                action="{{ route('call-plans.generate') }}"
                method="POST"
                style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;"
            >
                @csrf

                @if(request()->filled('location_id'))
                    <input type="hidden" name="location_id" value="{{ request('location_id') }}">
                @endif

                <input
                    type="number"
                    name="limit_customers"
                    id="limit_customers"
                    class="scp-generate-count"
                    min="1"
                    max="1000"
                    value="{{ old('limit_customers', request('limit_customers', 20)) }}"
                    placeholder="Count"
                >

                <button
                    type="submit"
                    id="generateCallPlanBtn"
                    class="scp-btn-generate"
                >
                    <span id="generateCallPlanBtnText">⚡ Generate Call Plan</span>
                </button>
            </form>
        </div>
    </div>

    <div class="scp-summary">
        <div class="scp-summary-card">
            <div class="scp-summary-icon total">📋</div>
            <div class="scp-summary-content">
                <h3>{{ $total }}</h3>
                <p>Total Customers</p>
            </div>
        </div>

        <div class="scp-summary-card">
            <div class="scp-summary-icon completed">✔</div>
            <div class="scp-summary-content">
                <h3>{{ $completed }}</h3>
                <p>Completed</p>
            </div>
        </div>

        <div class="scp-summary-card">
            <div class="scp-summary-icon pending">🕒</div>
            <div class="scp-summary-content">
                <h3>{{ $pending }}</h3>
                <p>Pending</p>
            </div>
        </div>
    </div>

    <div class="scp-progress-wrap">
        <div class="scp-progress-top">
            <div>
                <div class="scp-progress-title">
                    <span class="scp-progress-title-icon">☰</span>
                    <span>Today's Progress</span>
                </div>
            </div>
            <div class="scp-progress-percent">{{ $progressPercent }}%</div>
        </div>

        <div class="scp-progress-bar">
            <div class="scp-progress-fill" style="width: {{ $progressPercent }}%;"></div>
        </div>

        <div class="scp-progress-stats">
            <span><span class="scp-dot completed"></span>Completed: {{ $completed }}</span>
            <span><span class="scp-dot follow"></span>Follow Up: {{ $followUpCount }}</span>
            <span><span class="scp-dot pending"></span>Pending: {{ $pending }}</span>
            <span><span class="scp-dot skipped"></span>Skipped: {{ $skippedCount }}</span>
        </div>
    </div>

    <div class="scp-filter-box">
        <form method="GET" action="{{ route('call-plans.index') }}">
            @if(request()->filled('location_id'))
                <input type="hidden" name="location_id" value="{{ request('location_id') }}">
            @endif

            @if(!empty($selectedGroupId))
                <input type="hidden" name="customer_group_id" value="{{ $selectedGroupId }}">
            @endif

            <div class="scp-filter-toolbar">
                <div class="scp-filter-tabs">
                    <a href="{{ route('call-plans.index', request()->except('customer_group_id')) }}"
                       class="scp-filter-pill {{ empty($selectedGroupId) ? 'active' : '' }}">
                        <span>All</span>
                        <span class="scp-pill-count">{{ $allTabCount }}</span>
                    </a>

                    @foreach($customerGroups ?? [] as $group)
                        <a href="{{ route('call-plans.index', array_merge(request()->except('customer_group_id'), ['customer_group_id' => $group->id])) }}"
                           class="scp-filter-pill {{ (string) $selectedGroupId === (string) $group->id ? 'active' : '' }}">
                            <span>{{ $group->name }}</span>
                            <span class="scp-pill-count">{{ $groupCounts[$group->id] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="scp-filter-controls">
                    <select name="priority" class="scp-select">
                        <option value="all" {{ request('priority', 'all') === 'all' ? 'selected' : '' }}>
                            All Priority
                        </option>

                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>
                            High
                        </option>

                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>
                            Medium
                        </option>

                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>
                            Low
                        </option>
                    </select>

                    <input
                        type="text"
                        name="search"
                        class="scp-search"
                        placeholder="Search name or company..."
                        value="{{ request('search') }}"
                    >

                    <button type="submit" class="scp-btn-primary">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="scp-board">
        @foreach($columns as $key => $label)
            @php
                $columnClass = $key === 'to_call'
                    ? 'to-call'
                    : ($key === 'follow_up'
                        ? 'follow-up'
                        : ($key === 'completed' ? 'completed' : 'skipped'));
            @endphp

            <div class="scp-column {{ $columnClass }}">
                <div class="scp-column-head">
                    <h5>{{ $label }}</h5>
                    <div class="scp-count">{{ isset($board[$key]) ? $board[$key]->count() : 0 }}</div>
                </div>

                <div class="scp-column-body">
                    @forelse($board[$key] ?? [] as $item)
                       @php
    $customer = $item->customer;

    $rawPriority = strtolower(trim((string) ($item->priority ?? '')));

                        $priorityClass = in_array($rawPriority, ['high', 'medium', 'low'], true)
                            ? $rawPriority
                            : 'medium';

                        $priorityLabel = in_array($rawPriority, ['high', 'medium', 'low'], true)
                            ? strtoupper($rawPriority)
                            : 'n.d';

                        $companyName = $customer?->company_name
                            ?: $customer?->name
                            ?: 'Walk-In Customer';

                        $rawGroupName = trim((string) ($customer?->customer_group_name ?? ''));

                        $customerGroupName = $rawGroupName !== ''
                            ? $rawGroupName
                            : '_';

                        $phone = $customer?->phone ?: '_';

                        $lastOrderDate = scpFormatDateTime($customer?->last_order_date ?? null);
                        $lastCallDate = scpFormatDateTime($customer?->last_call_date ?? null);
                        $lastVisitDate = scpFormatDateTime($customer?->last_visit_date ?? null);
                    @endphp

                        <div class="scp-card {{ $priorityClass }}">
                            <div class="scp-card-top">
                                <div class="scp-company">{{ $companyName }}</div>

                                <div class="scp-priority {{ $priorityClass }}">
                                    {{ $priorityLabel }}
                                </div>
                            </div>

                            <div class="scp-meta"><strong>Group</strong> : {{ $customerGroupName }}</div>
                            <div class="scp-meta"><strong>Phone</strong> : {{ $phone }}</div>
                            <div class="scp-meta"><strong>Last Order Date</strong> : {{ $lastOrderDate }}</div>
                            <div class="scp-meta"><strong>Last Call Date</strong> : {{ $lastCallDate }}</div>
                            <div class="scp-meta"><strong>Last Visit Date</strong> : {{ $lastVisitDate }}</div>

                            @if($key === 'completed')
                                <div class="scp-ai-reason">
                                    {{ $item->ai_reason ?: 'Completed customer interaction.' }}
                                </div>

                                <div class="scp-completed-box">
                                    Order Placed
                                </div>
                            @elseif($key === 'skipped')
                                <div class="scp-ai-reason">
                                    {{ $item->ai_reason ?: 'No response was received from this customer.' }}
                                </div>
                            @else
                                <div class="scp-ai-reason">
                                    {{ $item->ai_reason ?: 'Suggested follow-up reason not available.' }}
                                </div>

                                <div class="scp-card-actions">
                                    <button
                                        type="button"
                                        class="scp-btn-log"
                                        onclick='openLogModal(
                                            {{ (int) $item->customer_id }},
                                            @json($companyName),
                                            @json($phone)
                                        )'
                                    >
                                        Log Result
                                    </button>

                                    <button
                                        type="button"
                                        class="scp-btn-skip"
                                        onclick='openSkipModal(
                                            {{ (int) $item->customer_id }},
                                            @json($companyName),
                                            @json($phone)
                                        )'
                                    >
                                        Skip
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="scp-empty">No records</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="scp-modal-mask" id="logModal">
    <div class="scp-modal">
        <div class="scp-modal-head">
            <h4>Log Call Outcome</h4>
            <button type="button" class="scp-modal-close" onclick="closeLogModal()">×</button>
        </div>

        <form action="{{ route('call-plans.log') }}" method="POST">
            @csrf

            <div class="scp-modal-body">
                <input type="hidden" name="customer_id" id="modal_customer_id">

                <label>Customer</label>
                <input type="text" id="modal_customer_name" readonly>

                <label>Phone</label>
                <input type="text" id="modal_customer_phone" readonly>

                <label>Call Result</label>
                <select
                    name="call_result"
                    id="modal_call_result"
                    required
                    onchange="toggleNextCallDate()"
                >
                    <option value="">Select outcome...</option>
                    <option value="order_placed">Order Placed (Success)</option>
                    <option value="interested">Interested / Positive</option>
                    <option value="request_callback">Request Callback</option>
                    <option value="follow_up_needed">Follow Up Needed</option>
                    <option value="no_answer">No Answer</option>
                    <option value="busy">Busy</option>
                    <option value="not_interested">Not Interested</option>
                </select>

                <div id="next_call_date_wrap" style="display: none;">
                    <label>Next Call Date</label>
                    <input type="date" name="next_call_date" id="modal_next_call_date">
                </div>

                <label>Notes / Updates</label>
                <textarea name="notes" placeholder="Enter conversation details..."></textarea>

                <div class="scp-modal-footer">
                    <button type="button" class="scp-btn-cancel" onclick="closeLogModal()">Cancel</button>
                    <button type="submit" class="scp-btn-save">Save Log</button>
                </div>
            </div>
        </form>
    </div>
</div>

<button type="button" class="scp-ai-float-btn" onclick="toggleAiChat()">
    <span>•••</span>
</button>

<div class="scp-ai-chat-popup" id="scpAiChatPopup">
    <div class="scp-ai-chat-header">
        <div>
            <div class="scp-ai-chat-title">AI Assistant</div>
            <div class="scp-ai-chat-subtitle">Smart Call Plan</div>
        </div>
        <button type="button" class="scp-ai-chat-close" onclick="toggleAiChat(false)">×</button>
    </div>

    <div class="scp-ai-chat-body" id="chatBox">
        @forelse($messages ?? [] as $message)
            <div class="scp-chat-row {{ $message->role === 'user' ? 'user' : 'assistant' }}">
                <div class="scp-chat-bubble">
                    {{ $message->message }}
                </div>
            </div>
        @empty
            <div class="scp-chat-row assistant">
                <div class="scp-chat-bubble">
                    Hello, you can type a prompt or click the Generate Call Plan button.
                </div>
            </div>
        @endforelse
    </div>

    <div class="scp-ai-chat-footer">
        <div id="chatLoading" class="scp-chat-loading">AI is thinking...</div>

        <div class="scp-ai-input-row">
            <textarea
                id="promptInput"
                class="scp-ai-chat-input"
                rows="1"
                placeholder="Ask AI about smart call plan..."
            ></textarea>

            <button type="button" class="scp-ai-send-btn" onclick="sendPrompt()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
    const promptInput = document.getElementById('promptInput');
    const chatBox = document.getElementById('chatBox');
    const chatLoading = document.getElementById('chatLoading');

    function openLogModal(customerId, customerName, customerPhone) {
        document.getElementById('modal_customer_id').value = customerId;
        document.getElementById('modal_customer_name').value = customerName || '';
        document.getElementById('modal_customer_phone').value = customerPhone || '';
        document.getElementById('logModal').style.display = 'flex';

        const resultSelect = document.getElementById('modal_call_result');
        const nextCallDate = document.getElementById('modal_next_call_date');

        if (resultSelect) {
            resultSelect.value = '';
        }

        if (nextCallDate) {
            nextCallDate.value = '';
        }

        toggleNextCallDate();
    }

    function closeLogModal() {
        document.getElementById('logModal').style.display = 'none';
    }

    function openSkipModal(customerId, customerName, customerPhone) {
        openLogModal(customerId, customerName, customerPhone);

        const resultSelect = document.getElementById('modal_call_result');

        if (resultSelect) {
            resultSelect.value = 'not_interested';
        }

        toggleNextCallDate();
    }

    function toggleNextCallDate() {
        const resultSelect = document.getElementById('modal_call_result');
        const wrap = document.getElementById('next_call_date_wrap');
        const input = document.getElementById('modal_next_call_date');

        if (!resultSelect || !wrap || !input) {
            return;
        }

        const result = resultSelect.value;

        if (result === 'request_callback') {
            wrap.style.display = 'block';
            input.required = true;
        } else {
            wrap.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    window.addEventListener('click', function (e) {
        const modal = document.getElementById('logModal');

        if (modal && e.target === modal) {
            closeLogModal();
        }
    });

    function toggleAiChat(forceOpen = null) {
        const popup = document.getElementById('scpAiChatPopup');

        if (!popup) {
            return;
        }

        if (forceOpen === true) {
            popup.classList.add('active');
            localStorage.setItem('smart_call_ai_open', '1');
            return;
        }

        if (forceOpen === false) {
            popup.classList.remove('active');
            localStorage.setItem('smart_call_ai_open', '0');
            return;
        }

        popup.classList.toggle('active');

        if (popup.classList.contains('active')) {
            localStorage.setItem('smart_call_ai_open', '1');
        } else {
            localStorage.setItem('smart_call_ai_open', '0');
        }
    }

    function appendChatMessage(role, message) {
        if (!chatBox) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'scp-chat-row ' + role;

        const bubble = document.createElement('div');
        bubble.className = 'scp-chat-bubble';
        bubble.textContent = message || '';

        row.appendChild(bubble);
        chatBox.appendChild(row);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function setChatLoading(show) {
        if (!chatLoading) {
            return;
        }

        chatLoading.style.display = show ? 'block' : 'none';
    }

    function setPromptDisabled(disabled) {
        if (!promptInput) {
            return;
        }

        promptInput.disabled = disabled;
    }

    function setGenerateButtonLoading(loading) {
        const btn = document.getElementById('generateCallPlanBtn');
        const text = document.getElementById('generateCallPlanBtnText');

        if (!btn || !text) {
            return;
        }

        btn.disabled = loading;
        text.textContent = loading ? 'Generating...' : '⚡ Generate Call Plan';
    }

    async function sendPrompt(forcedPrompt = null, showUserMessage = true) {
        if (!chatBox) {
            return;
        }

        const prompt = forcedPrompt
            ? forcedPrompt.trim()
            : (promptInput ? promptInput.value.trim() : '');

        if (!prompt) {
            return;
        }

        toggleAiChat(true);

        if (showUserMessage) {
            appendChatMessage('user', prompt);
        } else {
            appendChatMessage('assistant', 'Generating today call plan...');
        }

        if (promptInput && !forcedPrompt) {
            promptInput.value = '';
            promptInput.style.height = '54px';
        }

        setPromptDisabled(true);
        setChatLoading(true);
        setGenerateButtonLoading(true);

        try {
            const response = await fetch("{{ route('call-plans.chat') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    prompt: prompt
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to generate call plan.');
            }

            appendChatMessage('assistant', data.reply || 'Call plan generated successfully.');
            toggleAiChat(true);
            localStorage.setItem('smart_call_ai_open', '1');

            if (data.should_reload === true) {
                setTimeout(() => {
                    localStorage.setItem('smart_call_ai_open', '1');
                    window.location.reload();
                }, 1200);
            }
        } catch (error) {
            appendChatMessage(
                'assistant',
                error.message || 'Sorry, something went wrong while generating the call plan.'
            );

            toggleAiChat(true);
            console.error(error);
        } finally {
            setPromptDisabled(false);
            setChatLoading(false);
            setGenerateButtonLoading(false);

            if (promptInput) {
                promptInput.focus();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const wasOpen = localStorage.getItem('smart_call_ai_open');
        const generateForm = document.getElementById('generateCallPlanForm');

        if (wasOpen === '1') {
            toggleAiChat(true);
        }

        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        if (promptInput) {
            promptInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendPrompt();
                }
            });

            promptInput.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 140) + 'px';
            });
        }

        if (generateForm) {
            generateForm.addEventListener('submit', function () {
                setGenerateButtonLoading(true);
            });
        }
    });
</script>
@endsection