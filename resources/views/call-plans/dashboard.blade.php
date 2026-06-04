@extends('layouts.app')

@section('content')
@php
    $tasksByStatus = $tasksByStatus ?? collect([
        'todo' => collect(),
        'follow_up' => collect(),
        'completed' => collect(),
        'skipped' => collect(),
    ]);

    $todoTasks = $tasksByStatus['todo'] ?? collect();
    $followUpTasks = $tasksByStatus['follow_up'] ?? collect();
    $completedTasks = $tasksByStatus['completed'] ?? collect();
    $skippedTasks = $tasksByStatus['skipped'] ?? collect();

    $allTasks = $tasksByStatus->flatten(1);

    $totalTasks = $allTasks->count();
    $completedCount = $completedTasks->count();
    $followUpCount = $followUpTasks->count();
    $skippedCount = $skippedTasks->count();
    $todoCount = $todoTasks->count();

    $pendingCount = $todoCount + $followUpCount;
    $progressPercent = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 0) : 0;
    $hitRate = $progressPercent;

    $productNames = $productNames ?? collect();
    $team = $team ?? collect();

    $activeTargets = $targets ?? collect();

    $productOptions = $activeTargets->count()
        ? $activeTargets->flatMap(fn($target) => $target->details)
            ->map(fn($item) => [
                'id' => $item->product_id,
                'name' => $productNames[$item->product_id] ?? optional($item->product)->name ?? 'Unknown Product',
            ])
            ->unique('id')
            ->values()
        : collect();

    $teamOptions = $team->map(function($user) {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->surname ?? '')) ?: ($user->username ?? 'User #' . $user->id),
        ];
    })->values();

    $chatRows = collect($summary['product_performance'] ?? []);

    $chatCritical = $chatRows
        ->filter(fn($row) => ($row['gap'] ?? 0) > 0)
        ->sortByDesc('gap')
        ->first();

    $chatStrategyProduct = $chatCritical['product_name']
        ?? ($chatRows->first()['product_name'] ?? 'Product');

    $chatTotalDays = 30;
    $chatElapsedDay = 1;

    $startDate = $target->start_date ?? $target->period_start ?? null;
    $endDate = $target->end_date ?? $target->period_end ?? null;

    if ($startDate && $endDate) {
        $chatStart = \Carbon\Carbon::parse($startDate);
        $chatEnd = \Carbon\Carbon::parse($endDate);
        $chatToday = $today instanceof \Carbon\Carbon ? $today : \Carbon\Carbon::parse($today);

        $chatTotalDays = max(1, $chatStart->diffInDays($chatEnd) + 1);
        $chatElapsedDay = max(1, $chatStart->diffInDays($chatToday) + 1);
        $chatElapsedDay = min($chatElapsedDay, $chatTotalDays);
    }
@endphp

