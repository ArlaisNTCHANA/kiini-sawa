import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

/**
 * Chart.js controller for admin dashboard.
 * Renders doughnut and line charts with a premium dark theme.
 *
 * Usage in Twig:
 *   <canvas data-controller="chart"
 *           data-chart-type-value="doughnut"
 *           data-chart-data-value='{{ chartJson }}'
 *   ></canvas>
 */
export default class extends Controller {
    static values = {
        type: { type: String, default: 'doughnut' },
        data: Object,
    };

    connect() {
        // Chart.js est servi par l'application (importmap), plus par un CDN
        Chart.register(...registerables);

        this._chart = this._createChart(Chart);
    }

    disconnect() {
        this._chart?.destroy();
    }

    _createChart(Chart) {
        const ctx = this.element.getContext('2d');
        const chartData = this.dataValue;
        const type = this.typeValue;

        // Color palette
        const gradientColors = chartData.colors || [
            '#6366f1', '#8b5cf6', '#a855f7', '#c084fc', '#e879f9',
        ];

        const config = type === 'doughnut'
            ? this._doughnutConfig(chartData, gradientColors)
            : this._lineConfig(chartData);

        return new Chart(ctx, config);
    }

    _doughnutConfig(data, colors) {
        return {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: colors,
                    borderColor: 'rgba(11, 15, 25, 0.8)',
                    borderWidth: 3,
                    hoverBorderWidth: 0,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            font: { family: "'Inter', sans-serif", size: 12 },
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        borderColor: 'rgba(99, 102, 241, 0.3)',
                        borderWidth: 1,
                        titleColor: '#f8fafc',
                        bodyColor: '#cbd5e1',
                        titleFont: { family: "'Inter', sans-serif", weight: '600' },
                        bodyFont: { family: "'Inter', sans-serif" },
                        cornerRadius: 8,
                        padding: 12,
                    },
                },
            },
        };
    }

    _lineConfig(data) {
        return {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Activité Habitudes',
                    data: data.data,
                    borderColor: '#6366f1',
                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const { ctx, chartArea } = chart;
                        if (!chartArea) return 'rgba(99,102,241,0.1)';
                        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
                        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.02)');
                        return gradient;
                    },
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#0b0f19',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#a855f7',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Inter', sans-serif", size: 11 },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Inter', sans-serif", size: 11 },
                            stepSize: 1,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        borderColor: 'rgba(99, 102, 241, 0.3)',
                        borderWidth: 1,
                        titleColor: '#f8fafc',
                        bodyColor: '#cbd5e1',
                        cornerRadius: 8,
                        padding: 12,
                        titleFont: { family: "'Inter', sans-serif", weight: '600' },
                        bodyFont: { family: "'Inter', sans-serif" },
                    },
                },
            },
        };
    }
}
