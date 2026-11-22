/* Chart helpers: honest defaults (zero-based axes, units on every axis, small-number notes),
   colour-blind-safe palette with a second visual cue (marker shape, dash) for every series. */
window.owCharts = (function () {
    'use strict';
    var shapes = ['circle', 'triangle', 'rect', 'rectRot', 'cross', 'star'];
    var dashes = [[], [6, 3], [2, 3], [10, 3, 2, 3], [], []];
    var SIGNAL = '#d55e00';

    function init() {
        if (!window.Chart) { return false; }
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#3f4a59';
        Chart.defaults.borderColor = '#e4e7eb';
        return true;
    }
    function fmt(v) { return v === null || v === undefined ? '' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 1 }); }
    function smallNote(floor) {
        return function (items) {
            var v = items[0] && items[0].parsed ? (items[0].parsed.y !== undefined ? items[0].parsed.y : items[0].parsed.x) : null;
            return (floor && v > 0 && v < floor) ? 'Small number (fewer than ' + floor + '): interpret with caution.' : '';
        };
    }
    function label(canvas, text) {
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', text);
    }

    /* Line chart. series: [{label, data, signals?: [bool], dashed?: bool}] */
    function line(id, cfg) {
        if (!init()) { return null; }
        var el = document.getElementById(id);
        if (!el) { return null; }
        label(el, cfg.ariaLabel || cfg.yTitle);
        var datasets = cfg.series.map(function (s, i) {
            var color = s.color || owColors[i % owColors.length];
            var sig = s.signals || [];
            return {
                label: s.label, data: s.data, borderColor: color, backgroundColor: color, borderWidth: 2, tension: 0.15, spanGaps: false,
                borderDash: s.dashed ? [6, 3] : dashes[i % dashes.length], fill: !!s.fill,
                pointStyle: sig.length ? sig.map(function (f) { return f ? 'rectRot' : shapes[i % shapes.length]; }) : shapes[i % shapes.length],
                pointRadius: sig.length ? sig.map(function (f) { return f ? 7 : 2; }) : ((cfg.points === false || s.dashed) ? 0 : 3),
                pointBackgroundColor: sig.length ? sig.map(function (f) { return f ? SIGNAL : color; }) : color,
                pointBorderColor: sig.length ? sig.map(function (f) { return f ? '#7a2f00' : color; }) : color,
                pointHoverRadius: 6
            };
        });
        return new Chart(el, {
            type: 'line', data: { labels: cfg.labels, datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true } },
                    tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y) + (cfg.unit ? ' ' + cfg.unit : ''); }, footer: smallNote(cfg.floor) } }
                },
                scales: {
                    y: { beginAtZero: true, suggestedMax: cfg.yMax, ticks: { precision: cfg.integer === false ? undefined : 0 }, title: { display: true, text: cfg.yTitle } },
                    x: { grid: { display: false }, title: { display: !!cfg.xTitle, text: cfg.xTitle }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });
    }

    /* Bar chart (vertical or horizontal). cfg.series: [{label, data}] */
    function bar(id, cfg) {
        if (!init()) { return null; }
        var el = document.getElementById(id);
        if (!el) { return null; }
        label(el, cfg.ariaLabel || cfg.yTitle);
        var datasets = cfg.series.map(function (s, i) {
            var color = s.color || owColors[i % owColors.length];
            return { label: s.label, data: s.data, backgroundColor: s.colors || color, borderColor: s.colors || color, borderWidth: 1, borderRadius: 3, maxBarThickness: 32,
                type: s.type, order: s.order };
        });
        var horizontal = !!cfg.horizontal;
        var value = { beginAtZero: true, title: { display: true, text: cfg.yTitle }, grid: { color: '#eff1f4' }, suggestedMax: cfg.yMax, ticks: { precision: cfg.integer === false ? undefined : 0 } };
        var cat = { grid: { display: false }, ticks: { autoSkip: false } };
        var chart = new Chart(el, {
            type: 'bar', data: { labels: cfg.labels, datasets: datasets },
            options: {
                indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false,
                onClick: cfg.onClick ? function (e, els) { if (els.length) { cfg.onClick(els[0].index); } } : undefined,
                onHover: cfg.onClick ? function (e, els) { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; } : undefined,
                plugins: {
                    legend: { display: cfg.series.length > 1 || !!cfg.legend, position: 'bottom' },
                    tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(horizontal ? c.parsed.x : c.parsed.y) + (cfg.unit ? ' ' + cfg.unit : ''); }, footer: smallNote(cfg.floor) } },
                    annotation: undefined
                },
                scales: horizontal ? { x: value, y: cat } : { y: value, x: cat }
            }
        });
        return chart;
    }

    /* u-chart: rate per 100,000 with control limits; flagged points are diamonds in vermillion. */
    function uchart(id, cfg) {
        if (!init()) { return null; }
        var el = document.getElementById(id);
        if (!el) { return null; }
        label(el, cfg.ariaLabel);
        var flags = cfg.flags;
        return new Chart(el, {
            type: 'line',
            data: {
                labels: cfg.labels,
                datasets: [
                    { label: 'Rate per 100,000', data: cfg.rate, borderColor: owColors[0], backgroundColor: owColors[0], borderWidth: 2, tension: 0.1,
                      pointStyle: flags.map(function (f) { return f ? 'rectRot' : 'circle'; }), pointRadius: flags.map(function (f) { return f ? 7 : 3; }),
                      pointBackgroundColor: flags.map(function (f) { return f ? SIGNAL : owColors[0]; }), pointBorderColor: flags.map(function (f) { return f ? '#7a2f00' : owColors[0]; }) },
                    { label: 'Centre line', data: cfg.centre, borderColor: '#3f4a59', borderWidth: 1.5, pointRadius: 0, borderDash: [] },
                    { label: 'Upper control limit', data: cfg.ucl, borderColor: owColors[3], borderWidth: 1.5, pointRadius: 0, borderDash: [6, 4], stepped: true },
                    { label: 'Lower control limit', data: cfg.lcl, borderColor: owColors[2], borderWidth: 1.5, pointRadius: 0, borderDash: [2, 3], stepped: true }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } },
                    tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y); }, footer: function (items) { var i = items[0].dataIndex; return cfg.notes && cfg.notes[i] ? cfg.notes[i] : ''; } } } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Cases per 100,000 people per week' } }, x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } } }
            }
        });
    }

    return { line: line, bar: bar, uchart: uchart, fmt: fmt };
}());
