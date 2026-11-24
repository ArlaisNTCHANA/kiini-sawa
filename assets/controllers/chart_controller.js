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
            '#C8962E', '#1F4D3A', '#2E6B53', '#5D7368', '#A87A1F',
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
                    borderColor: '#FFFFFF',
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
                            color: '#66706A',
                            font: { family: "'Inter', sans-serif", size: 12 },
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1F4D3A',
                        borderColor: '#2E6B53',
                        borderWidth: 1,
                        titleColor: '#FFFFFF',
                        bodyColor: '#EEF3EF',
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
                    borderColor: '#2E6B53',
                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const { ctx, chartArea } = chart;
                        if (!chartArea) return 'rgba(46,107,83,0.1)';
                        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(46, 107, 83, 0.22)');
                        gradient.addColorStop(1, 'rgba(46, 107, 83, 0.02)');
                        return gradient;
                    },
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#2E6B53',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#C8962E',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: 'rgba(31,77,58,0.07)' },
                        ticks: {
                            color: '#66706A',
                            font: { family: "'Inter', sans-serif", size: 11 },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(31,77,58,0.07)' },
                        ticks: {
                            color: '#66706A',
                            font: { family: "'Inter', sans-serif", size: 11 },
                            stepSize: 1,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1F4D3A',
                        borderColor: '#2E6B53',
                        borderWidth: 1,
                        titleColor: '#FFFFFF',
                        bodyColor: '#EEF3EF',
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
