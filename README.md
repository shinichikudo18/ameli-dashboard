# Dashboard Condominio - Home Assistant

Dashboard para visualizar datos de sensores de Home Assistant con gráfico circular.

## Configuración

### Credenciales
- **URL**: `http://192.168.22.254:8123`
- **Token**: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI5MTgxZDc2MzI5ZGM0NTBiOTA0ZTJlZjAwMjJhOTEzYiIsImlhdCI6MTc3NDk2NzY4MywiZXhwIjoyMDkwMzI3NjgzfQ.H1FZpiBd7bpqRe75Bg1XxBFKp-8-7qTETmaHtIE6g2g`

### Sensores configurados
- `sensor.familia_navarrete_tranamil` → "Wifi Datos"

## Uso

### Desarrollo local
```bash
# Abrir directamente en navegador
open index.html
# O con servidor local
python3 -m http.server 8000
```

### Producción (servidor externo)
1. Copiar archivos a tu servidor:
   - `index.html`
   - `config.js`
2. Configurar un servidor web (nginx, apache, etc.)
3. Importante: El servidor debe tener acceso a la red de Home Assistant

## Agregar más sensores

Edita `config.js` y agrega más entidades:

```javascript
const HA_CONFIG = {
    // ... config actual
    entities: [
        { entityId: 'sensor.familia_navarrete_tranamil', name: 'Wifi Datos' },
        { entityId: 'sensor.otro_sensor', name: 'Nombre Personalizado' }
    ]
};
```

## Notas
- El dashboard se actualiza cada 30 segundos automáticamente
- Requiere conexión a la red de Home Assistant
- El token es un long-lived access token de Home Assistant