/* =============================================================================
   GIMS — dashboard charts
   ============================================================================= */

(function () {
    'use strict';

    const data     = window.GIMS_CHARTS || {};
    const currency = window.GIMS_CURRENCY || 'Rs.';

    if (typeof Chart === 'undefined') {
        console.warn('[GIMS] Chart.js not loaded');
        return;
    }

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size   = 11.5;
    Chart.defaults.color       = '#64748b';

    const palette = ['#2563eb','#4f46e5','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#0ea5e9'];

    function mkGradient(ctx, area, c1, c2) {
        const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        g.addColorStop(0, c1);
        g.addColorStop(1, c2);
        return g;
    }

    /* ---------------- 1. Sales + Purchases ---------------- */
    (function () {
        const canvas = document.getElementById('salesChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const salesLabels = (data.monthlySales && data.monthlySales.labels) || [];
        const salesData   = (data.monthlySales && data.monthlySales.data)   || [];
        const purLabels   = (data.monthlyPurchases && data.monthlyPurchases.labels) || [];
        const purData     = (data.monthlyPurchases && data.monthlyPurchases.data)   || [];

        // Use the longer label list; align purchases by label
        const labels = salesLabels.length >= purLabels.length ? salesLabels : purLabels;
        const purMap = {};
        purLabels.forEach((l, i) => purMap[l] = purData[i]);
        const purAligned = labels.map(l => purMap[l] || 0);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sales',
                        data: salesData,
                        borderColor: '#2563eb',
                        backgroundColor: (ctx) => {
                            const {ctx: c, chartArea} = ctx.chart;
                            if (!chartArea) return 'rgba(37,99,235,.15)';
                            return mkGradient(c, chartArea, 'rgba(37,99,235,.35)', 'rgba(37,99,235,0)');
                        },
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#2563eb',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Purchases',
                        data: purAligned,
                        borderColor: '#10b981',
                        backgroundColor: (ctx) => {
                            const {ctx: c, chartArea} = ctx.chart;
                            if (!chartArea) return 'rgba(16,185,129,.15)';
                            return mkGradient(c, chartArea, 'rgba(16,185,129,.30)', 'rgba(16,185,129,0)');
                        },
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { usePointStyle: true, boxWidth: 8, padding: 14 }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 10,
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: (c) => ` ${c.dataset.label}: ${currency} ${Number(c.parsed.y).toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 12 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7', drawBorder: false },
                        ticks: {
                            callback: (v) => {
                                if (v >= 1000000) return (v/1000000).toFixed(1) + 'M';
                                if (v >= 1000)    return (v/1000).toFixed(0) + 'k';
                                return v;
                            }
                        }
                    }
                }
            }
        });
    })();

    /* ---------------- 2. Production doughnut ---------------- */
    (function () {
        const canvas = document.getElementById('productionChart');
        if (!canvas) return;
        const labels = (data.production && data.production.labels) || ['No data'];
        const values = (data.production && data.production.data)   || [1];

        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8, padding: 12 }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        cornerRadius: 8
                    }
                }
            }
        });
    })();

    /* ---------------- 3. Stock movement bar ---------------- */
    (function () {
        const canvas = document.getElementById('movementChart');
        if (!canvas) return;
        const labels = (data.stockMovement && data.stockMovement.labels) || [];
        const inD    = (data.stockMovement && data.stockMovement['in'])  || [];
        const outD   = (data.stockMovement && data.stockMovement.out)    || [];

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Stock In',
                        data: inD,
                        backgroundColor: 'rgba(16,185,129,.75)',
                        hoverBackgroundColor: '#10b981',
                        borderRadius: 6,
                        maxBarThickness: 22
                    },
                    {
                        label: 'Stock Out',
                        data: outD,
                        backgroundColor: 'rgba(239,68,68,.7)',
                        hoverBackgroundColor: '#ef4444',
                        borderRadius: 6,
                        maxBarThickness: 22
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { usePointStyle: true, boxWidth: 8, padding: 14 }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: { grid: { display: false }, stacked: false },
                    y: { beginAtZero: true, grid: { color: '#eef2f7', drawBorder: false } }
                }
            }
        });
    })();

    /* ---------------- 4. Category value pie ---------------- */
    (function () {
        const canvas = document.getElementById('categoryChart');
        if (!canvas) return;
        const labels = (data.categories && data.categories.labels) || ['No data'];
        const values = (data.categories && data.categories.data)   || [1];

        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8, padding: 10 }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: (c) => ` ${c.label}: ${currency} ${Number(c.parsed).toLocaleString()}`
                        }
                    }
                }
            }
        });
    })();
})();