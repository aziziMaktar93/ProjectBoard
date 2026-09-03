<style>
    body, table, th, td, span, div, p { font-family: Arial, Helvetica, sans-serif; letter-spacing: 0.6px; }
    body { color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; background: #ffffff; }
    h1 { font-size: 22px; margin: 0 0 4px; color: #111827; }
    .subtitle { color: #6b7280; margin: 0 0 22px; font-size: 11px; }

    .stat-grid { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    .stat-grid td { border: none; padding: 0 10px 0 0; }
    .stat-box { border: 1px solid #e5e7eb; border-left: 3px solid #3b5bfd; border-radius: 6px; padding: 10px 12px; }
    .stat-value { font-size: 19px; font-weight: 700; display: block; color: #111827; }
    .stat-label { color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; }

    .section-title {
        font-size: 13px;
        font-weight: 700;
        color: #ffffff;
        background: #3b5bfd;
        padding: 6px 12px;
        border-radius: 6px;
        margin: 20px 0 10px;
        display: block;
    }

    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data th, table.data td { text-align: left; padding: 6px 8px; }
    table.data th {
        color: #6b7280;
        font-weight: 600;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 1px solid #d1d5db;
    }
    table.data tbody tr:nth-child(even) { background: #fafafa; }
    table.data tbody tr td { border-bottom: 1px solid #f0f0f0; }

    .pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        white-space: nowrap;
    }
    .pill-good { background: #d1fae5; color: #067a55; }
    .pill-bad { background: #fde2e2; color: #b3441f; }
    .pill-neutral { background: #e5e7eb; color: #4b5563; }

    .assignee { color: #4b5563; }
    .assignee-empty { color: #d1d5db; }

    .progress-track {
        display: inline-block;
        width: 100px;
        height: 8px;
        border-radius: 999px;
        background: #e5e7eb;
        vertical-align: middle;
        overflow: hidden;
    }
    .progress-fill {
        display: block;
        height: 100%;
        background-color: #3b5bfd;
        border-radius: 999px;
    }
    .progress-fill.low { background-color: #b3441f; }
    .progress-fill.complete { background-color: #067a55; }
    .progress-percent {
        display: inline-block;
        margin-left: 8px;
        font-weight: 700;
        font-size: 11px;
        color: #374151;
        vertical-align: middle;
    }

    .methodology {
        margin-top: 18px;
        padding: 10px 12px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        color: #6b7280;
        font-size: 10px;
        line-height: 1.6;
    }
    .methodology strong { color: #374151; }

    .muted { color: #9ca3af; font-style: italic; }
    .footer { margin-top: 28px; color: #9ca3af; font-size: 10px; }
</style>
