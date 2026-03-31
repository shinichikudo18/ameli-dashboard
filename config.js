// Configuración de Home Assistant
const HA_CONFIG = {
    url: 'http://192.168.22.254:8123',
    token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI5MTgxZDc2MzI5ZGM0NTBiOTA0ZTJlZjAwMjJhOTEzYiIsImlhdCI6MTc3NDk2NzY4MywiZXhwIjoyMDkwMzI3NjgzfQ.H1FZpiBd7bpqRe75Bg1XxBFKp-8-7qTETmaHtIE6g2g',
    entities: [
        { entityId: 'sensor.familia_navarrete_tranamil', name: 'Wifi Datos' }
    ]
};

async function fetchSensorData(entityId) {
    const response = await fetch(`${HA_CONFIG.url}/api/states/${entityId}`, {
        headers: {
            'Authorization': `Bearer ${HA_CONFIG.token}`,
            'Content-Type': 'application/json'
        }
    });
    if (!response.ok) throw new Error(`Error fetching ${entityId}`);
    return await response.json();
}

async function fetchAllEntities() {
    const results = [];
    for (const entity of HA_CONFIG.entities) {
        try {
            const data = await fetchSensorData(entity.entityId);
            results.push({
                name: entity.name,
                value: parseFloat(data.state) || 0,
                unit: data.attributes?.unit_of_measurement || '',
                lastChanged: data.last_changed
            });
        } catch (error) {
            console.error(`Error fetching ${entity.name}:`, error);
            results.push({ name: entity.name, value: 0, error: true });
        }
    }
    return results;
}

function updateChart(data) {
    const ctx = document.getElementById('sensorChart').getContext('2d');
    
    if (window.myChart) {
        window.myChart.destroy();
    }
    
    const labels = data.map(d => d.name);
    const values = data.map(d => d.error ? 0 : d.value);
    const colors = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
        '#9966FF', '#FF9F40', '#8B0000', '#00CED1'
    ];
    
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
                            const d = data[context.dataIndex];
                            return `${d.name}: ${d.value}${d.unit}`;
                        }
                    }
                }
            }
        }
    });
    
    // Actualizar información detallada
    const infoDiv = document.getElementById('sensorInfo');
    infoDiv.innerHTML = data.map(d => `
        <div class="sensor-card">
            <div class="sensor-name">${d.name}</div>
            <div class="sensor-value">${d.error ? 'Error' : d.value}${d.unit || ''}</div>
        </div>
    `).join('');
}

async function refreshData() {
    const lastUpdate = document.getElementById('lastUpdate');
    lastUpdate.textContent = 'Actualizando...';
    
    const data = await fetchAllEntities();
    updateChart(data);
    
    const now = new Date();
    lastUpdate.textContent = `Última actualización: ${now.toLocaleTimeString()}`;
}

async function init() {
    await refreshData();
    setInterval(refreshData, 30000);
}