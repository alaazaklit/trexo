import '../css/dashboard.css';

const POLL_INTERVAL_MS = 30000;

const root = document.getElementById('admin-dashboard-app');

if (root) {
    const dataUrl = root.dataset.dataUrl;
    const rangeDays = root.dataset.rangeDays;
    const styles = getComputedStyle(root);
    const color = (name) => styles.getPropertyValue(name).trim();

    let volumeChart = null;
    let cancellationsChart = null;

    const setStat = (id, value) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    };

    const sum = (obj) => Object.values(obj || {}).reduce((total, value) => total + Number(value || 0), 0);

    const renderVolumeChart = (trend) => {
        const ctx = document.getElementById('volume-trend-chart');
        if (!ctx) {
            return;
        }

        const labels = trend.map((row) => row.day);
        const taxiData = trend.map((row) => row.taxi);
        const deliveryData = trend.map((row) => row.delivery);

        const config = {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Taxi',
                        data: taxiData,
                        borderColor: color('--series-1'),
                        backgroundColor: color('--series-1'),
                        tension: 0.25,
                        borderWidth: 2,
                        pointRadius: 3,
                    },
                    {
                        label: 'Delivery',
                        data: deliveryData,
                        borderColor: color('--series-2'),
                        backgroundColor: color('--series-2'),
                        tension: 0.25,
                        borderWidth: 2,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    x: { grid: { color: color('--gridline') }, ticks: { color: color('--text-muted') } },
                    y: { beginAtZero: true, grid: { color: color('--gridline') }, ticks: { color: color('--text-muted') } },
                },
                plugins: {
                    legend: { labels: { color: color('--text-secondary') } },
                },
            },
        };

        if (volumeChart) {
            volumeChart.data = config.data;
            volumeChart.update();
        } else {
            volumeChart = new Chart(ctx, config);
        }
    };

    const renderCancellationsChart = (cancellations) => {
        const ctx = document.getElementById('cancellations-chart');
        if (!ctx) {
            return;
        }

        const order = [
            ['canceled', 'Canceled', '--series-1'],
            ['driver_rejected', 'Driver Rejected', '--series-2'],
            ['failed_delivery', 'Failed Delivery', '--series-3'],
            ['request_expired', 'Request Expired', '--series-4'],
        ];

        const labels = order.map(([, label]) => label);
        const values = order.map(([key]) => Number(cancellations[key] || 0));
        const colors = order.map(([, , cssVar]) => color(cssVar));

        const config = {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: colors }],
            },
            options: {
                responsive: true,
                scales: {
                    x: { grid: { display: false }, ticks: { color: color('--text-muted') } },
                    y: { beginAtZero: true, grid: { color: color('--gridline') }, ticks: { color: color('--text-muted') } },
                },
                plugins: {
                    legend: { display: false },
                },
            },
        };

        if (cancellationsChart) {
            cancellationsChart.data = config.data;
            cancellationsChart.update();
        } else {
            cancellationsChart = new Chart(ctx, config);
        }
    };

    const refresh = async () => {
        try {
            const response = await fetch(`${dataUrl}?range=${rangeDays}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!payload.result) {
                return;
            }

            const data = payload.data;

            setStat('stat-active-drivers', data.active_drivers);
            setStat('stat-active-customers', data.active_customers);
            setStat('stat-live-taxi', sum(data.in_flight.taxi));
            setStat('stat-live-delivery', sum(data.in_flight.delivery));
            setStat('stat-completed', sum(data.completed_orders));
            setStat('stat-cancellations', sum(data.cancellations));

            renderVolumeChart(data.volume_trend);
            renderCancellationsChart(data.cancellations);
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Dashboard refresh failed', error);
        }
    };

    refresh();
    setInterval(refresh, POLL_INTERVAL_MS);
}
