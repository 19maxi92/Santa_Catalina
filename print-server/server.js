// ============================================================
// Servidor Local de Impresion - Santa Catalina
// Reemplaza QZ Tray para impresion directa sin dialogo
// Escucha en http://localhost:3000
// ============================================================

const http = require('http')
const fs   = require('fs')
const path = require('path')

// ---- Cargar config ----
let config = { puerto: 3000, nombreImpresora: '' }
try {
    const raw = fs.readFileSync(path.join(__dirname, 'config.json'), 'utf8')
    config = Object.assign(config, JSON.parse(raw))
} catch(e) {
    console.warn('No se pudo leer config.json, usando valores por defecto')
}

// ---- Cargar modulo de impresion ----
let printerLib = null
try {
    printerLib = require('printer')
} catch(e) {
    console.error('\n[ERROR] No se pudo cargar el modulo "printer"')
    console.error('  Solucion: ejecuta instalar.bat y reinicia el servidor\n')
}

// ============================================================
// Generador ESC/POS (comandos para impresora termica 80mm)
// ============================================================
const ESC = 0x1B
const GS  = 0x1D

function generarEscPos(data) {
    const partes = []
    const pushBytes = (...bytes) => partes.push(Buffer.from(bytes))
    const pushTexto = (str)  => partes.push(Buffer.from(str + '\n', 'latin1'))
    const pushLinea = (str)  => partes.push(Buffer.from(str, 'latin1'))

    const ANCHO    = 42
    const SEPARADOR = '-'.repeat(ANCHO) + '\n'

    function centrar(str, ancho) {
        str = (str || '').slice(0, ancho)
        const pad = Math.max(0, Math.floor((ancho - str.length) / 2))
        return ' '.repeat(pad) + str
    }

    // Wrap texto a ancho dado
    function wrapTexto(str, ancho) {
        const palabras = (str || '').split(' ')
        const lineas = []
        let actual = ''
        for (const p of palabras) {
            if ((actual + ' ' + p).trim().length > ancho) {
                if (actual) lineas.push(actual.trim())
                actual = p
            } else {
                actual = (actual + ' ' + p).trim()
            }
        }
        if (actual) lineas.push(actual.trim())
        return lineas
    }

    // ESC @ - inicializar impresora
    pushBytes(ESC, 0x40)

    // Codepage latin (para acentos)
    pushBytes(ESC, 0x74, 20)

    // ---- ENCABEZADO: fecha izquierda, turno derecha ----
    pushBytes(ESC, 0x61, 0x00) // alinear izquierda
    pushBytes(ESC, 0x45, 0x01) // negrita
    pushBytes(ESC, 0x21, 0x10) // doble alto
    const fecha  = (data.fecha  || '').toUpperCase()
    const turno  = (data.turno  || '').toUpperCase()
    const header = fecha.padEnd(ANCHO - turno.length) + turno
    pushTexto(header)
    pushBytes(ESC, 0x21, 0x00) // normal
    pushBytes(ESC, 0x45, 0x00) // sin negrita
    pushLinea(SEPARADOR)

    // ---- NOMBRE CLIENTE ----
    pushBytes(ESC, 0x61, 0x01) // centrar
    pushBytes(ESC, 0x45, 0x01) // negrita
    pushBytes(ESC, 0x21, 0x10) // doble alto
    pushTexto((data.nombre || '').toUpperCase())
    pushBytes(ESC, 0x21, 0x00)
    pushBytes(ESC, 0x45, 0x00)

    // ---- PRODUCTO ----
    pushLinea(SEPARADOR)
    pushBytes(ESC, 0x61, 0x01) // centrar
    pushBytes(ESC, 0x45, 0x01) // negrita
    pushBytes(GS,  0x21, 0x11) // doble ancho + alto

    // Wrap producto si es muy largo (ancho efectivo = 42/2 = 21 chars en doble)
    const lineasProducto = wrapTexto(data.producto || '', 20)
    for (const linea of lineasProducto) {
        pushTexto(linea.toUpperCase())
    }

    pushBytes(GS,  0x21, 0x00)
    pushBytes(ESC, 0x45, 0x00)

    // ---- PRECIO ----
    pushLinea(SEPARADOR)
    pushBytes(ESC, 0x61, 0x01) // centrar
    pushBytes(ESC, 0x45, 0x01) // negrita
    pushBytes(GS,  0x21, 0x11) // doble ancho + alto
    pushTexto(data.precio || '')
    pushBytes(GS,  0x21, 0x00)
    pushBytes(ESC, 0x45, 0x00)

    // ---- OBSERVACIONES (si existen) ----
    const obs = (data.observaciones || '').trim()
    if (obs) {
        pushLinea(SEPARADOR)
        pushBytes(ESC, 0x61, 0x01) // centrar
        pushBytes(ESC, 0x45, 0x01) // negrita
        pushBytes(ESC, 0x21, 0x10) // doble alto
        pushTexto('OBSERVACIONES')
        pushBytes(ESC, 0x21, 0x00)
        pushBytes(ESC, 0x45, 0x00)
        pushBytes(ESC, 0x61, 0x00) // izquierda
        pushBytes(ESC, 0x21, 0x10) // doble alto

        // Wrap observaciones
        const lineasObs = wrapTexto(obs.toUpperCase(), ANCHO)
        for (const linea of lineasObs) {
            pushTexto(linea)
        }
        pushBytes(ESC, 0x21, 0x00)
    }

    // ---- PIE ----
    pushBytes(ESC, 0x61, 0x00) // izquierda
    pushLinea(SEPARADOR)
    pushTexto('Modalidad: ' + (data.modalidad || '') + '   Pago: ' + (data.forma_pago || ''))
    pushTexto('Pedido #' + (data.pedido_id || ''))

    // Feed y corte
    pushBytes(ESC, 0x64, 5)         // avanzar 5 lineas
    pushBytes(GS,  0x56, 0x42, 0)  // corte total

    return Buffer.concat(partes)
}

