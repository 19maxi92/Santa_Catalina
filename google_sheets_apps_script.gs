// google_sheets_apps_script.gs
// Código del Google Apps Script que recibe los pedidos desde google_sheets_helper.php
// (Extensiones → Apps Script en el Sheet). Se guarda acá solo para tenerlo versionado:
// después de cambiarlo hay que pegarlo en Apps Script y publicar una NUEVA VERSIÓN de la
// misma implementación (Implementar → Administrar implementaciones → lápiz → Versión: Nueva),
// así la URL que usa el PHP no cambia.

const SHEET_ID = '1ZTPwFz6ZECN5D_E0K81ZrnncqhaoiBMHwQOORKi-U7U';
const HEADERS = [
  'ID', 'Fecha/Hora', 'Nombre', 'Apellido', 'Teléfono', 'Dirección',
  'Producto', 'Cantidad', 'Precio', 'Pago', 'Modalidad',
  'Ubicación', 'Estado', 'Fecha Entrega', 'Observaciones'
];
// Columnas: 1=ID 2=Fecha/Hora 3=Nombre 4=Apellido 5=Teléfono 6=Dirección
//           7=Producto 8=Cantidad 9=Precio 10=Pago 11=Modalidad 12=Ubicación
//           13=Estado 14=Fecha Entrega 15=Observaciones

function findSheet(ss, nombre) {
  const sheets = ss.getSheets();
  for (let s of sheets) {
    if (s.getName().toLowerCase() === nombre.toLowerCase()) return s;
  }
  return ss.insertSheet(nombre);
}

function setupSheet(sheet) {
  const maxCols = sheet.getMaxColumns();
  if (maxCols < HEADERS.length) {
    sheet.insertColumnsAfter(maxCols, HEADERS.length - maxCols);
  }
  const hRng = sheet.getRange(1, 1, 1, HEADERS.length);
  hRng.setValues([HEADERS]);
  hRng.setBackground('#1a73e8').setFontColor('#ffffff').setFontWeight('bold');
  sheet.setFrozenRows(1);
  sheet.getRange(2, 2, 2000, 1).setNumberFormat('@');  // Fecha/Hora
  sheet.getRange(2, 14, 2000, 1).setNumberFormat('@'); // Fecha Entrega
}

function actualizarHeaders() {
  const ss = SpreadsheetApp.openById(SHEET_ID);
  ['pedidos_comunes', 'pedidos_online'].forEach(function(nombre) {
    const sheet = findSheet(ss, nombre);
    setupSheet(sheet);
    Logger.log(nombre + ': OK — ' + sheet.getMaxColumns() + ' cols');
  });
}

function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    const ss = SpreadsheetApp.openById(SHEET_ID);

    if (data.action === 'marcar_eliminado') {
      ['pedidos_comunes', 'pedidos_online'].forEach(function(nombre) {
        const sheet = findSheet(ss, nombre);
        const lastRow = sheet.getLastRow();
        if (lastRow < 2) return;
        const ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
        for (let i = 0; i < ids.length; i++) {
          if (data.ids.indexOf(Number(ids[i][0])) !== -1) {
            sheet.getRange(i + 2, 1, 1, sheet.getMaxColumns())
                 .setBackground('#f44336').setFontColor('#ffffff');
          }
        }
      });
      return ContentService.createTextOutput('ok');
    }

    if (data.action === 'actualizar_estado') {
      ['pedidos_comunes', 'pedidos_online'].forEach(function(nombre) {
        const sheet = findSheet(ss, nombre);
        const lastRow = sheet.getLastRow();
        if (lastRow < 2) return;
        const ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
        for (let i = 0; i < ids.length; i++) {
          if (Number(ids[i][0]) === Number(data.id)) {
            sheet.getRange(i + 2, 13).setValue(data.estado); // col 13 = Estado

            // Al marcar Entregado, el sistema también manda cómo pagó el cliente
            // (Efectivo / Transferencia) y el precio final (puede cambiar por el
            // descuento en efectivo). Si no vienen, no se toca nada más.
            if (data.forma_pago) {
              sheet.getRange(i + 2, 10).setValue(data.forma_pago); // col 10 = Pago
            }
            if (data.precio !== undefined && data.precio !== null && data.precio !== '') {
              sheet.getRange(i + 2, 9).setValue(Number(data.precio)); // col 9 = Precio
            }
          }
        }
      });
      return ContentService.createTextOutput('ok');
    }

    const sheetName = data.tipo === 'online' ? 'pedidos_online' : 'pedidos_comunes';
    const sheet = findSheet(ss, sheetName);

    if (sheet.getRange(1, 1).getValue() !== 'ID') {
      setupSheet(sheet);
    }

    const fila = [
      data.id,            //  1  ID
      data.fecha_hora,    //  2  Fecha/Hora  ← campo unificado
      data.nombre,        //  3  Nombre
      data.apellido,      //  4  Apellido
      data.telefono,      //  5  Teléfono
      data.direccion,     //  6  Dirección
      data.producto,      //  7  Producto
      data.cantidad,      //  8  Cantidad
      data.precio,        //  9  Precio
      data.forma_pago,    // 10  Pago
      data.modalidad,     // 11  Modalidad
      data.ubicacion,     // 12  Ubicación
      data.estado,        // 13  Estado
      data.fecha_entrega, // 14  Fecha Entrega
      data.observaciones  // 15  Observaciones
    ];

    const nuevaFila = sheet.getLastRow() + 1;

    sheet.getRange(nuevaFila, 2).setNumberFormat('@');  // Fecha/Hora como texto
    sheet.getRange(nuevaFila, 14).setNumberFormat('@'); // Fecha Entrega como texto

    sheet.getRange(nuevaFila, 1, 1, HEADERS.length).setValues([fila]);

    sheet.getRange(nuevaFila, 1, 1, sheet.getMaxColumns())
         .setBackground('#ffffff').setFontColor('#000000').setFontWeight('normal');

    return ContentService.createTextOutput('ok');

  } catch(err) {
    return ContentService.createTextOutput('error: ' + err.toString());
  }
}
