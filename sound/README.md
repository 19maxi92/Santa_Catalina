# Carpeta de Sonidos

## 📁 Archivos requeridos:

### `noti.mp3`
- **Descripción**: Sonido de notificación para nuevos pedidos
- **Uso**: Se reproduce automáticamente cuando llega un nuevo pedido para Local 1
- **Formato**: MP3
- **Duración recomendada**: 1-3 segundos
- **Volumen**: Ajustado para no ser demasiado fuerte

## 🔊 Cómo funciona:

1. Cada 30 segundos, el sistema chequea si hay nuevos pedidos para Local 1
2. Si detecta un pedido nuevo, reproduce `noti.mp3` automáticamente
3. Muestra una notificación visual

## 📍 Dónde está activo:

- ✅ **Dashboard de empleados** (empleados/dashboard.php)
- ✅ **Panel de admin principal** (admin/index.php)
- ✅ **Ver pedidos admin** (admin/modules/pedidos/ver_pedidos.php)

## ⚠️ Importante:

- El archivo DEBE llamarse exactamente `noti.mp3`
- Los navegadores modernos pueden bloquear la reproducción automática hasta que el usuario interactúe con la página
- Asegúrate de que el volumen del sistema esté activado

## 📝 Instrucciones:

1. Coloca tu archivo `noti.mp3` en esta carpeta
2. Asegúrate de que tenga permisos de lectura
3. El sistema funcionará automáticamente
