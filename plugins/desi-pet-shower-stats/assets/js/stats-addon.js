/**
 * Stats Add-on - JavaScript do Dashboard
 *
 * Gerencia gráficos Chart.js e interações do dashboard.
 *
 * @since 1.1.0
 */

(function() {
    'use strict';

    /**
     * Cores padrão para gráficos (baseadas no Visual Style Guide DPS)
     */
    var chartColors = [
        'rgba(14, 165, 233, 0.8)',   // Azul primário
        'rgba(16, 185, 129, 0.8)',   // Verde sucesso
        'rgba(245, 158, 11, 0.8)',   // Amarelo aviso
        'rgba(239, 68, 68, 0.8)',    // Vermelho erro
        'rgba(139, 92, 246, 0.8)',   // Roxo
        'rgba(236, 72, 153, 0.8)',   // Rosa
        'rgba(107, 114, 128, 0.8)',  // Cinza
        'rgba(59, 130, 246, 0.8)',   // Azul médio
        'rgba(34, 197, 94, 0.8)',    // Verde médio
        'rgba(251, 146, 60, 0.8)'    // Laranja
    ];

    /**
     * Inicializa gráfico de barras para serviços
     *
     * @param {string} canvasId - ID do canvas
     * @param {array} labels - Rótulos
     * @param {array} data - Valores
     * @param {string} label - Label do dataset
     */
    function initServicesChart(canvasId, labels, data, label) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: label || 'Serviços',
                    data: data,
                    backgroundColor: chartColors.slice(0, data.length),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#374151',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 6,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                }
            }
        });
    }

    /**
     * Inicializa gráfico de pizza para distribuição
     *
     * @param {string} canvasId - ID do canvas
     * @param {array} labels - Rótulos
     * @param {array} data - Valores
     */
    function initPieChart(canvasId, labels, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: chartColors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#374151',
                            font: {
                                size: 13
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#374151',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var value = context.parsed;
                                var percentage = Math.round((value / total) * 100);
                                return context.label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }

    /**
     * Inicializa gráfico de linha para tendências
     *
     * @param {string} canvasId - ID do canvas
     * @param {array} labels - Rótulos (datas)
     * @param {array} data - Valores
     * @param {string} label - Label do dataset
     */
    function initTrendChart(canvasId, labels, data, label) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label || 'Tendência',
                    data: data,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#0ea5e9',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#374151',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 6,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    /**
     * Mostra estado de carregamento em um canvas
     *
     * @param {string} canvasId - ID do canvas
     */
    function showLoading(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;

        var parent = canvas.parentElement;
        if (parent) {
            var loading = document.createElement('div');
            loading.className = 'dps-stats-loading';
            loading.id = canvasId + '-loading';
            loading.textContent = 'Carregando gráfico...';
            parent.insertBefore(loading, canvas);
            canvas.style.display = 'none';
        }
    }

    /**
     * Remove estado de carregamento
     *
     * @param {string} canvasId - ID do canvas
     */
    function hideLoading(canvasId) {
        var loading = document.getElementById(canvasId + '-loading');
        if (loading) {
            loading.remove();
        }
        var canvas = document.getElementById(canvasId);
        if (canvas) {
            canvas.style.display = 'block';
        }
    }

    /**
     * Formata número para moeda brasileira
     *
     * @param {number} value - Valor
     * @returns {string} - Valor formatado
     */
    function formatCurrency(value) {
        return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /**
     * Formata variação percentual
     *
     * @param {number} value - Valor
     * @returns {string} - Valor formatado com sinal
     */
    function formatVariation(value) {
        var sign = value >= 0 ? '+' : '';
        return sign + value.toFixed(1) + '%';
    }

    /**
     * Exporta dados via download (retrocompatibilidade)
     *
     * @deprecated Usar links diretos de download
     * @param {string} url - URL de exportação
     * @param {string} filename - Nome do arquivo
     */
    function exportCSV(url, filename) {
        console.warn('DPSStats.exportCSV está deprecado. Use links diretos de download.');
        var link = document.createElement('a');
        link.href = url;
        link.download = filename || 'export.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Verifica se Chart.js está disponível
     *
     * @returns {boolean} - True se Chart.js está carregado
     */
    function isChartAvailable() {
        return typeof Chart !== 'undefined' && window.Chart;
    }

    // Expor funções globalmente
    window.DPSStats = {
        initServicesChart: initServicesChart,
        initPieChart: initPieChart,
        initTrendChart: initTrendChart,
        showLoading: showLoading,
        hideLoading: hideLoading,
        exportCSV: exportCSV, // Mantido para retrocompatibilidade
        formatCurrency: formatCurrency,
        formatVariation: formatVariation,
        isChartAvailable: isChartAvailable,
        colors: chartColors
    };

    // Inicializar quando DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há dados para inicializar gráficos
        if (typeof dpsStatsData !== 'undefined') {
            if (dpsStatsData.services) {
                initServicesChart(
                    'dps-stats-services-chart',
                    dpsStatsData.services.labels,
                    dpsStatsData.services.data,
                    dpsStatsData.services.label || 'Serviços'
                );
            }

            if (dpsStatsData.species) {
                initPieChart(
                    'dps-stats-species-chart',
                    dpsStatsData.species.labels,
                    dpsStatsData.species.data
                );
            }

            if (dpsStatsData.trend) {
                initTrendChart(
                    'dps-stats-trend-chart',
                    dpsStatsData.trend.labels,
                    dpsStatsData.trend.data,
                    dpsStatsData.trend.label
                );
            }
        }
    });

})();
