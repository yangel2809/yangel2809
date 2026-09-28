import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Tokens (ver skill de dataviz): tinta en grises, datos en una rampa azul.
const INK = { primary: '#111827', secondary: '#4b5563', muted: '#9ca3af', grid: '#eef0f3' };
const BLUE = { r7: '#256abf', r30: '#86b6ef', single: '#2a78d6' };

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.font.size = 12;
Chart.defaults.color = INK.muted;

const pct = (v) => (v === null || v === undefined ? 'sin datos' : `${Math.round(v)}%`);

/** Escribe el valor en la punta de cada barra (texto en tinta, nunca en el color del dato). */
const tipLabels = {
    id: 'tipLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const horizontal = chart.options.indexAxis === 'y';
        ctx.save();
        ctx.font = `600 11px ${Chart.defaults.font.family}`;
        ctx.fillStyle = INK.secondary;
        chart.data.datasets.forEach((ds, i) => {
            chart.getDatasetMeta(i).data.forEach((bar, j) => {
                const v = ds.data[j];
                const text = v === null ? '—' : `${Math.round(v)}%`;
                if (horizontal) {
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    const x = v === null ? chart.scales.x.getPixelForValue(0) : bar.x;
                    ctx.fillText(text, x + 4, bar.y);
                } else {
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    const y = v === null ? chart.scales.y.getPixelForValue(0) : bar.y;
                    ctx.fillText(text, bar.x, y - 4);
                }
            });
        });
        ctx.restore();
    },
};

const tooltip = (extra) => ({
    backgroundColor: INK.primary,
    padding: 10,
    cornerRadius: 8,
    displayColors: true,
    boxPadding: 4,
    callbacks: { label: (c) => ` ${c.dataset.label}: ${pct(c.raw)}${extra ? extra(c) : ''}` },
});

const percentScale = (display = true) => ({
    min: 0,
    max: 100,
    grid: { color: INK.grid, drawTicks: false },
    border: { display: false },
    ticks: { display, stepSize: 50, callback: (v) => `${v}%`, padding: 6 },
});

function habitsChart(el, rows) {
    const bar = { barThickness: 10, borderRadius: 4, borderSkipped: 'start', categoryPercentage: 0.8, barPercentage: 1 };

    return new Chart(el, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.name),
            datasets: [
                { label: '7 días', data: rows.map((r) => r.rate7), backgroundColor: BLUE.r7, ...bar },
                { label: '30 días', data: rows.map((r) => r.rate30), backgroundColor: BLUE.r30, ...bar },
            ],
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            animation: false,
            layout: { padding: { right: 36 } },
            interaction: { mode: 'index', axis: 'y', intersect: false },
            scales: {
                x: percentScale(),
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: INK.primary,
                        font: { size: 13 },
                        // Nombre completo en el tooltip; en el eje, recortado.
                        callback: (_, i) => (rows[i].name.length > 16 ? `${rows[i].name.slice(0, 15)}…` : rows[i].name),
                    },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltip(), callbacks: { ...tooltip().callbacks, title: (items) => rows[items[0].dataIndex].name } },
            },
        },
        plugins: [tipLabels],
    });
}

function weekdayChart(el, days) {
    return new Chart(el, {
        type: 'bar',
        data: {
            labels: days.map((d) => d.short),
            datasets: [{
                label: 'Cumplimiento',
                data: days.map((d) => d.rate),
                backgroundColor: BLUE.single,
                barThickness: 24,
                borderRadius: 4,
                borderSkipped: 'start',
            }],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            layout: { padding: { top: 18 } },
            scales: {
                y: { ...percentScale(false), grid: { color: INK.grid, drawTicks: false } },
                x: { grid: { display: false }, border: { color: '#d1d5db' }, ticks: { color: INK.secondary } },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltip((c) => (days[c.dataIndex].total ? ` (${days[c.dataIndex].done}/${days[c.dataIndex].total})` : '')),
                    callbacks: {
                        title: (items) => days[items[0].dataIndex].name,
                        label: (c) => ` ${pct(c.raw)}${days[c.dataIndex].total ? ` (${days[c.dataIndex].done}/${days[c.dataIndex].total})` : ''}`,
                    },
                    displayColors: false,
                },
            },
        },
        plugins: [tipLabels],
    });
}

const data = JSON.parse(document.getElementById('progress-data').textContent);
const h = document.getElementById('chart-habits');
const w = document.getElementById('chart-weekday');
if (h && data.habits.length) habitsChart(h, data.habits);
if (w) weekdayChart(w, data.weekday);
