// js/charts.js — inicializace grafů na stránce statistik

document.addEventListener('DOMContentLoaded', function () {
    if (!window.chartData || !document.getElementById('wpmChart')) return;

    const { labels, wpm, acc } = window.chartData;
    const ctx = document.getElementById('wpmChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'WPM',
                    data: wpm,
                    borderColor: '#4ade80',
                    backgroundColor: 'rgba(74,222,128,0.08)',
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Přesnost %',
                    data: acc,
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96,165,250,0.06)',
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: '#a1a1aa' } }
            },
            scales: {
                x:  { ticks: { color: '#a1a1aa', maxTicksLimit: 8 }, grid: { color: '#27272a' } },
                y:  { ticks: { color: '#4ade80' }, grid: { color: '#27272a' }, title: { display: true, text: 'WPM', color: '#4ade80' } },
                y1: { position: 'right', ticks: { color: '#60a5fa' }, grid: { drawOnChartArea: false }, min: 0, max: 100 }
            }
        }
    });
});
