<?php
/**
 * admin/cola_impresion.php
 * Helper para encolar trabajos a la app de escritorio "Estación de Impresión"
 * (estacion-impresion/), que los levanta sola por api-impresion/.
 *
 * Vive en un archivo propio, separado de config.php, porque config.php
 * tiene las credenciales y en más de un deploy quedó sin actualizar en el
 * servidor. Cada página que encola trabajos lo incluye explícitamente:
 * así, aunque config.php sea viejo, el cajón y las comandas siguen andando.
 *
 *   $accion = 'imprimir_comanda'  → imprime la comanda del pedido
 *   $accion = 'abrir_cajon'       → solo abre el cajón de dinero (cobro en efectivo)
 *
 * Nunca rompe el flujo que la llama: si algo falla devuelve false y lo deja
 * en el error_log. Si falta la columna `accion` (migración
 * migrations/add_accion_cola_impresion.php sin correr), la agrega sola y
 * reintenta, para que el cajón no quede "mudo" sin que nadie se entere.
 */

if (!function_exists('encolarTrabajoImpresion')) {
    function encolarTrabajoImpresion(PDO $pdo, int $pedido_id, string $ubicacion, string $accion = 'imprimir_comanda'): bool {
        $prefijo = $accion === 'abrir_cajon' ? 'cajon' : 'comanda';
        // Sufijo aleatorio: dos clics en el mismo segundo no chocan contra el UNIQUE de `codigo`
        $codigo = $prefijo . '_' . $pedido_id . '_' . time() . '_' . bin2hex(random_bytes(2));
        $sql = "INSERT INTO cola_impresion (pedido_id, codigo, ubicacion, accion, estado) VALUES (?, ?, ?, ?, 'pendiente')";

        try {
            $pdo->prepare($sql)->execute([$pedido_id, $codigo, $ubicacion, $accion]);
            return true;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'accion') !== false && stripos($msg, 'column') !== false) {
                try {
                    $pdo->exec("ALTER TABLE cola_impresion ADD COLUMN accion VARCHAR(30) NOT NULL DEFAULT 'imprimir_comanda' AFTER ubicacion");
                    $pdo->prepare($sql)->execute([$pedido_id, $codigo, $ubicacion, $accion]);
                    return true;
                } catch (\Throwable $e2) {
                    $msg = $e2->getMessage();
                }
            }
            error_log("cola_impresion: no se pudo encolar '$accion' del pedido #$pedido_id ($ubicacion): $msg");
            return false;
        }
    }
}

if (!function_exists('debeAbrirCajonLocal1')) {
    /**
     * ¿Hay que abrir el cajón de Local 1 por este cobro en efectivo?
     * Sí cuando el pedido es de Local 1, o cuando quien cobra es personal de
     * Local 1 (que también entrega en mostrador los pedidos de reparto que se
     * cargan como Fábrica). Un admin cobrando un pedido de Fábrica no lo abre.
     */
    function debeAbrirCajonLocal1(?string $ubicacion_pedido, ?string $ubicacion_staff): bool {
        return $ubicacion_pedido === 'Local 1' || $ubicacion_staff === 'Local 1';
    }
}
