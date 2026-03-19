document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || typeof window.ReportsData === 'undefined') return;

    // Configuración común
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.color = '#6e5c5f';

    const data = window.ReportsData;

    // 2. Estado de Pedidos
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && data.status) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: data.status.labels,
                datasets: [{
                    data: data.status.data,
                    backgroundColor: data.status.colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // 3. Top Productos
    const productsCtx = document.getElementById('productsChart');
    if (productsCtx && data.products) {
        new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: data.products.map(p => p.name),
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: data.products.map(p => p.total_sold),
                    backgroundColor: '#e6c86e',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            }
        });
    }

    // 4. Ventas por Mes (Línea)
    const salesMonthCtx = document.getElementById('salesMonthChart');
    if (salesMonthCtx && data.salesMonth) {
        new Chart(salesMonthCtx, {
            type: 'line',
            data: {
                labels: data.salesMonth.map(r => r.mes_label),
                datasets: [{
                    label: 'Ventas',
                    data: data.salesMonth.map(r => parseFloat(r.total_ventas)),
                    borderColor: '#a65c68',
                    backgroundColor: 'rgba(166, 92, 104, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#a65c68',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: val => '$' + val.toLocaleString('es-CO')
                        }
                    }
                }
            }
        });
    }

    // 5. Vendedores por Universidad (Barras)
    const uniCtx = document.getElementById('uniChart');
    if (uniCtx && data.universities) {
        new Chart(uniCtx, {
            type: 'bar',
            data: {
                labels: data.universities.map(r => r.universidad),
                datasets: [{
                    label: 'Vendedores',
                    data: data.universities.map(r => parseInt(r.total_vendedores)),
                    backgroundColor: 'rgba(255, 107, 107, 0.75)',
                    borderColor: 'rgba(255, 107, 107, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
});
