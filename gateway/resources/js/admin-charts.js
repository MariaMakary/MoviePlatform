import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const charts = new WeakMap();

function theme() {
    const style = getComputedStyle(document.documentElement);

    return {
        cerulean: style.getPropertyValue('--color-cerulean').trim(),
        rose: style.getPropertyValue('--color-neon-rose').trim(),
        text: style.getPropertyValue('--color-muted').trim(),
        grid: style.getPropertyValue('--color-line').trim(),
        neutral: style.getPropertyValue('--color-subtle').trim(),
        muted: style.getPropertyValue('--color-line-strong').trim(),
    };
}

function lineChart(data, colors) {
    return {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: data.label,
                data: data.values,
                borderColor: colors.cerulean,
                backgroundColor: `color-mix(in srgb, ${colors.cerulean} 25%, transparent)`,
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            }],
        },
        options: { plugins: { legend: { display: false } }, scales: axes(colors) },
    };
}

function barChart(data, colors) {
    return {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{ label: data.label, data: data.values, backgroundColor: colors.rose, borderRadius: 4 }],
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: axes(colors) },
    };
}

function doughnutChart(data, colors) {
    return {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [colors.cerulean, colors.neutral, colors.rose, colors.muted],
                borderWidth: 0,
            }],
        },
        options: { plugins: { legend: { position: 'bottom', labels: { color: colors.text } } } },
    };
}

function axes(colors) {
    const axis = { ticks: { color: colors.text, precision: 0 }, grid: { color: colors.grid } };

    return { x: { ...axis, beginAtZero: true }, y: { ...axis, beginAtZero: true } };
}

const builders = { line: lineChart, bar: barChart, doughnut: doughnutChart };

export function mountAdminChart(root) {
    const data = JSON.parse(root.dataset.chart);
    const colors = theme();
    const config = builders[data.type](data, colors);

    charts.get(root)?.destroy();
    charts.set(root, new Chart(root.querySelector('canvas'), {
        ...config,
        options: { ...config.options, responsive: true, maintainAspectRatio: false, animation: false },
    }));
}
