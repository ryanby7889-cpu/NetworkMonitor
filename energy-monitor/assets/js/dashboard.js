let chart = null;
const labels = [];
const powerData = [];

const fmt = (value, digits = 1) => Number(value ?? 0).toFixed(digits);

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function initChart() {
    const canvas = document.getElementById('powerChart');
    if (!canvas || typeof Chart === 'undefined') return;

    chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Power (W)',
                data: powerData,
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 0,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { beginAtZero: true } }
        }
    });
}

async function loadHistory() {
    const response = await fetch('../api/history.php?limit=60&t=' + Date.now(), { cache: 'no-store' });
    const rows = await response.json();
    labels.length = 0;
    powerData.length = 0;
    rows.forEach(row => {
        labels.push(new Date(row.created_at.replace(' ', 'T')).toLocaleTimeString());
        powerData.push(Number(row.power));
    });
    if (chart) chart.update();
}

async function loadRealtime() {
    try {
        const response = await fetch('../api/latest.php?t=' + Date.now(), { cache: 'no-store' });
        const data = await response.json();
        const online = data.device_status === 'ONLINE';

        setValue('voltage', fmt(data.voltage, 1) + ' V');
        setValue('current', fmt(data.current, 3) + ' A');
        setValue('power', fmt(data.power, 1) + ' W');
        setValue('energy', fmt(data.energy, 2) + ' kWh');
        setValue('frequency', fmt(data.frequency, 1) + ' Hz');
        setValue('pf', fmt(data.pf, 2));
        setValue('last_update', data.created_at || '-');
        setValue('status', online ? 'ONLINE' : 'OFFLINE');
        setValue('device_status', online ? 'ONLINE' : 'OFFLINE');

        const statusEls = document.querySelectorAll('#status, #device_status');
        statusEls.forEach(el => el.classList.toggle('text-danger', !online));
        statusEls.forEach(el => el.classList.toggle('text-success', online));
    } catch (error) {
        setValue('status', 'OFFLINE');
        setValue('device_status', 'OFFLINE');
    }
}

async function loadStats() {
    try {
        const response = await fetch('../api/stats.php?t=' + Date.now(), { cache: 'no-store' });
        const data = await response.json();
        setValue('alarm_today', data.alarm_today ?? 0);
        setValue('alarm_active', data.alarm_active ?? 0);
        setValue('alarm_ack', data.alarm_ack ?? 0);

        if (data.latest) {
            const tariff = Number(window.ENERGY_TARIFF || 1500);
            setValue('cost', 'Rp ' + Math.round(Number(data.latest.energy) * tariff).toLocaleString('id-ID'));
        }
    } catch (error) {
        console.error(error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    initChart();
    await loadHistory();
    await loadRealtime();
    await loadStats();
    setInterval(loadRealtime, 3000);
    setInterval(loadStats, 5000);
    setInterval(loadHistory, 10000);
});
