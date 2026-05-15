(function () {
    const brlFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    function formatBRL(value) {
        return brlFormatter.format(Number(value) || 0);
    }

    window.formatBRL = formatBRL;

    const sidebar = document.querySelector('[data-sidebar]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

    function openSidebar() {
        if (!sidebar) {
            return;
        }

        sidebar.classList.add('is-open');
        sidebarOverlay?.classList.add('is-visible');
        document.body.classList.add('is-sidebar-open');
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        if (!sidebar) {
            return;
        }

        sidebar.classList.remove('is-open');
        sidebarOverlay?.classList.remove('is-visible');
        document.body.classList.remove('is-sidebar-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    }

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    sidebarOverlay?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });

    function parseDashboardData() {
        const dataScript = document.getElementById('dashboard-chart-data');

        if (!dataScript) {
            return null;
        }

        try {
            return JSON.parse(dataScript.textContent || '{}');
        } catch (error) {
            console.warn('Dados do dashboard inválidos.', error);
            return null;
        }
    }

    function hasValues(values) {
        return Array.isArray(values) && values.some((value) => Number(value) > 0);
    }

    function setChartEmptyState(chartId, emptyKey, isEmpty) {
        const chart = document.getElementById(chartId);
        const empty = document.querySelector(`[data-chart-empty="${emptyKey}"]`);

        chart?.classList.toggle('is-hidden', isEmpty);
        empty?.classList.toggle('is-hidden', !isEmpty);
    }

    function initCashflowChart(data) {
        const chartElement = document.getElementById('cashflow-chart');
        const hasData = hasValues(data?.entradas) || hasValues(data?.saidas);

        if (!chartElement) {
            return;
        }

        setChartEmptyState('cashflow-chart', 'cashflow', !hasData || !window.ApexCharts);

        if (!hasData || !window.ApexCharts) {
            return;
        }

        const chart = new ApexCharts(chartElement, {
            chart: {
                type: 'area',
                height: 310,
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif'
            },
            colors: ['#2FAF8F', '#C84D4D'],
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 0.25,
                    opacityFrom: 0.28,
                    opacityTo: 0.04,
                    stops: [0, 90, 100]
                }
            },
            grid: {
                borderColor: '#E5EAF1',
                strokeDashArray: 4
            },
            series: [
                { name: 'Entradas', data: data.entradas || [] },
                { name: 'Saídas', data: data.saidas || [] }
            ],
            xaxis: {
                categories: data.months || [],
                labels: { style: { colors: '#667085' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: { colors: '#667085' },
                    formatter: (value) => formatBRL(value)
                }
            },
            tooltip: {
                y: {
                    formatter: (value) => formatBRL(value)
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                labels: { colors: '#142033' }
            }
        });

        chart.render();
    }

    function initCategoryChart(data) {
        const chartElement = document.getElementById('category-chart');
        const hasData = hasValues(data?.categoriasValores);

        if (!chartElement) {
            return;
        }

        setChartEmptyState('category-chart', 'category', !hasData || !window.ApexCharts);

        if (!hasData || !window.ApexCharts) {
            return;
        }

        const total = (data.categoriasValores || []).reduce((sum, value) => sum + Number(value || 0), 0);
        const chart = new ApexCharts(chartElement, {
            chart: {
                type: 'donut',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif'
            },
            labels: data.categorias || [],
            series: data.categoriasValores || [],
            colors: ['#2FAF8F', '#286CC8', '#8057C7', '#9C7422', '#C84D4D'],
            dataLabels: { enabled: false },
            stroke: {
                colors: ['#FFFFFF'],
                width: 4
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: { show: true, color: '#667085' },
                            value: {
                                show: true,
                                color: '#142033',
                                formatter: (value) => formatBRL(value)
                            },
                            total: {
                                show: true,
                                label: 'Total',
                                color: '#667085',
                                formatter: () => formatBRL(total)
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                labels: { colors: '#142033' }
            },
            tooltip: {
                y: {
                    formatter: (value) => formatBRL(value)
                }
            }
        });

        chart.render();
    }

    const dashboardData = parseDashboardData();

    if (dashboardData) {
        initCashflowChart(dashboardData);
        initCategoryChart(dashboardData);
    }

    if (window.lucide) {
        window.lucide.createIcons();
    }
})();
