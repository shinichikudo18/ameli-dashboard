const HA_CONFIG = {
    proxyUrl: '/dashboard/proxy.php',
    entities: [
        { entityId: 'sensor.oficinasala_de_reuniones_temperature', name: 'Temperatura Sala de Reuniones', showInChart: true, kind: 'sensor' },
        { entityId: 'switch.oficinasala_de_reuniones', name: 'Luz Sala de Reuniones', showInChart: false, kind: 'switch' }
    ]
};

async function fetchSensorData(entityId) {
    const response = await fetch(HA_CONFIG.proxyUrl + '?action=entity&entity=' + entityId);
    if (!response.ok) throw new Error('Error fetching ' + entityId);
    return await response.json();
}

async function fetchAllEntities() {
    const results = [];
    for (const entity of HA_CONFIG.entities) {
        try {
            const data = await fetchSensorData(entity.entityId);
            results.push({
                name: entity.name,
                value: entity.kind === 'switch' ? (data.state === 'on' ? 1 : 0) : (parseFloat(data.state) || 0),
                state: data.state,
                unit: data.attributes?.unit_of_measurement || '',
                lastChanged: data.last_changed,
                entityId: entity.entityId,
                kind: entity.kind || 'sensor',
                showInChart: entity.showInChart !== false
            });
        } catch (error) {
            console.error('Error fetching ' + entity.name + ':', error);
            results.push({ name: entity.name, value: 0, error: true, state: 'unavailable', kind: entity.kind || 'sensor', showInChart: entity.showInChart !== false });
        }
    }
    return results;
}

function updateChart(data) {
    const ctx = document.getElementById('sensorChart').getContext('2d');
    
    if (window.myChart) {
        window.myChart.destroy();
    }
    
    const chartData = data.filter(d => d.showInChart !== false);
    const labels = chartData.map(d => d.name);
    const values = chartData.map(d => d.error ? 0 : d.value);
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8B0000', '#00CED1'];
    
    window.myChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#fff', padding: 20 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const d = chartData[context.dataIndex];
                            return d.name + ': ' + d.value + d.unit;
                        }
                    }
                }
            }
        }
    });
    
    const infoDiv = document.getElementById('sensorInfo');
    infoDiv.innerHTML = data.map(d => 
        '<div class="sensor-card">' +
            '<div class="sensor-name">' + d.name + '</div>' +
            '<div class="sensor-value">' + (d.error ? 'Error' : (d.kind === 'switch' ? (d.state === 'on' ? 'Encendida' : 'Apagada') : d.value)) + (d.kind === 'switch' ? '' : (d.unit || '')) + '</div>' +
        '</div>'
    ).join('');
}

async function refreshData() {
    document.getElementById('lastUpdate').textContent = 'Actualizando...';
    const data = await fetchAllEntities();
    updateChart(data);
    document.getElementById('lastUpdate').textContent = 'Última actualización: ' + new Date().toLocaleTimeString();
}

async function init() {
    await refreshData();
    setInterval(refreshData, 30000);
}
