document.addEventListener("DOMContentLoaded", function () {
    const dados = window.relatorioDonoCharts || {};

    const protocolosCanvas = document.getElementById("protocolosChart");
    const statusCanvas = document.getElementById("statusChart");

    if (typeof Chart === "undefined") {
        console.warn("Chart.js não foi carregado.");
        return;
    }

    Chart.defaults.font.family = "'Inter', 'Segoe UI', Arial, sans-serif";
    Chart.defaults.color = "#475569";

    if (protocolosCanvas) {
        const evolucao = dados.evolucao || {
            labels: [],
            abertos: [],
            concluidos: [],
            pendentes: []
        };

        new Chart(protocolosCanvas, {
            type: "line",
            data: {
                labels: evolucao.labels || [],
                datasets: [
                    {
                        label: "Protocolos abertos",
                        data: evolucao.abertos || [],
                        borderColor: "#1b5e20",
                        backgroundColor: "rgba(27, 94, 32, 0.10)",
                        pointBackgroundColor: "#1b5e20",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: "Concluídos",
                        data: evolucao.concluidos || [],
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37, 99, 235, 0.08)",
                        pointBackgroundColor: "#2563eb",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: "Pendentes",
                        data: evolucao.pendentes || [],
                        borderColor: "#f59e0b",
                        backgroundColor: "rgba(245, 158, 11, 0.08)",
                        pointBackgroundColor: "#f59e0b",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 18,
                            font: {
                                weight: "700"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: "#102016",
                        titleColor: "#ffffff",
                        bodyColor: "#ffffff",
                        padding: 12,
                        cornerRadius: 12
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: "700"
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: "rgba(148, 163, 184, 0.18)"
                        }
                    }
                }
            }
        });
    }

    if (statusCanvas) {
        const status = dados.status || {
            labels: ["Concluídos", "Em análise", "Pendentes"],
            valores: [0, 0, 0]
        };

        new Chart(statusCanvas, {
            type: "doughnut",
            data: {
                labels: status.labels || [],
                datasets: [
                    {
                        data: status.valores || [],
                        backgroundColor: [
                            "#1b5e20",
                            "#2563eb",
                            "#f59e0b"
                        ],
                        borderColor: "#ffffff",
                        borderWidth: 5,
                        hoverOffset: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "#102016",
                        titleColor: "#ffffff",
                        bodyColor: "#ffffff",
                        padding: 12,
                        cornerRadius: 12
                    }
                }
            }
        });
    }
});