// ============================================================
// Obtener impresora a usar
// ============================================================
function obtenerImpresora() {
    if (config.nombreImpresora && config.nombreImpresora.trim()) {
        return config.nombreImpresora.trim()
    }
    const impresoras = printerLib.getPrinters()
    const predeterminada = impresoras.find(p => p.isDefault)
    if (predeterminada) return predeterminada.name
    if (impresoras.length > 0) return impresoras[0].name
    return null
}

// ============================================================
// Servidor HTTP
// ============================================================
const server = http.createServer((req, res) => {
    // Cabeceras CORS (necesario para que el browser pueda llamar localhost)
    res.setHeader('Access-Control-Allow-Origin', '*')
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type')

    if (req.method === 'OPTIONS') {
        res.writeHead(204)
        res.end()
        return
    }

    // ---- GET /ping - chequeo de estado ----
    if (req.method === 'GET' && req.url === '/ping') {
        let impresoras = []
        let impresoraPredeterminada = '(no disponible)'
        try {
            if (printerLib) {
                impresoras = printerLib.getPrinters().map(p => ({ nombre: p.name, predeterminada: !!p.isDefault }))
                impresoraPredeterminada = obtenerImpresora() || '(ninguna)'
            }
        } catch(e) {}

        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' })
        res.end(JSON.stringify({
            ok: true,
            version: '1.0',
            impresoraConfigurada: config.nombreImpresora || '(predeterminada)',
            impresoraActiva: impresoraPredeterminada,
            impresorasDisponibles: impresoras,
            moduloOk: !!printerLib
        }, null, 2))
        return
    }

    // ---- POST /imprimir - imprimir comanda ----
    if (req.method === 'POST' && req.url === '/imprimir') {
        let body = ''
        req.on('data', chunk => { body += chunk })
        req.on('end', () => {
            try {
                const data = JSON.parse(body)

                if (!printerLib) {
                    res.writeHead(500, { 'Content-Type': 'application/json' })
                    res.end(JSON.stringify({ ok: false, error: 'Modulo printer no cargado. Ejecutar instalar.bat' }))
                    return
                }

                const nombreImpresora = obtenerImpresora()
                if (!nombreImpresora) {
                    res.writeHead(500, { 'Content-Type': 'application/json' })
                    res.end(JSON.stringify({ ok: false, error: 'No se encontro impresora. Configurar config.json o instalar impresora en Windows' }))
                    return
                }

                const escposBuffer = generarEscPos(data)

                printerLib.printDirect({
                    data: escposBuffer,
                    type: 'RAW',
                    printer: nombreImpresora,
                    success: (jobId) => {
                        console.log(`[OK] Pedido #${data.pedido_id || '?'} → "${nombreImpresora}" (job: ${jobId})`)
                        res.writeHead(200, { 'Content-Type': 'application/json' })
                        res.end(JSON.stringify({ ok: true, job: jobId, impresora: nombreImpresora }))
                    },
                    error: (err) => {
                        console.error(`[ERROR] Pedido #${data.pedido_id || '?'}:`, err)
                        res.writeHead(500, { 'Content-Type': 'application/json' })
                        res.end(JSON.stringify({ ok: false, error: String(err) }))
                    }
                })

            } catch(e) {
                console.error('[ERROR] Request invalido:', e.message)
                res.writeHead(400, { 'Content-Type': 'application/json' })
                res.end(JSON.stringify({ ok: false, error: 'JSON invalido: ' + e.message }))
            }
        })
        return
    }

    // ---- GET / - pagina de estado simple ----
    if (req.method === 'GET' && req.url === '/') {
        let impresoras = []
        try {
            if (printerLib) impresoras = printerLib.getPrinters()
        } catch(e) {}

        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Servidor Impresion - Santa Catalina</title>
<style>body{font-family:monospace;background:#1a1a1a;color:#00ff00;padding:20px;}
h1{color:#fff;} .ok{color:#00ff00;} .err{color:#ff4444;}
table{border-collapse:collapse;} td,th{padding:6px 12px;border:1px solid #444;}</style>
</head><body>
<h1>Servidor de Impresion - Santa Catalina</h1>
<p class="ok">Estado: ACTIVO en puerto ${config.puerto}</p>
<p>Modulo printer: <span class="${printerLib ? 'ok' : 'err'}">${printerLib ? 'OK' : 'ERROR - ejecutar instalar.bat'}</span></p>
<p>Impresora configurada: ${config.nombreImpresora || '(predeterminada)'}</p>
<h2>Impresoras disponibles:</h2>
<table><tr><th>Nombre</th><th>Estado</th><th>Predeterminada</th></tr>
${impresoras.map(p => `<tr><td>${p.name}</td><td>${p.status}</td><td>${p.isDefault ? 'SI' : '-'}</td></tr>`).join('')}
</table>
<h2>Endpoints:</h2>
<ul>
<li>GET /ping - Estado en JSON</li>
<li>POST /imprimir - Imprimir comanda (JSON body)</li>
</ul>
</body></html>`

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' })
        res.end(html)
        return
    }

    res.writeHead(404)
    res.end('Not found')
})

const PUERTO = config.puerto || 3000

server.listen(PUERTO, '127.0.0.1', () => {
    console.log('\n============================================')
    console.log('  Servidor de Impresion - Santa Catalina')
    console.log(`  http://localhost:${PUERTO}`)
    console.log(`  Impresora: ${config.nombreImpresora || '(predeterminada de Windows)'}`)
    console.log('============================================\n')

    if (printerLib) {
        try {
            const impresoras = printerLib.getPrinters()
            if (impresoras.length === 0) {
                console.warn('[AVISO] No se encontraron impresoras instaladas en Windows')
            } else {
                console.log('Impresoras disponibles:')
                impresoras.forEach(p => {
                    console.log(`  ${p.isDefault ? '→' : ' '} ${p.name}`)
                })
            }
        } catch(e) {
            console.warn('[AVISO] No se pudo listar impresoras:', e.message)
        }
    }

    console.log('\nEsperando pedidos para imprimir...')
})

server.on('error', (e) => {
    if (e.code === 'EADDRINUSE') {
        console.error(`\n[ERROR] Puerto ${PUERTO} ya esta en uso.`)
        console.error('  El servidor ya puede estar corriendo.')
        console.error('  Si no, cambiar "puerto" en config.json\n')
    } else {
        console.error('[ERROR] Servidor:', e.message)
    }
    process.exit(1)
})
