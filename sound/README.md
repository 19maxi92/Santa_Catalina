# Carpeta de Sonidos

## 📁 Archivos requeridos:

### `noti.mp3`
- **Descripción**: Sonido de notificación para nuevos pedidos
- **Uso**: Se reproduce automáticamente en el dashboard de empleados cuando llega un nuevo pedido para Local 1
- **Formato**: MP3
- **Duración recomendada**: 1-3 segundos
- **Volumen**: Ajustado para no ser demasiado fuerte

## 🔊 Cómo funciona:

1. Cada 30 segundos, el dashboard de empleados chequea si hay nuevos pedidos para Local 1
2. Si detecta un pedido nuevo, reproduce `noti.mp3` automáticamente
3. Recarga la página después de 2 segundos para mostrar el nuevo pedido

## ⚠️ Importante:

- El archivo DEBE llamarse exactamente `noti.mp3`
- Los navegadores modernos pueden bloquear la reproducción automática hasta que el usuario interactúe con la página
- Asegúrate de que el volumen del sistema esté activado

## 📝 Instrucciones:

1. Coloca tu archivo `noti.mp3` en esta carpeta
2. Asegúrate de que tenga permisos de lectura
3. El sistema funcionará automáticamente
