document.addEventListener("DOMContentLoaded", function () {
    const protocolosCanvas = document.getElementById("protocolosChart");
    const statusCanvas = document.getElementById("statusChart");

    if (typeof Chart === "undefined") {
        console.warn("Chart.js não foi carregado.");
        return;
    }

    if (protocolosCanvas) {
        new Chart(protocolosCanvas, {
            type: "line",
            data: {
                labels: ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun"],
                datasets: [
                    {
                        label: "Protocolos abertos",
                        data: [48, 55, 61, 58, 70, 82],
                        borderWidth: 3,
                        tension: 0.35,
                        fill: false
                    },
                    {
                        label: "Concluídos",
                        data: [32, 41, 49, 46, 57, 68],
                        borderWidth: 3,
                        tension: 0.35,
                        fill: false
                    },
                    {
                        label: "Pendentes",
                        data: [12, 10, 14, 13, 16, 14],
                        borderWidth: 3,
                        tension: 0.35,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                weight: "bold"
                            }
                        }
                    },
                    tooltip: {
                        mode: "index",
                        intersect: false
                    }
                },
                interaction: {
                    mode: "nearest",
                    axis: "x",
                    intersect: false
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: "doughnut",
            data: {
                labels: ["Concluídos", "Em análise", "Pendentes"],
                datasets: [
                    {
                        data: [72, 18, 10],
                        borderWidth: 4
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
                    }
                }
            }
        });
    }
});