<style>
    .scp-page {
        background: #f4f7fb;
        min-height: calc(100vh - 80px);
        padding: 18px;
    }

    .scp-top-header {
        background: linear-gradient(135deg, #2674df, #173f91);
        color: #fff;
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 16px;
        box-shadow: 0 8px 22px rgba(38, 116, 223, .25);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .scp-top-header h3 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #fff;
    }

    .scp-subtitle {
        margin-top: 6px;
        font-size: 13px;
        color: rgba(255,255,255,.9);
        line-height: 1.5;
    }
    .task-result-box {
    margin-top: 10px;
    width: 100%;
    min-height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    border: 1px solid transparent;
}

.task-result-box.result-success {
    background: #e9f8ee;
    color: #16a05d;
    border-color: #9ee6bf;
}

.task-result-box.result-interested {
    background: #e9f8ee;
    color: #16a05d;
    border-color: #9ee6bf;
}

.task-result-box.result-callback {
    background: #fff7e7;
    color: #f39c12;
    border-color: #ffd58a;
}

.task-result-box.result-skipped {
    background: #eef2f7;
    color: #8a97ad;
    border-color: #dce5f2;
}

.task-result-box.result-default {
    background: #f5f7fb;
    color: #6b7890;
    border-color: #e2e8f0;
}

    .scp-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(22,35,79,.08);
        border: 1px solid #ebeff5;
    }

    .scp-btn-primary,
    .scp-btn-light,
    .scp-btn-success {
        min-height: 40px;
        border-radius: 10px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: .2s;
    }

    .scp-btn-success {
        background: #19c979;
        color: #fff;
    }

    .scp-btn-success:hover {
        background: #13ad67;
        color: #fff;
    }

    .scp-btn-primary {
        background: #2674df;
        color: #fff;
    }

    .scp-btn-primary:hover {
        background: #1c5fc0;
        color: #fff;
    }

    .scp-btn-light {
        background: #f2f5fa;
        color: #34405a;
        border: 1px solid #dfe7f2;
    }

    .scp-btn-light:hover {
        background: #e8eef7;
        color: #17233c;
    }

    .scp-empty-wrapper {
        min-height: 520px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .log-modal-box {
    width: 540px !important;
    max-width: 94vw !important;
    border-radius: 12px !important;
}

.log-modal-body {
    padding: 20px !important;
    background: #f6f8fc !important;
}

.log-customer-title {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 10px;
}

.log-form-group {
    margin-bottom: 16px;
}

.log-form-group label {
    display: block;
    font-size: 12px;
    font-weight: 900;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
}

.log-select,
.log-input {
    width: 100%;
    height: 42px;
    border-radius: 6px;
    background: #fff;
}

.log-textarea {
    width: 100%;
    min-height: 82px;
    border-radius: 8px;
    resize: vertical;
    background: #fff;
}

.log-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
    padding-top: 10px;
}






    .scp-empty-card {
        width: 560px;
        max-width: 95%;
        background: #fff;
        border: 1px solid #e4ebf5;
        border-radius: 18px;
        padding: 36px 30px;
        text-align: center;
        box-shadow: 0 10px 26px rgba(30, 41, 59, .08);
    }

    .scp-empty-icon {
        width: 76px;
        height: 76px;
        margin: 0 auto 16px;
        border-radius: 24px;
        background: #eaf3ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 38px;
    }

    .scp-empty-card h3 {
        margin: 0 0 10px;
        font-size: 22px;
        font-weight: 800;
        color: #17233c;
    }

    .scp-empty-card p {
        color: #6b7890;
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 22px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 14px;
    }

    .summary-card {
        padding: 18px;
        min-height: 105px;
    }

    .summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
    }

    .summary-value {
        font-size: 26px;
        font-weight: 800;
        color: #17233c;
        line-height: 1.2;
    }

    .summary-label {
        color: #6b7890;
        font-size: 13px;
        margin-top: 4px;
    }

    .progress-bar-wrap {
        width: 100%;
        height: 8px;
        background: #e8edf5;
        border-radius: 20px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: #2f76df;
        border-radius: 20px;
    }

    .filter-pill {
        border: 1px solid #dce5f2;
        border-radius: 999px;
        padding: 8px 14px;
        background: #fff;
        display: inline-flex;
        gap: 8px;
        align-items: center;
        cursor: pointer;
        margin-right: 8px;
        font-weight: 600;
        color: #4a5876;
        margin-bottom: 6px;
    }

    .filter-pill.active {
        background: #2f76df;
        color: #fff;
        border-color: #2f76df;
    }

    .scp-input,
    .scp-select {
        height: 40px;
        border: 1px solid #dce5f2;
        border-radius: 10px;
        padding: 8px 12px;
        outline: none;
        background: #fff;
    }

    .scp-input:focus,
    .scp-select:focus {
        border-color: #2f76df;
        box-shadow: 0 0 0 3px rgba(47, 118, 223, .12);
    }

    .board-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
    }

    .board-col {
        background: #f8faff;
        border: 1px solid #e2eaf8;
        border-top-width: 4px;
        border-radius: 16px;
        height: calc(100vh - 330px);
        min-height: 520px;
        max-height: 720px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 14px;
        scrollbar-width: thin;
        scrollbar-color: #b8c7dc #eef3fb;
    }

    .board-col::-webkit-scrollbar {
        width: 8px;
    }

    .board-col::-webkit-scrollbar-track {
        background: #eef3fb;
        border-radius: 999px;
    }

    .board-col::-webkit-scrollbar-thumb {
        background: #b8c7dc;
        border-radius: 999px;
    }

    .board-col::-webkit-scrollbar-thumb:hover {
        background: #8fa4c0;
    }

    .board-col.todo { border-top-color: #2f76df; }
    .board-col.follow_up { border-top-color: #f39c12; }
    .board-col.completed { border-top-color: #28a745; }
    .board-col.skipped { border-top-color: #adb5bd; }

    .board-col.drag-over {
        background: #eef6ff;
        border-color: #2f76df;
        box-shadow: inset 0 0 0 2px rgba(47, 118, 223, .18);
    }

    .board-col.completed.drag-over {
        background: #effaf4;
        border-color: #28a745;
    }

    .board-col.follow_up.drag-over {
        background: #fff8ea;
        border-color: #f39c12;
    }

    .board-col.skipped.drag-over {
        background: #f2f4f7;
        border-color: #adb5bd;
    }

    .board-header {
        position: sticky;
        top: -14px;
        z-index: 5;
        background: #f8faff;
        padding: 12px 0;
        margin-top: -14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        color: #32405c;
        margin-bottom: 12px;
    }

    .board-count {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #eef2f7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }

    .task-card {
        background: #fff;
        border-radius: 14px;
        border-left: 4px solid #2f76df;
        padding: 14px;
        margin-bottom: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,.04);
        cursor: grab;
    }

    .task-card:active {
        cursor: grabbing;
    }

    .task-card.dragging {
        opacity: 0.45;
        transform: scale(0.98);
    }

    .task-card.priority-high { border-left-color: #e74c3c; }
    .task-card.priority-medium { border-left-color: #f39c12; }
    .task-card.priority-low { border-left-color: #28a745; }

    .task-type-badge,
    .priority-badge {
        display: inline-block;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .task-type-call { background: #e9f1ff; color: #2f76df; }
    .task-type-visit { background: #ffeaf4; color: #d63384; }
    

    .priority-badge.priority-high { background: #fff0f0; color: #e74c3c; }
    .priority-badge.priority-medium { background: #fff7e7; color: #f39c12; }
    .priority-badge.priority-low { background: #eefbf1; color: #28a745; }

    .task-date-badge {
        display: inline-block;
        margin-top: 8px;
        background: #fff7e7;
        color: #f39c12;
        border: 1px solid #ffd58a;
        padding: 4px 8px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 900;
    }

    .task-title-line {
        font-size: 13px;
        color: #56647c;
        font-weight: 800;
        margin-top: 8px;
    }

    .task-title {
        font-size: 16px;
        font-weight: 800;
        color: #17233c;
        margin-top: 8px;
    }

    .task-meta {
        color: #6b7890;
        font-size: 13px;
        margin-top: 3px;
    }

    .task-info-grid {
        display: grid;
        grid-template-columns: 70px 1fr;
        column-gap: 6px;
        row-gap: 3px;
        margin-top: 5px;
        font-size: 13px;
    }

    .task-info-label {
        color: #6b7890;
        font-weight: 700;
    }

    .task-info-value {
        color: #34405a;
        font-weight: 600;
    }

    .task-info-value.assignee {
        color: #2f76df;
        font-weight: 800;
    }
    .task-info-value.assignee {
    color: #2f76df;
    font-weight: 800;
    line-height: 1.5;
    word-break: break-word;
}

    .task-note {
        background: #f5f7fb;
        border-radius: 10px;
        padding: 10px;
        font-size: 13px;
        margin: 10px 0;
        border-left: 3px solid #2f76df;
        color: #34405a;
    }

    .task-actions {
        display: flex;
        gap: 8px;
    }

    .task-btn {
        width: 100%;
        min-height: 34px;
        border-radius: 9px;
        border: none;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .task-btn-primary {
        background: #2f76df;
        color: #fff;
    }

    .task-btn-light {
        background: #eef2f7;
        color: #34405a;
    }

    .empty-col {
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 28px 10px;
        text-align: center;
        color: #8a97ad;
        background: rgba(255,255,255,.55);
        font-size: 13px;
    }

    .performance-table {
        width: 100%;
        border-collapse: collapse;
    }

    .performance-table th,
    .performance-table td {
        padding: 11px 12px;
        border-bottom: 1px solid #edf2f7;
        font-size: 13px;
        text-align: left;
    }

    .performance-table th {
        background: #f8fbff;
        color: #56647c;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 12px;
    }

    .status-badge-success,
    .status-badge-info,
    .status-badge-danger {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-badge-success { background: #e9f8ee; color: #28a745; }
    .status-badge-info { background: #eaf3ff; color: #2f76df; }
    .status-badge-danger { background: #fff0f0; color: #e74c3c; }

    .ai-plan-box {
        border: 1px solid #2f76df;
        background: #f7fbff;
        border-radius: 12px;
        padding: 14px;
        line-height: 1.7;
        color: #17233c;
    }

    .chat-fab {
        position: fixed;
        right: 26px;
        bottom: 24px;
        z-index: 1001;
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2674df, #1749b4);
        color: #fff;
        border: none;
        font-size: 28px;
        box-shadow: 0 12px 26px rgba(47,118,223,.38);
        cursor: pointer;
    }

    .chat-drawer {
        position: fixed;
        right: 26px;
        bottom: 100px;
        width: 560px;
        max-width: calc(100vw - 40px);
        height: 760px;
        max-height: calc(100vh - 125px);
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, .25);
        overflow: hidden;
        z-index: 1000;
        display: none;
        flex-direction: column;
        border: 1px solid #dbe7f7;
    }

    .chat-drawer.open {
        display: flex;
    }

    .chat-header {
        background: linear-gradient(135deg, #2674df, #1749b4);
        color: #fff;
        padding: 14px 16px;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .chat-title-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chat-avatar {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: rgba(255,255,255,.18);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .chat-title {
        font-size: 18px;
        font-weight: 800;
        line-height: 1.1;
    }

    .chat-subtitle {
        font-size: 12px;
        opacity: .9;
        margin-top: 3px;
    }

    .chat-day-badge {
        background: rgba(255,255,255,.2);
        border: 1px solid rgba(255,255,255,.35);
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .chat-close {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 10px;
        background: rgba(255,255,255,.2);
        color: #fff;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
    }

    .chat-body {
        flex: 1;
        overflow-y: auto;
        padding: 18px;
        background: #f5f8fc;
    }

    .chat-msg {
        margin-bottom: 14px;
        display: flex;
    }

    .chat-msg.assistant { justify-content: flex-start; }
    .chat-msg.user { justify-content: flex-end; }

    .chat-bubble {
        max-width: 100%;
        padding: 12px 14px;
        border-radius: 16px;
        font-size: 14px;
        box-shadow: 0 3px 8px rgba(15, 23, 42, .06);
        line-height: 1.75;
        word-break: break-word;
    }
    .area-select-card {
    background: #fff;
    border: 1px solid #e2eaf8;
    border-radius: 14px;
    padding: 14px;
    margin-top: 10px;
}

.area-card-title {
    font-size: 16px;
    font-weight: 900;
    color: #17233c;
    margin-bottom: 12px;
}

.area-label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #56647c;
    margin: 10px 0 6px;
}

.area-label span {
    color: #e53935;
}

.area-select-box {
    min-height: 44px;
    border: 1px solid #dbe5f3;
    border-radius: 8px;
    padding: 6px;
    background: #fff;
    cursor: pointer;
}

.area-selected-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.area-placeholder {
    color: #9aa8bc;
    padding: 5px 6px;
    font-size: 13px;
}

.area-tag {
    background: #eaf3ff;
    border: 1px solid #9dc2ff;
    color: #1d5ec9;
    border-radius: 6px;
    padding: 5px 9px;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.area-tag b {
    cursor: pointer;
    font-size: 14px;
}

.area-dropdown {
    display: none;
    max-height: 210px;
    overflow-y: auto;
    border: 1px solid #dbe5f3;
    border-radius: 8px;
    background: #fff;
    margin-top: 5px;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
}

.area-dropdown.show {
    display: block;
}

.area-search-input {
    width: 100%;
    height: 38px;
    border: none;
    border-bottom: 1px solid #eef2f7;
    padding: 8px 10px;
    outline: none;
}

.area-option-item {
    width: 100%;
    display: block;
    border: none;
    background: #fff;
    padding: 9px 12px;
    text-align: left;
    color: #34405a;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.area-option-item:hover {
    background: #f3f7ff;
    color: #1d5ec9;
}

.area-all-option {
    color: #1d5ec9;
    font-weight: 900;
}

.area-empty {
    padding: 10px 12px;
    color: #8a97ad;
    font-size: 13px;
}

.area-next-btn {
    width: 100%;
    margin-top: 14px;
    border: none;
    background: #2674df;
    color: #fff;
    border-radius: 8px;
    height: 42px;
    font-weight: 900;
    cursor: pointer;
}

    .chat-msg.assistant .chat-bubble {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #17233c;
    }

    .chat-msg.user .chat-bubble {
        background: #2674df;
        color: #fff;
    }

    .ai-strategy-card {
        background: #fff;
        border: 1px solid #dbe7f7;
        border-radius: 16px;
        overflow: hidden;
    }

    .ai-strategy-header {
        background: #195dbd;
        color: #fff;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .ai-strategy-title {
        font-size: 16px;
        font-weight: 900;
    }

    .ai-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        padding: 14px;
    }

    .ai-kpi-card {
        border: 1px solid #dce7f6;
        border-radius: 10px;
        padding: 12px 8px;
        text-align: center;
        background: #f8fbff;
    }

    .ai-kpi-card.expected {
        border-color: #8db9ff;
        background: #eef6ff;
    }

    .ai-kpi-card.actual {
        border-color: #9ee6bf;
        background: #effaf4;
    }

    .ai-kpi-card.gap {
        border-color: #ffb7b7;
        background: #fff3f3;
    }

    .ai-kpi-label {
        font-size: 11px;
        font-weight: 900;
        color: #6b7890;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .ai-kpi-value {
        margin-top: 5px;
        font-size: 19px;
        font-weight: 900;
        color: #17233c;
    }

    .ai-kpi-sub {
        margin-top: 3px;
        font-size: 10px;
        font-weight: 800;
        color: #64748b;
    }

    .ai-kpi-card.expected .ai-kpi-value { color: #2674df; }
    .ai-kpi-card.actual .ai-kpi-value { color: #16a05d; }
    .ai-kpi-card.gap .ai-kpi-value { color: #e53935; }

    .ai-section-title {
        padding: 0 14px 8px;
        font-size: 14px;
        font-weight: 900;
        color: #173f91;
    }

    .ai-performance-wrap {
        padding: 0 14px 14px;
    }

    .ai-performance-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .ai-performance-table th {
        color: #64748b;
        font-size: 11px;
        text-align: left;
        padding: 8px;
        border-bottom: 1px solid #e6edf7;
        text-transform: uppercase;
    }

    .ai-performance-table td {
        padding: 8px;
        border-bottom: 1px solid #eef2f7;
        font-weight: 700;
    }

    .ai-performance-table tr.critical-row {
        background: #fff5f5;
    }

    .ai-status {
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 10px;
        font-weight: 900;
    }

    .ai-status.success { background: #e9f8ee; color: #16a05d; }
    .ai-status.info { background: #eaf3ff; color: #2674df; }
    .ai-status.danger { background: #fff0f0; color: #e53935; }

    .chat-bubble .ai-plan-box {
        margin: 0 14px 14px;
        border: 1px solid #2674df;
        background: #f8fbff;
        border-radius: 14px;
        padding: 14px;
        line-height: 1.8;
        color: #17233c;
        font-size: 14px;
    }

    .chat-bubble .ai-plan-box h5 {
        margin: 0 0 10px;
        font-size: 15px;
        font-weight: 900;
        color: #173f91;
    }

    .chat-bubble .ai-plan-box ul {
        padding-left: 20px;
        margin-bottom: 8px;
    }

    .chat-choice-box {
        margin-top: 14px;
        padding: 14px;
        background: #f8fbff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
    }

    .quick-replies {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .quick-replies button {
        border: 1px solid #2674df;
        background: #fff;
        color: #2674df;
        border-radius: 999px;
        padding: 8px 13px;
        font-weight: 800;
        cursor: pointer;
        font-size: 13px;
        white-space: nowrap;
    }

    .quick-replies button.primary {
        background: #2674df;
        color: #fff;
    }
    .dynamic-quick-btn.selected {
    background: #2674df !important;
    color: #fff !important;
    box-shadow: 0 6px 14px rgba(38, 116, 223, .25);
        }

        .dynamic-quick-btn.quick-next-btn {
            background: #2674df !important;
            color: #fff !important;
            border-color: #2674df !important;
        }

        .multi-help-text {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 10px;
        }

    .chat-input-wrap {
        border-top: 1px solid #e6edf7;
        padding: 12px;
        display: flex;
        gap: 10px;
        background: #fff;
        flex-shrink: 0;
    }

    .chat-input-wrap input {
        flex: 1;
        border: 1px solid #dbe5f3;
        border-radius: 999px;
        padding: 12px 15px;
        outline: none;
        font-size: 14px;
    }

    .chat-input-wrap input:focus {
        border-color: #2674df;
        box-shadow: 0 0 0 3px rgba(38, 116, 223, .12);
    }

    .chat-input-wrap button {
        width: 46px;
        height: 46px;
        border: none;
        border-radius: 50%;
        background: #2674df;
        color: #fff;
        cursor: pointer;
        font-size: 18px;
    }

    .modal-scp-backdrop {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(15, 23, 42, 0.55) !important;
        display: none !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 999999 !important;
        padding: 24px !important;
    }

    .modal-scp-backdrop.show {
        display: flex !important;
    }

    .modal-scp {
        width: 760px !important;
        max-width: 94vw !important;
        max-height: 90vh !important;
        background: #fff !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        box-shadow: 0 25px 60px rgba(0,0,0,.28) !important;
    }

    .modal-scp.small {
        width: 540px !important;
    }

    .modal-scp-header {
        background: #2f76df;
        color: #fff;
        padding: 16px 20px;
        font-size: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-scp-body {
        padding: 20px;
        background: #f8fbff;
        max-height: calc(90vh - 70px);
        overflow-y: auto;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        background: rgba(255,255,255,.2);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }

    .manual-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    .manual-form-group.full {
        grid-column: 1 / -1;
    }

    .manual-form-group label {
        display: block;
        font-size: 12px;
        font-weight: 900;
        color: #17233c;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .manual-form-group label span {
        color: #e53935;
    }

    .manual-filter-summary {
        background: #eaf3ff;
        border: 1px solid #8db9ff;
        color: #26456f;
        border-radius: 10px;
        padding: 10px 12px;
        font-weight: 800;
        font-size: 13px;
    }

    .manual-item {
        background: #fff;
        border: 1px solid #e7edf7;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .search-result-item {
        padding: 10px 12px;
        border-bottom: 1px solid #edf2f7;
        cursor: pointer;
    }

    .search-result-item:hover {
        background: #f5f9ff;
    }

    @media (max-width: 1200px) {
        .board-grid,
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .scp-page {
            padding: 10px;
        }

        .board-grid,
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .chat-drawer {
            right: 10px;
            bottom: 88px;
            width: calc(100vw - 20px);
            height: 650px;
        }

        .ai-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .manual-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="scp-page">
    <div class="scp-top-header">
        <div>
            <h3>Task Plan (Call & Visit)</h3>
            <div class="scp-subtitle">
                AI analyzed target and generated today's action plan.<br>
                {{ $today->format('l, F d, Y') }}
            </div>
        </div>
    </div>

    @if(!$target)
        <div class="scp-empty-wrapper">
            <div class="scp-empty-card">
                <div class="scp-empty-icon">🎯</div>
                <h3>No Active Sale Target Found</h3>
                <p>
                    No active sale target is available for today.
                    Please set Sale Target first in Settings, then Smart Call Plan will automatically calculate the plan.
                </p>

                <button type="button" class="scp-btn-light" onclick="window.location.reload()">
                    Refresh
                </button>
            </div>
        </div>
    @else
        <div class="summary-grid">
            <div class="scp-card summary-card">
                <div style="display:flex; gap:14px; align-items:center;">
                    <div class="summary-icon" style="background:#eaf3ff;color:#2f76df;">☷</div>
                    <div>
                        <div class="summary-value">{{ number_format($totalTasks) }}</div>
                        <div class="summary-label">Total Tasks</div>
                    </div>
                </div>
            </div>

            <div class="scp-card summary-card">
                <div style="display:flex; gap:14px; align-items:center;">
                    <div class="summary-icon" style="background:#eefbf1;color:#28a745;">✓</div>
                    <div>
                        <div class="summary-value">{{ number_format($completedCount) }}</div>
                        <div class="summary-label">Completed</div>
                    </div>
                </div>
            </div>

            <div class="scp-card summary-card">
                <div style="display:flex; gap:14px; align-items:center;">
                    <div class="summary-icon" style="background:#fff7e7;color:#f39c12;">◔</div>
                    <div>
                        <div class="summary-value">{{ number_format($pendingCount) }}</div>
                        <div class="summary-label">Pending</div>
                    </div>
                </div>
            </div>

            <div class="scp-card summary-card">
                <div style="display:flex; gap:14px; align-items:center;">
                    <div class="summary-icon" style="background:#f3eaff;color:#7b2cbf;">◔</div>
                    <div>
                        <div class="summary-value">{{ number_format($hitRate) }}%</div>
                        <div class="summary-label">Hit Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="scp-card" style="padding:16px; margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h4 style="margin:0; font-size:16px; font-weight:800;">Today's Progress</h4>
                <strong style="color:#2f76df;">{{ $progressPercent }}%</strong>
            </div>

            <div class="progress-bar-wrap" style="margin-bottom:10px;">
                <div class="progress-bar-fill" style="width:{{ $progressPercent }}%;"></div>
            </div>

            <div style="font-size:13px; color:#6b7890;">
                <span style="margin-right:15px;">🟢 Completed: {{ $completedCount }}</span>
                <span style="margin-right:15px;">🟠 Follow Up: {{ $followUpCount }}</span>
                <span style="margin-right:15px;">⚪ Skipped: {{ $skippedCount }}</span>
                <span style="margin-right:15px;">🔵 Remaining: {{ $todoCount }}</span>
            </div>
        </div>

        <div class="scp-card" style="padding:16px; margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <span class="filter-pill active task-type-filter" data-type="all">📋 All {{ $totalTasks }}</span>
                    <span class="filter-pill task-type-filter" data-type="call">📞 Call</span>
                    <span class="filter-pill task-type-filter" data-type="visit">📍 Visit</span>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <select id="priorityFilter" class="scp-select">
                        <option value="">All Priority</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>

                    <input type="text" id="searchFilter" class="scp-input" placeholder="Search name or company...">
                </div>
            </div>
        </div>

        <div class="board-grid">
            @foreach(['todo' => 'To Do', 'follow_up' => 'Follow Up', 'completed' => 'Completed', 'skipped' => 'Skipped'] as $statusKey => $statusLabel)
                <div class="board-col {{ $statusKey }} board-drop-zone" data-status="{{ $statusKey }}">
                    <div class="board-header">
                        <span>{{ $statusLabel }}</span>
                        <span class="board-count">{{ ($tasksByStatus[$statusKey] ?? collect())->count() }}</span>
                    </div>

                    @forelse(($tasksByStatus[$statusKey] ?? collect()) as $task)
                        @php
                            $taskTypeClass = $task->task_type === 'visit'
                                ? 'task-type-visit'
                                : 'task-type-call';

                            $contactName = $task->contact_name
                                ?? $task->name
                                ?? $task->customer_name
                                ?? ('Customer #' . ($task->contact_id ?? ''));

                            $contactPhone = $task->phone
                                ?? $task->mobile
                                ?? $task->customer_phone
                                ?? '-';

                               $assigneeName = $task->assigned_name ?? null;

                        if (empty($assigneeName) && !empty($task->assigned_names ?? null)) {
                            $assigneeName = collect($task->assigned_names)
                                ->filter()
                                ->map(fn ($name) => trim((string) $name))
                                ->unique()
                                ->join(', ');
                        }

                        $assigneeName = $assigneeName ?: '-';
                            $focusProductName = $task->product_name ?? '-';
                            
                            $taskNoteText = $task->notes ?? $task->note ?? null;
                            
                            if (($focusProductName === '-' || $focusProductName === 'Unknown Product') && !empty($task->product_id ?? null)) {
                                $focusProductName = $productNames[$task->product_id] ?? 'Unknown Product';
                                }
                                $resultLabels = [
                                    'order_placed_success' => 'Order Placed',
                                    'interested_positive' => 'Interested',
                                    'request_callback' => 'Callback',
                                    'no_answer_busy' => 'No Answer',
                                    'not_interested' => 'Not Interested',
                                    'skipped' => 'Skipped',

                                    // old data support
                                    'order_placed' => 'Order Placed',
                                    'sale_closed' => 'Order Placed',
                                    'no_answer' => 'No Answer',
                                    'followup' => 'Callback',
                                    'declined' => 'Not Interested',
                                ];

                                $resultClass = [
                                    'order_placed_success' => 'result-success',
                                    'interested_positive' => 'result-interested',
                                    'request_callback' => 'result-callback',
                                    'no_answer_busy' => 'result-skipped',
                                    'not_interested' => 'result-skipped',
                                    'skipped' => 'result-skipped',

                                    // old data support
                                    'order_placed' => 'result-success',
                                    'sale_closed' => 'result-success',
                                    'no_answer' => 'result-skipped',
                                    'followup' => 'result-callback',
                                    'declined' => 'result-skipped',
                                ];
                                                        @endphp

                        <div class="task-card priority-{{ $task->priority }} task-item"
                            draggable="true"
                            data-task-id="{{ $task->id }}"
                            data-type="{{ $task->task_type }}"
                            data-priority="{{ $task->priority }}"
                            data-result="{{ $task->result }}"
                            data-name="{{ strtolower($contactName) }}">

                            <div style="display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
                                <span class="task-type-badge {{ $taskTypeClass }}">
                                    @if($task->task_type === 'visit')
                                        📍 Visit
                                    @else
                                        📞 Call
                                    @endif
                                </span>

                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
                                    @if(!empty($task->plan_date))
                                        <span class="task-date-badge" style="margin-top:0;">
                                            📅 {{ \Carbon\Carbon::parse($task->plan_date)->format('Y-m-d') }}
                                        </span>
                                    @endif

                                    <span class="priority-badge priority-{{ $task->priority }}">
                                        {{ strtoupper($task->priority) }}
                                    </span>
                                </div>
                            </div>

                            @if(!empty($task->ai_reason))
                                <div class="task-title-line">
                                    📁 {{ $task->ai_reason }}
                                </div>
                            @endif

                            <div class="task-title">{{ $contactName }}</div>

                            <div class="task-info-grid">
                                <div class="task-info-label">Group:</div>
                                <div class="task-info-value">
                                    {{ $task->customer_group ?? $task->group_name ?? '-' }}
                                </div>

                                <div class="task-info-label">Phone:</div>
                                <div class="task-info-value">
                                    {{ $contactPhone }}
                                </div>

                                <div class="task-info-label">Assignee:</div>
                                <div class="task-info-value assignee">
                                    {{ $assigneeName }}
                                </div>

                                @if(!empty($focusProductName) && $focusProductName !== '-')
                                    <div class="task-info-label">Focus:</div>
                                    <div class="task-info-value">
                                        {{ $focusProductName }}
                                    </div>
                                @endif
                            </div>

                            @if(!empty($taskNoteText))
                                <div class="task-note">{{ $taskNoteText }}</div>
                            @endif

                            @if(in_array($statusKey, ['todo', 'follow_up']))
                                <div class="task-actions">
                                    <button type="button"
                                            class="task-btn task-btn-primary btn-open-log"
                                            data-id="{{ $task->id }}"
                                            data-name="{{ $contactName }}"
                                            data-type="{{ $task->task_type }}">
                                        Log Result
                                    </button>

                                    <button type="button"
                                            class="task-btn task-btn-light btn-skip-task"
                                            data-id="{{ $task->id }}">
                                        Skip
                                    </button>
                                </div>
                            @else
                               @php
    $displayResult = $task->result;

    if (empty($displayResult) && $statusKey === 'completed') {
        $displayResult = 'completed';
    }

    if (empty($displayResult) && $statusKey === 'skipped') {
        $displayResult = 'skipped';
    }

    $resultLabels['completed'] = 'Completed';
    $resultLabels['skipped'] = 'Skipped';

    $resultClass['completed'] = 'result-success';
    $resultClass['skipped'] = 'result-skipped';
@endphp

<div class="task-result-box {{ $resultClass[$displayResult] ?? 'result-default' }}">
    {{ $resultLabels[$displayResult] ?? '-' }}
</div>
                            @endif
                        </div>
                    @empty
                        <div class="empty-col">No records</div>
                    @endforelse
                </div>
            @endforeach
        </div>

        
    @endif
</div>

<button class="chat-fab" id="chatFab">🤖</button>

<div class="chat-drawer" id="chatDrawer">
    <div class="chat-header">
        <div class="chat-title-wrap">
            <div class="chat-avatar">🤖</div>
            <div>
                <div class="chat-title">ជំនួយការ AI</div>
                <div class="chat-subtitle">Smart Call Plan Generator</div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            @if($target)
                <span class="chat-day-badge">ថ្ងៃទី {{ $chatElapsedDay }}/{{ $chatTotalDays }}</span>
            @endif
            <button type="button" class="chat-close" id="chatCloseBtn">✕</button>
        </div>
    </div>

    <div class="chat-body" id="chatBody">
        @if($target)
            <div class="chat-msg assistant">
                <div class="chat-bubble">
                    <div class="ai-strategy-card">
                        <div class="ai-strategy-header">
                            <div class="ai-strategy-title">
                                📈 AI Strategy: {{ $chatStrategyProduct }} Recovery
                            </div>
                            <span class="chat-day-badge">Day {{ $chatElapsedDay }} / {{ $chatTotalDays }}</span>
                        </div>

                        <div class="ai-kpi-grid">
                            <div class="ai-kpi-card">
                                <div class="ai-kpi-label">Total<br>Target</div>
                                <div class="ai-kpi-value">{{ number_format($summary['total_target'], 0) }}</div>
                                <div class="ai-kpi-sub">SKUs / Month</div>
                            </div>

                            <div class="ai-kpi-card expected">
                                <div class="ai-kpi-label">Expected<br>Today</div>
                                <div class="ai-kpi-value">{{ number_format($summary['expected_today'], 2) }}</div>
                                <div class="ai-kpi-sub">To stay On Track</div>
                            </div>

                            <div class="ai-kpi-card actual">
                                <div class="ai-kpi-label">Actual Sold</div>
                                <div class="ai-kpi-value">{{ number_format($summary['actual_sold'], 0) }}</div>
                                <div class="ai-kpi-sub">As of now</div>
                            </div>

                            <div class="ai-kpi-card gap">
                                <div class="ai-kpi-label">Gap /<br>Missing</div>
                                <div class="ai-kpi-value">{{ number_format($summary['gap_missing'], 2) }}</div>
                                <div class="ai-kpi-sub">Critical Status</div>
                            </div>
                        </div>

                        <div class="ai-section-title">🛒 Product Performance Details</div>

                        <div class="ai-performance-wrap">
                            <table class="ai-performance-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Target</th>
                                        <th>Actual</th>
                                        <th>Gap</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($chatRows as $row)
                                        <tr class="{{ ($row['gap'] ?? 0) > 0 ? 'critical-row' : '' }}">
                                            <td>{{ $row['product_name'] }}</td>
                                            <td>{{ number_format($row['target'], 2) }}</td>
                                            <td style="{{ ($row['actual'] ?? 0) >= ($row['expected'] ?? 0) ? 'color:#16a05d;font-weight:900;' : '' }}">
                                                {{ number_format($row['actual'], 0) }}
                                            </td>
                                            <td style="{{ ($row['gap'] ?? 0) > 0 ? 'color:#e53935;font-weight:900;' : 'color:#16a05d;font-weight:900;' }}">
                                                {{ number_format($row['gap'], 0) }}
                                            </td>
                                            <td>
                                                <span class="ai-status {{ $row['status_class'] }}">
                                                    {{ $row['status_label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- @if(!empty($summary['ai_recommendation']))
                            {!! $summary['ai_recommendation'] !!}
                        @endif --}}
                        <div style="margin-top:16px;" id="aiRecommendationBox"></div>
                    </div>

                    <div class="chat-choice-box">
                        <strong>តើអ្នកចង់បង្កើតផែនការបែបណា?</strong>
                        <div style="font-size:13px; color:#64748b; margin-top:4px;">
                            អ្នកអាចឱ្យ AI បង្កើតដោយស្វ័យប្រវត្តិ ឬកំណត់ផែនការដោយដៃ។
                        </div>

                        <div class="quick-replies">
                            <button class="primary quick-btn" data-kind="auto_generate" data-value="auto">
                                ⚡ Auto Generate Plan
                            </button>

                            <button class="quick-btn" data-kind="manual_start" data-value="manual">
                                🛠 Manual Setup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="chat-msg assistant">
                <div class="chat-bubble">
                    មិនទាន់មាន Target សកម្មសម្រាប់ថ្ងៃនេះទេ។ សូមឱ្យ Admin កំណត់ Sale Target នៅ Settings មុនសិន។
                </div>
            </div>
        @endif
    </div>

    <div class="chat-input-wrap">
        <input type="text" id="chatInput" placeholder="សួរបន្ថែម ឬប្រើប៊ូតុងខាងលើ...">
        <button type="button" id="chatSendBtn">➤</button>
    </div>
</div>

<div class="modal-scp-backdrop" id="manualModalBackdrop">
    <div class="modal-scp">
        <div class="modal-scp-header">
            <strong>✏️ Draft Task Plan (Manual)</strong>
            <button class="modal-close" type="button" onclick="closeManualModal()">✕</button>
        </div>

        <div class="modal-scp-body">
            <div style="border:1px solid #e7edf7; border-radius:12px; padding:14px; margin-bottom:14px; background:#fff;" id="manualFilterPreview"></div>

            {{-- Search & Add Customer moved to top of customer list --}}
            <div style="margin-bottom:14px;">
                <h5 style="font-size:14px; font-weight:800; margin-bottom:8px; color:#2f3d6b;">
                    + SEARCH & ADD CUSTOMER
                </h5>

                <input type="text"
                    class="scp-input"
                    style="width:100%;"
                    id="customerSearchInput"
                    placeholder="Type customer name or phone number here...">

                <div style="border:1px solid #e7edf7; border-radius:12px; margin-top:8px; background:#fff;"
                    id="customerSearchResult"></div>
            </div>

            {{-- Customer list now below search --}}
            <div id="manualDraftList"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                <button class="scp-btn-light" type="button" onclick="closeManualModal()">Cancel</button>
                <button class="scp-btn-success" type="button" id="btnApplyManualBoard">✔ Apply to Board</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-scp-backdrop" id="logModalBackdrop">
    <div class="modal-scp log-modal-box">
        <div class="modal-scp-header">
            <strong>📋 Log Task Outcome</strong>
            <button class="modal-close" type="button" onclick="closeLogModal()">✕</button>
        </div>

        <div class="modal-scp-body log-modal-body">
            <div class="log-customer-title" id="logTaskTitle">
                Customer - Task
            </div>

            <form id="logTaskForm">
                @csrf

                <input type="hidden" id="logTaskId">

                <div class="log-form-group">
                    <label>TASK RESULT</label>
                    <select class="scp-select log-select" id="logResult" required>
                        <option value="">Select an outcome...</option>
                    </select>
                </div>

                <div class="log-form-group" id="callbackWrap" style="display:none;">
                    <label>SCHEDULE CALLBACK</label>
                    <input type="datetime-local" class="scp-input log-input" id="logCallbackAt">
                </div>

                <div class="log-form-group">
                    <label>NOTES & UPDATES</label>
                    <textarea class="scp-input log-textarea" id="logNote"></textarea>
                </div>

                <div class="log-modal-footer">
                    <button class="scp-btn-light" type="button" onclick="closeLogModal()">Cancel</button>
                    <button class="scp-btn-success" type="submit">💾 Save Log</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    const generateBoardUrl = @json(route('smart-call-plans.generate-board'));
    const manualDraftUrl = @json(route('smart-call-plans.manual-draft'));
    const manualApplyUrl = @json(route('smart-call-plans.manual-apply'));
    const searchCustomersUrl = @json(route('smart-call-plans.search-customers'));
    const taskBaseUrl = @json(url('smart-call-plans/task'));
    const moveTaskBaseUrl = @json(url('smart-call-plans/task'));
    const csrfToken = @json(csrf_token());

    const productOptions = @json($productOptions);
    const teamOptions = @json($teamOptions);
    const areaOptions = {
        provinces: @json($provinces ?? []),
        districts: @json($districts ?? []),
        communes: @json($communes ?? []),
    };

    const chatDrawer = document.getElementById('chatDrawer');
    const chatFab = document.getElementById('chatFab');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const chatBody = document.getElementById('chatBody');
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');

    const manualModalBackdrop = document.getElementById('manualModalBackdrop');
    const manualFilterPreview = document.getElementById('manualFilterPreview');
    const manualDraftList = document.getElementById('manualDraftList');

    const aiRecommendationUrl = @json(route('smart-call-plans.ai-recommendation'));
    const draftAiReasonsUrl = @json(route('smart-call-plans.draft-ai-reasons'));


    let manualState = resetManualState();
    let manualDraftItems = [];

    function resetManualState() {
    return {
        step: null,

        area_filter: {
            is_all_area: true,

            province_ids: [],
            district_ids: [],
            commune_ids: [],

            province_names: [],
            district_names: [],
            commune_names: [],

            labels: ['ទាំងអស់'],
        },

        customer_segments: [],
        customer_segment_labels: [],

        count: null,

        product_ids: [],
        product_names: [],

        assigned_to_ids: [],
        assigned_names: [],

        task_types: [],
    };
}

    let aiRecommendationLoaded = false;
let aiRecommendationLoading = false;

chatFab?.addEventListener('click', () => {
    chatDrawer.classList.toggle('open');

    if (chatDrawer.classList.contains('open') && !aiRecommendationLoaded && !aiRecommendationLoading) {
        loadAiRecommendationOnClick();
    }
});

async function loadAiRecommendationOnClick() {
    aiRecommendationLoading = true;

    const box = document.getElementById('aiRecommendationBox');

    if (box) {
        box.innerHTML = `
            <div class="ai-plan-box">
                <p>AI កំពុងវិភាគទិន្នន័យ សូមរង់ចាំបន្តិច...</p>
            </div>
        `;
    }

    try {
        const response = await fetch(aiRecommendationUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        });

        const data = await response.json();

        aiRecommendationLoaded = true;

        if (data.html && box) {
            box.innerHTML = data.html;
        } else if (box) {
            box.innerHTML = '';
        }

    } catch (error) {
        if (box) {
            box.innerHTML = '';
        }
    } finally {
        aiRecommendationLoading = false;
    }
}

    chatCloseBtn?.addEventListener('click', () => {
        chatDrawer.classList.remove('open');
    });

    document.querySelectorAll('.quick-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            handleQuickReply(btn.dataset.kind, btn.dataset.value, btn.innerText.trim());
        });
    });

    function addAssistantMessage(html) {
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-msg assistant';
        wrapper.innerHTML = `<div class="chat-bubble">${html}</div>`;
        chatBody.appendChild(wrapper);
        chatBody.scrollTop = chatBody.scrollHeight;
        bindDynamicQuickBtns();
    }

    function addUserMessage(text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-msg user';
        wrapper.innerHTML = `<div class="chat-bubble">${escapeHtml(text)}</div>`;
        chatBody.appendChild(wrapper);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function bindDynamicQuickBtns() {
    document.querySelectorAll('.dynamic-quick-btn').forEach(btn => {
        if (!btn.dataset.bound) {
            btn.dataset.bound = '1';

            btn.addEventListener('click', () => {
                if (btn.classList.contains('multi-choice-btn')) {
                    btn.classList.toggle('selected');
                    return;
                }

                handleQuickReply(btn.dataset.kind, btn.dataset.value, btn.innerText.trim());
            });
        }
    });
}

    function getSelectedValues(groupName) {
        return Array.from(document.querySelectorAll(`[data-group="${groupName}"].selected`))
            .map(btn => btn.dataset.value);
    }

    function getSelectedLabels(groupName) {
        return Array.from(document.querySelectorAll(`[data-group="${groupName}"].selected`))
            .map(btn => btn.innerText.trim());
    }

    function requireSelection(groupName, message) {
        const values = getSelectedValues(groupName);

        if (!values.length) {
            alert(message);
            return null;
        }

        return values;
    }

    function getCustomerSegmentLabel(value) {
        const labels = {
            inactive_7_days: 'អតិថិជនមិនបានទិញ 7 ថ្ងៃ',
            daily_buyers: 'អតិថិជនទិញរាល់ថ្ងៃ',
            combined_customers: 'អតិថិជនទាំងអស់',
        };

        return labels[value] || value;
    }
    

function toggleAreaDropdown(type) {
    const dropdownId = type + 'Dropdown';

    document.querySelectorAll('.area-dropdown').forEach(dropdown => {
        if (dropdown.id !== dropdownId) {
            dropdown.classList.remove('show');
        }
    });

    document.getElementById(dropdownId)?.classList.toggle('show');
}

function selectAreaOption(button) {
    const type = button.dataset.areaType;
    const id = button.dataset.areaId;
    const label = button.dataset.areaLabel;

    manualState.area_filter.is_all_area = false;
    manualState.area_filter.labels = manualState.area_filter.labels.filter(item => item !== 'ទាំងអស់');

    const idKey = type + '_ids';
    const nameKey = type + '_names';

    if (!manualState.area_filter[idKey]) {
        manualState.area_filter[idKey] = [];
    }

    if (!manualState.area_filter[nameKey]) {
        manualState.area_filter[nameKey] = [];
    }

    const exists = manualState.area_filter[idKey].includes(id);

    if (exists) {
        manualState.area_filter[idKey] = manualState.area_filter[idKey].filter(value => value !== id);
        manualState.area_filter[nameKey] = manualState.area_filter[nameKey].filter(value => value !== label);
    } else {
        manualState.area_filter[idKey].push(id);
        manualState.area_filter[nameKey].push(label);
    }

    renderAreaTags(type);
    document.getElementById(type + 'Dropdown')?.classList.remove('show');
}
function selectAreaOption(button) {
    const type = String(button.dataset.areaType || '');
    const id = String(button.dataset.areaId || '');
    const label = String(button.dataset.areaLabel || '');

    manualState.area_filter.is_all_area = false;
    manualState.area_filter.labels = manualState.area_filter.labels.filter(item => item !== 'ទាំងអស់');

    const idKey = type + '_ids';
    const nameKey = type + '_names';

    const exists = manualState.area_filter[idKey].includes(id);

    if (exists) {
        manualState.area_filter[idKey] = manualState.area_filter[idKey].filter(value => value !== id);
        manualState.area_filter[nameKey] = manualState.area_filter[nameKey].filter(value => value !== label);
    } else {
        manualState.area_filter[idKey].push(id);
        manualState.area_filter[nameKey].push(label);
    }

    if (type === 'province') {
        manualState.area_filter.district_ids = [];
        manualState.area_filter.district_names = [];
        manualState.area_filter.commune_ids = [];
        manualState.area_filter.commune_names = [];

        renderAreaTags('province');
        renderAreaTags('district');
        renderAreaTags('commune');

        renderDistrictDropdown();
        renderCommuneDropdown();
    }

    if (type === 'district') {
        manualState.area_filter.commune_ids = [];
        manualState.area_filter.commune_names = [];

        renderAreaTags('district');
        renderAreaTags('commune');

        renderCommuneDropdown();
    }

    if (type === 'commune') {
        renderAreaTags('commune');
    }

    document.getElementById(type + 'Dropdown')?.classList.remove('show');
}
function removeAreaTag(type, id, label) {
    const idKey = type + '_ids';
    const nameKey = type + '_names';

    manualState.area_filter[idKey] = (manualState.area_filter[idKey] || []).filter(value => value !== id);
    manualState.area_filter[nameKey] = (manualState.area_filter[nameKey] || []).filter(value => value !== label);

    const hasAnySelected =
        manualState.area_filter.province_ids.length ||
        manualState.area_filter.district_ids.length ||
        manualState.area_filter.commune_ids.length;

    if (!hasAnySelected) {
        manualState.area_filter.is_all_area = true;
        manualState.area_filter.labels = ['ទាំងអស់'];
    }

    renderAreaTags(type);
}
function renderAreaTags(type) {
    const idKey = type + '_ids';
    const nameKey = type + '_names';
    const boxId = type + 'SelectedTags';

    const box = document.getElementById(boxId);

    if (!box) {
        return;
    }

    if (manualState.area_filter.is_all_area && type === 'province') {
        box.innerHTML = `<span class="area-tag">ទាំងអស់</span>`;
        return;
    }

    if (manualState.area_filter.is_all_area && type !== 'province') {
        box.innerHTML = `<span class="area-placeholder">ជ្រើសរើស...</span>`;
        return;
    }

    const ids = manualState.area_filter[idKey] || [];
    const names = manualState.area_filter[nameKey] || [];

    if (!ids.length) {
        box.innerHTML = `<span class="area-placeholder">ជ្រើសរើស...</span>`;
        return;
    }

    box.innerHTML = ids.map((id, index) => {
        const label = names[index] || id;

        return `
            <span class="area-tag">
                ${escapeHtml(label)}
                <b onclick="event.stopPropagation(); removeAreaTag('${type}', '${escapeJs(id)}', '${escapeJs(label)}')">×</b>
            </span>
        `;
    }).join('');
}
function filterAreaOptions(input, dropdownId) {
    const keyword = String(input.value || '').toLowerCase();
    const dropdown = document.getElementById(dropdownId);

    dropdown?.querySelectorAll('.area-option-item').forEach(item => {
        const text = String(item.innerText || '').toLowerCase();
        item.style.display = text.includes(keyword) ? '' : 'none';
    });
}

function collectSelectedAreaFilter() {
    const allLabels = [
        ...(manualState.area_filter.province_names || []),
        ...(manualState.area_filter.district_names || []),
        ...(manualState.area_filter.commune_names || []),
    ];

    if (manualState.area_filter.is_all_area || !allLabels.length) {
        return {
            is_all_area: true,

            province_ids: [],
            district_ids: [],
            commune_ids: [],

            province_names: [],
            district_names: [],
            commune_names: [],

            labels: ['ទាំងអស់'],
        };
    }

    return {
        is_all_area: false,

        province_ids: manualState.area_filter.province_ids || [],
        district_ids: manualState.area_filter.district_ids || [],
        commune_ids: manualState.area_filter.commune_ids || [],

        province_names: manualState.area_filter.province_names || [],
        district_names: manualState.area_filter.district_names || [],
        commune_names: manualState.area_filter.commune_names || [],

        labels: allLabels,
    };
}
function areaOptionLabel(item) {
    const kh = item.name_kh || '';
    const en = item.name_en || '';

    if (kh && en) {
        return `${kh} (${en})`;
    }

    return kh || en || item.name || '';
}
    function areaButtonHtml(type, id, label, extra = {}) {
    const provinceId = extra.province_id || '';
    const districtId = extra.district_id || '';

    return `
        <button type="button"
                class="area-option-item"
                data-area-type="${type}"
                data-area-id="${escapeHtml(id)}"
                data-area-label="${escapeHtml(label)}"
                data-province-id="${escapeHtml(provinceId)}"
                data-district-id="${escapeHtml(districtId)}"
                onclick="selectAreaOption(this)">
            ${escapeHtml(label)}
        </button>
    `;
}


function showAreaQuestion() {
    manualState.step = 'area';

    const provinceOptions = (areaOptions.provinces || []).map(item =>
        areaButtonHtml('province', item.id, areaOptionLabel(item))
    ).join('');

    addAssistantMessage(`
        <div style="font-weight:900; margin-bottom:10px;">
            សំណួរ ១: សូមធ្វើការជ្រើសរើសទីតាំងរបស់អតិថិជន
        </div>

        <div class="area-select-card">
            <div class="area-card-title">📍 ទីតាំងអតិថិជន</div>

            <label class="area-label">ខេត្ត/រាជធានី <span>*</span></label>
            <div class="area-select-box" id="provinceSelectBox" onclick="toggleAreaDropdown('province')">
                <div class="area-selected-tags" id="provinceSelectedTags">
                    <span class="area-placeholder">ជ្រើសរើសខេត្ត/រាជធានី...</span>
                </div>
            </div>
            <div class="area-dropdown" id="provinceDropdown">
                <input type="text" class="area-search-input" placeholder="ស្វែងរកខេត្ត/រាជធានី..." oninput="filterAreaOptions(this, 'provinceDropdown')">
                <button type="button" class="area-option-item area-all-option" onclick="selectAllArea()">ទាំងអស់</button>
                ${provinceOptions || '<div class="area-empty">មិនមានទិន្នន័យ</div>'}
            </div>

            <label class="area-label">ស្រុក/ខណ្ឌ</label>
            <div class="area-select-box" id="districtSelectBox" onclick="toggleAreaDropdown('district')">
                <div class="area-selected-tags" id="districtSelectedTags">
                    <span class="area-placeholder">សូមជ្រើសខេត្តជាមុនសិន...</span>
                </div>
            </div>
            <div class="area-dropdown" id="districtDropdown">
                <input type="text" class="area-search-input" placeholder="ស្វែងរកស្រុក/ខណ្ឌ..." oninput="filterAreaOptions(this, 'districtDropdown')">
                <div class="area-empty">សូមជ្រើសខេត្តជាមុនសិន</div>
            </div>

            <label class="area-label">ឃុំ/សង្កាត់</label>
            <div class="area-select-box" id="communeSelectBox" onclick="toggleAreaDropdown('commune')">
                <div class="area-selected-tags" id="communeSelectedTags">
                    <span class="area-placeholder">សូមជ្រើសស្រុក/ខណ្ឌជាមុនសិន...</span>
                </div>
            </div>
            <div class="area-dropdown" id="communeDropdown">
                <input type="text" class="area-search-input" placeholder="ស្វែងរកឃុំ/សង្កាត់..." oninput="filterAreaOptions(this, 'communeDropdown')">
                <div class="area-empty">សូមជ្រើសស្រុក/ខណ្ឌជាមុនសិន</div>
            </div>

            <button type="button"
                    class="area-next-btn"
                    onclick="handleQuickReply('area_next', 'next', 'បន្ទាប់')">
                បន្ទាប់ ✓
            </button>
        </div>
    `);

    renderAreaTags('province');
    renderAreaTags('district');
    renderAreaTags('commune');
}

function renderDistrictDropdown() {
    const selectedProvinceIds = manualState.area_filter.province_ids || [];
    const dropdown = document.getElementById('districtDropdown');

    if (!dropdown) {
        return;
    }

    if (!selectedProvinceIds.length || manualState.area_filter.is_all_area) {
        dropdown.innerHTML = `
            <input type="text" class="area-search-input" placeholder="ស្វែងរកស្រុក/ខណ្ឌ..." oninput="filterAreaOptions(this, 'districtDropdown')">
            <div class="area-empty">សូមជ្រើសខេត្តជាមុនសិន</div>
        `;
        return;
    }

    const districtOptions = (areaOptions.districts || [])
        .filter(item => selectedProvinceIds.includes(String(item.province_id)))
        .map(item => areaButtonHtml('district', item.id, areaOptionLabel(item), {
            province_id: item.province_id
        }))
        .join('');

    dropdown.innerHTML = `
        <input type="text" class="area-search-input" placeholder="ស្វែងរកស្រុក/ខណ្ឌ..." oninput="filterAreaOptions(this, 'districtDropdown')">
        ${districtOptions || '<div class="area-empty">មិនមានស្រុក/ខណ្ឌសម្រាប់ខេត្តនេះទេ</div>'}
    `;
}
function renderCommuneDropdown() {
    const selectedDistrictIds = manualState.area_filter.district_ids || [];
    const dropdown = document.getElementById('communeDropdown');

    if (!dropdown) {
        return;
    }

    if (!selectedDistrictIds.length || manualState.area_filter.is_all_area) {
        dropdown.innerHTML = `
            <input type="text" class="area-search-input" placeholder="ស្វែងរកឃុំ/សង្កាត់..." oninput="filterAreaOptions(this, 'communeDropdown')">
            <div class="area-empty">សូមជ្រើសស្រុក/ខណ្ឌជាមុនសិន</div>
        `;
        return;
    }

    const communeOptions = (areaOptions.communes || [])
        .filter(item => selectedDistrictIds.includes(String(item.district_id)))
        .map(item => areaButtonHtml('commune', item.id, areaOptionLabel(item), {
            province_id: item.province_id,
            district_id: item.district_id
        }))
        .join('');

    dropdown.innerHTML = `
        <input type="text" class="area-search-input" placeholder="ស្វែងរកឃុំ/សង្កាត់..." oninput="filterAreaOptions(this, 'communeDropdown')">
        ${communeOptions || '<div class="area-empty">មិនមានឃុំ/សង្កាត់សម្រាប់ស្រុក/ខណ្ឌនេះទេ</div>'}
    `;
}


    function showCustomerSegmentQuestion() {
        manualState.step = 'customer_segment';

        addAssistantMessage(`
            <div style="font-weight:800; margin-bottom:8px;">
                សំណួរ ២: តើអ្នកចង់ជ្រើសអតិថិជនប្រភេទណាខ្លះ?
            </div>

            <div class="multi-help-text">
                អាចជ្រើសបានច្រើន។ បន្ទាប់ពីជ្រើសរួច សូមចុច Next។
            </div>

            <div class="quick-replies">
                <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="customer_segment" data-value="inactive_7_days">
                    មិនបានទិញ 7 ថ្ងៃ
                </button>

                <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="customer_segment" data-value="daily_buyers">
                    ទិញរាល់ថ្ងៃ
                </button>

                <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="customer_segment" data-value="combined_customers">
                    ទាំងអស់
                </button>

                <button type="button" class="dynamic-quick-btn quick-next-btn" data-kind="customer_segment_next" data-value="next">
                    Next
                </button>
            </div>
        `);
    }

    function showCustomerCountQuestion() {
        manualState.step = 'count';

        addAssistantMessage(`
            <div style="font-weight:800; margin-bottom:8px;">
                សំណួរ ៣: តើចង់កំណត់ចំនួនអតិថិជនប៉ុន្មាននាក់?
            </div>

            <div class="multi-help-text">
                ចំនួននេះប្រើសម្រាប់បង្កើត Draft Plan។
            </div>

            <div class="quick-replies">
                <button class="dynamic-quick-btn" data-kind="count" data-value="10">10 នាក់</button>
                <button class="dynamic-quick-btn" data-kind="count" data-value="30">30 នាក់</button>
                <button class="dynamic-quick-btn" data-kind="count" data-value="60">60 នាក់</button>
                <button class="dynamic-quick-btn" data-kind="count" data-value="90">90 នាក់</button>
            </div>

            <div style="display:flex; gap:8px; margin-top:12px;">
                <input type="number"
                       id="manualCustomCountInput"
                       class="scp-input"
                       min="1"
                       max="500"
                       placeholder="Input custom count..."
                       style="flex:1; height:38px;">

                <button type="button"
                        class="scp-btn-primary"
                        style="height:38px; min-height:38px;"
                        onclick="submitManualCustomCount()">
                    Apply
                </button>
            </div>
        `);
    }

    function showProductQuestion() {
        manualState.step = 'product';

        const buttons = productOptions.map(p => `
            <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="product" data-value="${p.id}">
                ${escapeHtml(p.name)}
            </button>
        `).join('');

        addAssistantMessage(`
            <div style="font-weight:800; margin-bottom:8px;">
                សំណួរ ៤: តើចង់ផ្តោតលើផលិតផលណាខ្លះ?
            </div>

            <div class="multi-help-text">
                អាចជ្រើសបានច្រើនផលិតផល។ បន្ទាប់ពីជ្រើសរួច សូមចុច Next។
            </div>

            <div class="quick-replies">
                ${buttons}
                <button type="button" class="dynamic-quick-btn quick-next-btn" data-kind="product_next" data-value="next">
                    Next
                </button>
            </div>
        `);
    }

    function showAssigneeQuestion() {
        manualState.step = 'assignee';

        const buttons = teamOptions.map(u => `
            <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="assignee" data-value="${u.id}">
                ${escapeHtml(u.name)}
            </button>
        `).join('');

        addAssistantMessage(`
            <div style="font-weight:800; margin-bottom:8px;">
                សំណួរ ៥: តើចង់ Assign ឱ្យអ្នកលក់ណាខ្លះ?
            </div>

            <div class="multi-help-text">
                អាចជ្រើសបានច្រើននាក់។ បន្ទាប់ពីជ្រើសរួច សូមចុច Next។
            </div>

            <div class="quick-replies">
                ${buttons}
                <button type="button" class="dynamic-quick-btn quick-next-btn" data-kind="assignee_next" data-value="next">
                    Next
                </button>
            </div>
        `);
    }

    function showTaskTypeQuestion() {
        manualState.step = 'task_type';

        addAssistantMessage(`
            <div style="font-weight:800; margin-bottom:8px;">
                សំណួរ ៦: តើត្រូវការ Call ឬ Visit?
            </div>

            <div class="multi-help-text">
                អាចជ្រើស Call, Visit ឬជ្រើសទាំងពីរ។ បន្ទាប់មកចុច Generate Draft។
            </div>

            <div class="quick-replies">
                <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="task_type" data-value="call">
                    📞 Call
                </button>

                <button type="button" class="dynamic-quick-btn multi-choice-btn" data-group="task_type" data-value="visit">
                    📍 Visit
                </button>

                <button type="button" class="dynamic-quick-btn quick-next-btn" data-kind="task_type_next" data-value="next">
                    Generate Draft
                </button>
            </div>
        `);
    }

    async function handleQuickReply(kind, value, label) {
        if (!kind) {
            return;
        }

        const nextKinds = [
        'area_next',
        'customer_segment_next',
        'product_next',
        'assignee_next',
        'task_type_next',
    ];

        if (!nextKinds.includes(kind)) {
            addUserMessage(label);
        }

        if (kind === 'auto_generate') {
            await autoGeneratePlan();
            return;
        }

        if (kind === 'manual_start') {
            manualState = resetManualState();
            manualState.step = 'area';

            showAreaQuestion();
            return;
        }

        if (kind === 'area_next') {
            manualState.area_filter = collectSelectedAreaFilter();

            addUserMessage('ទីតាំង: ' + manualState.area_filter.labels.join(', '));

            showCustomerSegmentQuestion();
            return;
        }
        if (kind === 'customer_segment_next') {
            const values = requireSelection('customer_segment', 'Please select at least one customer type.');

            if (!values) {
                return;
            }

            manualState.customer_segments = values;
            manualState.customer_segment_labels = getSelectedLabels('customer_segment');

            addUserMessage(manualState.customer_segment_labels.join(', '));
            showCustomerCountQuestion();
            return;
        }

        if (kind === 'count') {
            manualState.count = parseInt(value, 10);
            showProductQuestion();
            return;
        }

        if (kind === 'product_next') {
            const values = requireSelection('product', 'Please select at least one product.');

            if (!values) {
                return;
            }

            manualState.product_ids = values;
            manualState.product_names = getSelectedLabels('product');

            addUserMessage(manualState.product_names.join(', '));
            showAssigneeQuestion();
            return;
        }

        if (kind === 'assignee_next') {
            const values = requireSelection('assignee', 'Please select at least one assignee.');

            if (!values) {
                return;
            }

            manualState.assigned_to_ids = values;
            manualState.assigned_names = getSelectedLabels('assignee');

            addUserMessage(manualState.assigned_names.join(', '));
            showTaskTypeQuestion();
            return;
        }

        if (kind === 'task_type_next') {
            const values = requireSelection('task_type', 'Please select Call, Visit, or both.');

            if (!values) {
                return;
            }

            manualState.task_types = values;

            addUserMessage(getSelectedLabels('task_type').join(', '));
            await buildManualDraft();
        }
    }
    



    function submitManualCustomCount() {
        const input = document.getElementById('manualCustomCountInput');
        const value = parseInt(input?.value || '0', 10);

        if (!value || value <= 0) {
            alert('Please input valid task count.');
            return;
        }

        if (value > 500) {
            alert('Maximum task count is 500.');
            return;
        }

        manualState.count = value;
        addUserMessage(`${value} នាក់`);
        showProductQuestion();
    }

    async function autoGeneratePlan() {
        try {
            addAssistantMessage('ខ្ញុំកំពុងរៀបចំ Targeted Plan ជាមុនសិន...');

            const response = await fetch(generateBoardUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    customer_segment: 'combined_customers',
                    customer_segments: ['combined_customers'],
                })
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                addAssistantMessage(`មិនអាចបង្កើត Draft Plan បានទេ។ ${escapeHtml(result.message ?? '')}`);
                return;
            }

            const draft = result.data;

            if (!draft || !draft.filters) {
                addAssistantMessage('មិនមាន Draft Plan ត្រឡប់មកវិញទេ។ សូមពិនិត្យ Controller response។');
                return;
            }

            manualState = resetManualState();
            manualState.step = 'auto_preview';
            manualState.customer_segments = draft.filters.customer_segments || [draft.filters.customer_segment || 'combined_customers'];
            manualState.customer_segment_labels = draft.filters.customer_segment_labels || manualState.customer_segments.map(getCustomerSegmentLabel);
            manualState.count = draft.filters.count || 0;
            manualState.product_ids = draft.filters.product_ids || (draft.filters.product_id ? [draft.filters.product_id] : []);
            manualState.product_names = draft.filters.product_names || (draft.filters.product_name ? [draft.filters.product_name] : []);
            manualState.assigned_to_ids = draft.filters.assigned_to_ids || (draft.filters.assigned_to ? [draft.filters.assigned_to] : []);
            manualState.assigned_names = draft.filters.assigned_names || (draft.filters.assigned_name ? [draft.filters.assigned_name] : []);
            manualState.task_types = draft.filters.task_types || [draft.filters.task_type || 'call'];

            manualDraftItems = draft.items || [];

            renderAutoDraftModal(draft.filters);

            addAssistantMessage(
                `Targeted Plan បានរៀបចំរួចហើយ ចំនួន <strong>${manualDraftItems.length}</strong> customers។ សូមពិនិត្យ ហើយចុច Apply to Board។`
            );
        } catch (e) {
            console.error(e);
            addAssistantMessage('មានបញ្ហាក្នុងការរៀបចំ Targeted Plan។');
        }
    }

    async function buildManualDraft() {
        try {
            addAssistantMessage(`
                ខ្ញុំកំពុងរៀបចំ Draft Plan សម្រាប់
                <strong>${escapeHtml(manualState.product_names.join(', '))}</strong>...
            `);

            const response = await fetch(manualDraftUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
    is_all_area: manualState.area_filter.is_all_area,

    province_ids: manualState.area_filter.province_ids,
    district_ids: manualState.area_filter.district_ids,
    commune_ids: manualState.area_filter.commune_ids,

    province_names: manualState.area_filter.province_names,
    district_names: manualState.area_filter.district_names,
    commune_names: manualState.area_filter.commune_names,

    customer_segments: manualState.customer_segments,
    customer_segment: manualState.customer_segments[0] || 'combined_customers',

    count: manualState.count,

    product_ids: manualState.product_ids,
    product_id: manualState.product_ids[0] || null,

    assigned_to_ids: manualState.assigned_to_ids,
    assigned_to: manualState.assigned_to_ids[0] || null,

    task_types: manualState.task_types,
    task_type: manualState.task_types[0] || 'call',
})
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                addAssistantMessage(`មិនអាចបង្កើត Draft បានទេ។ ${escapeHtml(result.message ?? '')}`);
                return;
            }

            manualDraftItems = result.data.items || [];

            const filters = result.data.filters || {};

            filters.customer_segments = manualState.customer_segments;
            filters.customer_segment_labels = manualState.customer_segment_labels;
            filters.product_ids = manualState.product_ids;
            filters.product_names = manualState.product_names;
            filters.assigned_to_ids = manualState.assigned_to_ids;
            filters.assigned_names = manualState.assigned_names;
            filters.task_types = manualState.task_types;
            filters.count = manualState.count;

            renderManualDraftModal(filters);
            addAssistantMessage(`Draft Plan បានត្រៀមរួចរាល់។ សូមពិនិត្យក្នុង Modal ហើយចុច Apply to Board។`);
        } catch (e) {
            console.error(e);
            addAssistantMessage('មានបញ្ហាក្នុងការបង្កើត Draft Manual Plan។');
        }
    }

    function renderManualDraftModal(filters) {
        const todayDate = new Date().toISOString().slice(0, 10);

        const customerLabels = filters.customer_segment_labels || manualState.customer_segment_labels || [];
        const productNames = filters.product_names || manualState.product_names || [];
        const assignedNames = filters.assigned_names || manualState.assigned_names || [];
        const taskTypes = filters.task_types || manualState.task_types || [];
        const areaLabels = manualState.area_filter?.labels || ['ទាំងអស់'];
        manualFilterPreview.innerHTML = `
            <div class="manual-form-grid">
                <div class="manual-form-group full">
                    <label>TASK TITLE <span>*</span></label>
                    <input type="text"
                           id="manualTaskTitle"
                           class="scp-input"
                           style="width:100%;"
                           placeholder="Optional task title..."
                           value="">
                </div>

                <div class="manual-form-group">
                    <label>PLAN DATE <span>*</span></label>
                    <input type="date"
                           id="manualPlanDate"
                           class="scp-input"
                           style="width:100%;"
                           value="${todayDate}">
                </div>

                <div class="manual-form-group">
                    <label>DEFAULT TASK TYPE <span>*</span></label>
                    <select id="manualTaskTypeSelect"
                            class="scp-select"
                            style="width:100%;">
                        <option value="call" ${taskTypes.includes('call') ? 'selected' : ''}>📞 Call Plan</option>
                        <option value="visit" ${taskTypes.includes('visit') && !taskTypes.includes('call') ? 'selected' : ''}>📍 Visit Plan</option>
                    </select>
                </div>
            </div>

            <div class="manual-filter-summary">
                ⚙️ Manual Filters:
                ទីតាំង: ${escapeHtml(areaLabels.join(', ') || 'ទាំងអស់')}
                | Customer Type: ${escapeHtml(customerLabels.join(', ') || '-')}
                | Focus: ${escapeHtml(productNames.join(', ') || filters.product_name || '-')}
                | Count: ${escapeHtml(filters.count || manualDraftItems.length || 0)}
                | Assignee: ${escapeHtml(assignedNames.join(', ') || filters.assigned_name || '-')}
                | Task: ${escapeHtml(taskTypes.join(', ') || filters.task_type || '-')}
            </div>
        `;

        const titleEl = document.querySelector('#manualModalBackdrop .modal-scp-header strong');
        if (titleEl) {
            titleEl.innerText = '✏️ Draft Task Plan (Manual)';
        }

        manualState.customer_segments = filters.customer_segments || manualState.customer_segments || [];
        manualState.customer_segment_labels = customerLabels;
        manualState.product_ids = filters.product_ids || manualState.product_ids || [];
        manualState.product_names = productNames;
        manualState.assigned_to_ids = filters.assigned_to_ids || manualState.assigned_to_ids || [];
        manualState.assigned_names = assignedNames;
        manualState.task_types = taskTypes.length ? taskTypes : ['call'];

        renderManualDraftList();
        document.body.appendChild(manualModalBackdrop);
        manualModalBackdrop.classList.add('show');

        loadAiReasonsForDraftItems(filters);

        document.getElementById('manualTaskTypeSelect')?.addEventListener('change', function () {
            if (!manualState.task_types.length) {
                manualState.task_types = [this.value];
            }
        });
    }

    function renderAutoDraftModal(filters) {
        filters.customer_segments = filters.customer_segments || [filters.customer_segment || 'combined_customers'];
        filters.customer_segment_labels = filters.customer_segment_labels || filters.customer_segments.map(getCustomerSegmentLabel);
        filters.product_ids = filters.product_ids || (filters.product_id ? [filters.product_id] : []);
        filters.product_names = filters.product_names || (filters.product_name ? [filters.product_name] : []);
        filters.assigned_to_ids = filters.assigned_to_ids || (filters.assigned_to ? [filters.assigned_to] : []);
        filters.assigned_names = filters.assigned_names || (filters.assigned_name ? [filters.assigned_name] : []);
        filters.task_types = filters.task_types || ['AI decides per customer'];

        const todayDate = new Date().toISOString().slice(0, 10);
        const defaultTitle = `Targeted Plan: ${filters.product_names.join(', ') || 'Close Gap'}`;

        manualFilterPreview.innerHTML = `
            <div class="manual-form-grid">
                <div class="manual-form-group full">
                    <label>TASK TITLE <span>*</span></label>
                    <input type="text"
                           id="manualTaskTitle"
                           class="scp-input"
                           style="width:100%;"
                           placeholder="e.g. Targeted Plan: Close Gap"
                           value="${escapeHtml(defaultTitle)}">
                </div>

                <div class="manual-form-group">
                    <label>PLAN DATE <span>*</span></label>
                    <input type="date"
                           id="manualPlanDate"
                           class="scp-input"
                           style="width:100%;"
                           value="${todayDate}">
                </div>

                <div class="manual-form-group">
                    <label>DEFAULT TASK TYPE <span>*</span></label>
                    <select id="manualTaskTypeSelect"
                            class="scp-select"
                            style="width:100%;">
                        <option value="call" ${filters.task_types.includes('call') ? 'selected' : ''}>📞 Call Plan</option>
                        <option value="visit" ${filters.task_types.includes('visit') && !filters.task_types.includes('call') ? 'selected' : ''}>📍 Visit Plan</option>
                    </select>
                </div>
            </div>

            <div style="background:#fff0f0; border:1px solid #ffb7b7; color:#c62828; border-radius:10px; padding:12px;">
                <div style="font-weight:900; margin-bottom:4px;">
                    <i class="fa fa-bullseye"></i>
                    AI Goal: ${escapeHtml(filters.ai_goal || 'Target Gap Recovery')}
                </div>

                <div style="font-weight:800;">
                    <i class="fa fa-user"></i>
                    Assigned To: ${escapeHtml(filters.assigned_names.join(', ') || filters.assigned_name || '-')}
                </div>
            </div>
        `;

        const titleEl = document.querySelector('#manualModalBackdrop .modal-scp-header strong');
        if (titleEl) {
            titleEl.innerText = '🎯 Targeted Plan: Close Gap';
        }

        manualState.customer_segments = filters.customer_segments;
        manualState.customer_segment_labels = filters.customer_segment_labels;
        manualState.product_ids = filters.product_ids;
        manualState.product_names = filters.product_names;
        manualState.assigned_to_ids = filters.assigned_to_ids;
        manualState.assigned_names = filters.assigned_names;
        manualState.task_types = filters.task_types;

        renderManualDraftList();
        document.body.appendChild(manualModalBackdrop);
        manualModalBackdrop.classList.add('show');

        loadAiReasonsForDraftItems(filters);
    }

    function renderManualDraftList() {
    const countLabel = document.getElementById('manualCustomerCountLabel');

    if (countLabel) {
        countLabel.innerText = `${manualDraftItems.length} customers`;
    }

    if (!manualDraftItems.length) {
        manualDraftList.innerHTML = `<div style="color:#718096;">No customer selected.</div>`;
        return;
    }

    manualDraftList.innerHTML = manualDraftItems.map((item, index) => {
        const taskType = item.task_type || manualState.task_types?.[0] || 'call';
        const taskTypeLabel = taskType === 'visit' ? 'Visit' : 'Call';

        const customerType = item.customer_type
            || item.customer_segment
            || 'urgent_follow_up';

        const priority = item.priority || 'high';

        return `
            <div class="manual-item">
                <div>
                    <div style="font-size:16px; font-weight:800; color:#17233c;">
                        ${escapeHtml(item.name)}
                    </div>

                    <div style="font-size:13px; color:#6b7890; line-height:1.7;">
                        ${escapeHtml(item.group || '-')}
                        | Phone: ${escapeHtml(item.phone || '-')}
                        | Assignee: ${escapeHtml((manualState.assigned_names || []).join(', ') || '-')}
                        | Task: ${escapeHtml(taskTypeLabel)}
                        | Priority: ${escapeHtml(priority)}
                    </div>

                    ${item.ai_loading ? `
                        <div style="margin-top:8px; background:#fff7e7; border-left:3px solid #f39c12; padding:8px 10px; border-radius:8px; color:#8a5a00; font-size:13px;">
                            <i class="fa fa-spinner fa-spin"></i> AI កំពុងវិភាគហេតុផល...
                        </div>
                    ` : ''}

                    ${item.ai_note ? `
                        <div style="margin-top:8px; background:#f5f7fb; border-left:3px solid #2f76df; padding:8px 10px; border-radius:8px; color:#34405a; font-size:13px;">
                            ${escapeHtml(item.ai_note)}
                        </div>
                    ` : ''}
                </div>

                <button type="button" class="task-btn task-btn-light" style="width:44px;" onclick="removeDraftItem(${index})">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        `;
    }).join('');
}
    async function loadAiReasonsForDraftItems(filters) {
    if (!manualDraftItems.length) {
        return;
    }

    if (!filters.target_id || !filters.product_id) {
        console.warn('Missing target_id or product_id for AI reason loading.', filters);
        return;
    }

    // Mark all items as loading first
    manualDraftItems = manualDraftItems.map(item => ({
        ...item,
        ai_loading: !item.ai_note,
    }));

    renderManualDraftList();

    const batchSize = 10;

    for (let i = 0; i < manualDraftItems.length; i += batchSize) {
        const batch = manualDraftItems.slice(i, i + batchSize);

        try {
            const response = await fetch(draftAiReasonsUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    target_id: filters.target_id,
                    product_id: filters.product_id || manualState.product_ids?.[0],
                    product_name: filters.product_name || manualState.product_names?.[0] || '-',
                    task_type: filters.task_type === 'visit' ? 'visit' : 'call',
                    customer_segment: filters.customer_segment || manualState.customer_segments?.[0] || 'combined_customers',
                    items: batch.map(item => ({
                        contact_id: item.contact_id,
                        name: item.name,
                        phone: item.phone,
                        task_type: item.task_type || filters.task_type || 'call',
                        customer_type: item.customer_type || filters.customer_segment || 'combined_customers',
                    })),
                })
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                batch.forEach(batchItem => {
                    const item = manualDraftItems.find(x => Number(x.contact_id) === Number(batchItem.contact_id));
                    if (item) {
                        item.ai_loading = false;
                    }
                });

                renderManualDraftList();
                continue;
            }

            const aiMap = {};

            (result.data || []).forEach(row => {
                aiMap[Number(row.contact_id)] = row;
            });

            manualDraftItems = manualDraftItems.map(item => {
                const ai = aiMap[Number(item.contact_id)];

                if (!ai) {
                    return item;
                }

                return {
                    ...item,
                    task_type: ai.task_type || item.task_type,
                    customer_type: ai.customer_type || item.customer_type,
                    priority: ai.priority_level || ai.priority || item.priority || 'high',
                    ai_note: ai.ai_note || item.ai_note,
                    ai_loading: false,
                };
            });

            // Stop loading for items in this batch even if AI missed some
            batch.forEach(batchItem => {
                const item = manualDraftItems.find(x => Number(x.contact_id) === Number(batchItem.contact_id));
                if (item) {
                    item.ai_loading = false;
                }
            });

            renderManualDraftList();

        } catch (e) {
            console.error(e);

            batch.forEach(batchItem => {
                const item = manualDraftItems.find(x => Number(x.contact_id) === Number(batchItem.contact_id));
                if (item) {
                    item.ai_loading = false;
                }
            });

            renderManualDraftList();
        }
    }
}

    function removeDraftItem(index) {
        manualDraftItems.splice(index, 1);
        renderManualDraftList();
    }

    function closeManualModal() {
        manualModalBackdrop.classList.remove('show');

        const titleEl = document.querySelector('#manualModalBackdrop .modal-scp-header strong');
        if (titleEl) {
            titleEl.innerText = '✏️ Draft Task Plan (Manual)';
        }
    }
    function selectAllArea() {
    manualState.area_filter = {
        is_all_area: true,

        province_ids: [],
        district_ids: [],
        commune_ids: [],

        province_names: [],
        district_names: [],
        commune_names: [],

        labels: ['ទាំងអស់'],
    };

    renderAreaTags('province');
    renderAreaTags('district');
    renderAreaTags('commune');
    renderDistrictDropdown();
    renderCommuneDropdown();

    document.querySelectorAll('.area-dropdown').forEach(dropdown => dropdown.classList.remove('show'));
}

    document.getElementById('btnApplyManualBoard')?.addEventListener('click', async () => {
        try {
            if (!manualDraftItems.length) {
                alert('No customer selected.');
                return;
            }

            const taskTitle = document.getElementById('manualTaskTitle')?.value || '';
            const planDate = document.getElementById('manualPlanDate')?.value || '';
            const defaultTaskType = document.getElementById('manualTaskTypeSelect')?.value || 'call';

            if (!planDate) {
                alert('Please select Plan Date.');
                return;
            }

            const response = await fetch(manualApplyUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                customer_segments: manualState.customer_segments,
                customer_segment: manualState.customer_segments?.[0] || 'combined_customers',

                product_ids: manualState.product_ids,
                product_id: manualState.product_ids?.[0] || null,

                assigned_to_ids: manualState.assigned_to_ids,
                assigned_to: manualState.assigned_to_ids?.[0] || null,

                task_types: manualState.task_types?.length ? manualState.task_types : [defaultTaskType],
                task_type: defaultTaskType,

                task_title: taskTitle.trim() || null,
                plan_date: planDate,

                contact_ids: manualDraftItems.map(x => x.contact_id),

                items: manualDraftItems.map(x => ({
                    contact_id: x.contact_id,
                    task_type: x.task_type || defaultTaskType || 'call',
                    priority: x.priority || 'high',
                    ai_note: x.ai_note || null,
                })),
        })
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                alert(result.message || 'Cannot apply board');
                return;
            }

            closeManualModal();
            addAssistantMessage(`Plan បានដាក់ចូល Board ជោគជ័យ ចំនួន <strong>${result.count}</strong> Task។`);
            setTimeout(() => window.location.reload(), 800);
        } catch (e) {
            console.error(e);
            alert('Apply board failed');
        }
    });

    let searchTimeout = null;

    document.getElementById('customerSearchInput')?.addEventListener('input', function () {
        const q = this.value.trim();
        const resultBox = document.getElementById('customerSearchResult');

        clearTimeout(searchTimeout);

        if (q.length < 2) {
            resultBox.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(async () => {
            const url = `${searchCustomersUrl}?q=${encodeURIComponent(q)}`;

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            const result = await response.json();

            if (!result.ok) {
                return;
            }

            resultBox.innerHTML = result.data.map(item => `
                <div class="search-result-item" onclick="addCustomerToDraft(${Number(item.id)}, '${escapeJs(item.name)}', '${escapeJs(item.phone || '')}')">
                    <strong>${escapeHtml(item.name)}</strong><br>
                    <small>${escapeHtml(item.phone || '-')}</small>
                </div>
            `).join('');
        }, 400);
    });

    function addCustomerToDraft(id, name, phone) {
        const exists = manualDraftItems.find(x => Number(x.contact_id) === Number(id));

        if (exists) {
            return;
        }

        manualDraftItems.push({
            contact_id: id,
            name,
            phone,
            group: '-',
            assigned_to: manualState.assigned_to_ids?.[0] || null,
            customer_segment: manualState.customer_segments?.[0] || 'manual_added'
        });

        renderManualDraftList();

        document.getElementById('customerSearchResult').innerHTML = '';
        document.getElementById('customerSearchInput').value = '';
    }

   document.querySelectorAll('.btn-open-log').forEach(btn => {
    btn.addEventListener('click', function () {
        const taskId = this.dataset.id;
        const taskName = this.dataset.name || 'Customer';
        const taskType = String(this.dataset.type || 'call').toLowerCase();

        document.getElementById('logTaskId').value = taskId;
        document.getElementById('logTaskTitle').innerText =
            `${taskName} - ${taskType === 'visit' ? 'Visit' : 'Call'}`;

        const resultSelect = document.getElementById('logResult');

        resultSelect.innerHTML = `
            <option value="">Select an outcome...</option>
            <option value="order_placed_success">Order Placed (Success)</option>
            <option value="interested_positive">Interested / Positive</option>
            <option value="request_callback">Request Callback</option>
            <option value="no_answer_busy">No Answer / Busy</option>
            <option value="not_interested">Not Interested</option>
        `;

        document.getElementById('callbackWrap').style.display = 'none';
        document.getElementById('logCallbackAt').value = '';
        document.getElementById('logNote').value = '';

        document.getElementById('logModalBackdrop').classList.add('show');
    });
});
    function closeLogModal() {
        document.getElementById('logModalBackdrop').classList.remove('show');
        document.getElementById('logTaskForm').reset();
        document.getElementById('callbackWrap').style.display = 'none';
    }

    document.getElementById('logResult')?.addEventListener('change', function () {
    const needCallback = this.value === 'request_callback';
    document.getElementById('callbackWrap').style.display = needCallback ? 'block' : 'none';
});

    document.getElementById('logTaskForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const id = document.getElementById('logTaskId').value;
        const url = `${taskBaseUrl}/${id}/log`;

        const payload = {
            result: document.getElementById('logResult').value,
            callback_at: document.getElementById('logCallbackAt').value || null,
            note: document.getElementById('logNote').value || null,
        };

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || !result.ok) {
            let message = result.message || 'Save log failed';

            if (result.errors) {
                message = Object.values(result.errors).flat().join('\n');
            }

            alert(message);
            return;
        }

        closeLogModal();
        window.location.reload();
    });

    document.querySelectorAll('.btn-skip-task').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            const url = `${taskBaseUrl}/${id}/skip`;

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                alert(result.message || 'Skip failed');
                return;
            }

            window.location.reload();
        });
    });

    document.querySelectorAll('.task-type-filter').forEach(filter => {
        filter.addEventListener('click', function () {
            document.querySelectorAll('.task-type-filter').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            applyTaskFilter();
        });
    });

    document.getElementById('priorityFilter')?.addEventListener('change', applyTaskFilter);
    document.getElementById('searchFilter')?.addEventListener('input', applyTaskFilter);

    function applyTaskFilter() {
        const activeType = document.querySelector('.task-type-filter.active')?.dataset.type || 'all';
        const priority = document.getElementById('priorityFilter')?.value || '';
        const search = (document.getElementById('searchFilter')?.value || '').toLowerCase();

        document.querySelectorAll('.task-item').forEach(item => {
            const type = item.dataset.type;
            const itemPriority = item.dataset.priority;
            const name = item.dataset.name || '';

            let show = true;

            if (activeType !== 'all' && type !== activeType) {
                show = false;
            }

            if (priority && itemPriority !== priority) {
                show = false;
            }

            if (search && !name.includes(search)) {
                show = false;
            }

            item.style.display = show ? '' : 'none';
        });
    }

    chatSendBtn?.addEventListener('click', onSendChatText);

    chatInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            onSendChatText();
        }
    });

    function onSendChatText() {
        const text = (chatInput.value || '').trim();

        if (!text) {
            return;
        }

        addUserMessage(text);
        chatInput.value = '';

        const lower = text.toLowerCase();

        if (!@json((bool) $target)) {
            addAssistantMessage(`
                មិនទាន់មាន Target សម្រាប់ថ្ងៃនេះទេ។ សូមឱ្យ Admin កំណត់ Sale Target នៅ Settings មុនសិន។
            `);
            return;
        }

        if (lower.includes('manual')) {
            handleQuickReply('manual_start', 'manual', 'Manual Setup');
            return;
        }

        if (lower.includes('auto') || lower.includes('generate')) {
            handleQuickReply('auto_generate', 'auto', 'Auto Generate Plan');
            return;
        }

        addAssistantMessage(`
            សូមប្រើប៊ូតុង <strong>Auto Generate Plan</strong> ឬ <strong>Manual Setup</strong> ដើម្បីបង្កើតផែនការ។
            <div class="quick-replies">
                <button class="dynamic-quick-btn primary" data-kind="auto_generate" data-value="auto">⚡ Auto Generate Plan</button>
                <button class="dynamic-quick-btn" data-kind="manual_start" data-value="manual">🛠 Manual Setup</button>
            </div>
        `);
    }

    let draggedTaskEl = null;
    let draggedTaskId = null;
    let originalColumn = null;

    function initDragAndDropBoard() {
    document.querySelectorAll('.task-card').forEach(card => {
        card.removeAttribute('draggable');
        card.setAttribute('draggable', 'false');
    });
   }

    function getResultLabel(value) {
    const labels = {
        order_placed_success: 'Order Placed',
        interested_positive: 'Interested',
        request_callback: 'Callback',
        no_answer_busy: 'No Answer',
        not_interested: 'Not Interested',
        skipped: 'Skipped',

        order_placed: 'Order Placed',
        sale_closed: 'Order Placed',
        no_answer: 'No Answer',
        followup: 'Callback',
        declined: 'Not Interested',
    };

    return labels[value] || value || '-';
}

function getResultClass(value) {
    const classes = {
        order_placed_success: 'result-success',
        interested_positive: 'result-interested',
        request_callback: 'result-callback',
        no_answer_busy: 'result-skipped',
        not_interested: 'result-skipped',
        skipped: 'result-skipped',
        completed: 'result-success',

        order_placed: 'result-success',
        sale_closed: 'result-success',
        no_answer: 'result-skipped',
        followup: 'result-callback',
        declined: 'result-skipped',
    };

    return classes[value] || 'result-default';
}

function updateTaskButtonsByStatus(taskEl, status, resultValue = null) {
    const actions = taskEl.querySelector('.task-actions');
    let resultBox = taskEl.querySelector('.task-result-box');

    if (['completed', 'skipped'].includes(status)) {
        if (actions) {
            actions.remove();
        }

        const result = resultValue || taskEl.dataset.result || status;
        taskEl.dataset.result = result;

        if (!resultBox) {
            resultBox = document.createElement('div');
            taskEl.appendChild(resultBox);
        }

        resultBox.className = `task-result-box ${getResultClass(result)}`;
        resultBox.innerText = getResultLabel(result);
    } else {
        if (resultBox) {
            resultBox.remove();
        }
    }
}

    function updateBoardCounts() {
        document.querySelectorAll('.board-drop-zone').forEach(column => {
            const count = column.querySelectorAll('.task-card').length;
            const countEl = column.querySelector('.board-count');

            if (countEl) {
                countEl.innerText = count;
            }

            const hasEmpty = column.querySelector('.empty-col');

            if (count === 0 && !hasEmpty) {
                const empty = document.createElement('div');
                empty.className = 'empty-col';
                empty.innerText = 'No records';
                column.appendChild(empty);
            }

            if (count > 0 && hasEmpty) {
                hasEmpty.remove();
            }
        });

        updateTopSummaryCounts();
    }

    function updateTopSummaryCounts() {
        const todo = document.querySelector('.board-drop-zone[data-status="todo"]')?.querySelectorAll('.task-card').length || 0;
        const followUp = document.querySelector('.board-drop-zone[data-status="follow_up"]')?.querySelectorAll('.task-card').length || 0;
        const completed = document.querySelector('.board-drop-zone[data-status="completed"]')?.querySelectorAll('.task-card').length || 0;
        const skipped = document.querySelector('.board-drop-zone[data-status="skipped"]')?.querySelectorAll('.task-card').length || 0;

        const total = todo + followUp + completed + skipped;
        const progress = total > 0 ? Math.round((completed / total) * 100) : 0;

        const progressFill = document.querySelector('.progress-bar-fill');
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }

        const progressText = document.querySelector('.scp-card strong[style*="color:#2f76df"]');
        if (progressText) {
            progressText.innerText = `${progress}%`;
        }
    }

    function showSmallToast(message) {
        let toast = document.getElementById('scpDragToast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'scpDragToast';
            toast.style.position = 'fixed';
            toast.style.right = '24px';
            toast.style.top = '86px';
            toast.style.zIndex = '999999';
            toast.style.background = '#17233c';
            toast.style.color = '#fff';
            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '10px';
            toast.style.boxShadow = '0 8px 20px rgba(0,0,0,.2)';
            toast.style.fontWeight = '800';
            toast.style.fontSize = '13px';
            document.body.appendChild(toast);
        }

        toast.innerText = message;
        toast.style.display = 'block';

        setTimeout(() => {
            toast.style.display = 'none';
        }, 1800);
    }

    initDragAndDropBoard();

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJs(value) {
        return String(value ?? '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '\\"')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '');
    }
</script>
@endsection