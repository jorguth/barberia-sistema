<?php
require_once("auth.php");
require_once("conexion.php");

if (!esAdmin() && !esBarbero()) {
    die("Acceso Denegado");
}

$det_tipo    = $_GET['det_tipo']    ?? 'ambos';
$det_periodo = $_GET['det_periodo'] ?? 'semana';
$det_fecha   = $_GET['det_fecha']   ?? date('Y-m-d');

if ($det_periodo == 'semana') {
    $ts = strtotime($det_fecha);
    $dia_semana = date('w', $ts);
    if ($dia_semana == 1) {
        $lunes_ts = $ts;
    } else {
        $lunes_ts = strtotime('last monday', $ts);
    }
    $det_desde = date('Y-m-d', $lunes_ts);
    $det_hasta = date('Y-m-d', strtotime('next sunday', $lunes_ts));
    $titulo_periodo = "Semana del " . date('d/m/Y', strtotime($det_desde)) . " al " . date('d/m/Y', strtotime($det_hasta));
} else {
    $ts = strtotime($det_fecha);
    $det_desde = date('Y-m-01', $ts);
    $det_hasta = date('Y-m-t', $ts);
    $titulo_periodo = "Mes de " . date('F Y', $ts);
}

$det_data = [];

if ($det_tipo == 'citas' || $det_tipo == 'ambos') {
    $res_citas = $conn->query("
        SELECT 'Cita' as tipo, c.fecha as fecha_ref, c.hora, cl.nombre as cliente, c.total_general as monto, 'Servicio(s)' as detalle
        FROM cita c
        LEFT JOIN cliente cl ON c.id_cliente = cl.id_cliente
        WHERE c.estado='Completada' AND c.fecha BETWEEN '$det_desde' AND '$det_hasta'
    ");
    while($r = $res_citas->fetch_assoc()) $det_data[] = $r;
}

if ($det_tipo == 'productos' || $det_tipo == 'ambos') {
    $res_prod = $conn->query("
        SELECT 'Producto' as tipo, v.fecha_venta as fecha_ref, '' as hora, cl.nombre as cliente, vd.subtotal as monto, p.nombre as detalle
        FROM venta_detalle vd
        JOIN venta v ON vd.id_venta = v.id_venta
        JOIN producto p ON vd.id_producto = p.id_producto
        LEFT JOIN cliente cl ON v.id_cliente = cl.id_cliente
        WHERE v.estado='Activa' AND v.fecha_venta >= '$det_desde 00:00:00' AND v.fecha_venta <= '$det_hasta 23:59:59'
    ");
    while($r = $res_prod->fetch_assoc()) $det_data[] = $r;
}

usort($det_data, function($a, $b) {
    $cmp = strcmp($a['fecha_ref'], $b['fecha_ref']);
    if ($cmp === 0) return strcmp($a['hora'], $b['hora']);
    return $cmp;
});

$total_monto = 0;
foreach($det_data as $item) $total_monto += $item['monto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Operaciones - Barbería</title>
    <!-- Incluir html2pdf.js para descarga directa -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Estilos base */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; line-height: 1.4; background: #525659; }
        
        /* Contenedor para el reporte real */
        .page-container {
            background: white;
            width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.4);
            box-sizing: border-box;
            position: relative;
        }

        .header { text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #1a1a2e; font-size: 22px; }
        .header p { margin: 3px 0 0; color: #666; font-size: 13px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; table-layout: fixed; }
        th { background: #f0f2f8; color: #333; padding: 10px; text-align: left; font-size: 12px; border-bottom: 2px solid #ddd; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 12px; word-wrap: break-word; }
        
        .footer { margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .summary { background: #f8f9ff; padding: 12px; border-radius: 8px; border: 1px solid #e0e0e0; min-width: 180px; }
        .summary p { margin: 3px 0; font-size: 13px; }
        .summary .total { font-size: 16px; font-weight: 700; color: #667eea; border-top: 1px solid #ddd; padding-top: 5px; margin-top: 5px; }

        /* Barra de herramientas */
        .toolbar {
            background: #323639;
            padding: 10px 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-print { background: #5a6fd6; color: white; }
        .btn-download { background: #10b981; color: white; }
        .btn-close { background: #ffffff; color: #333; border: 1px solid #ddd; }

        @media print {
            .toolbar { display: none; }
            @page { margin: 0; } /* Elimina encabezados y pies de página del navegador */
            body { background: white; margin: 1.5cm; padding: 0; }
            .page-container { margin: 0; box-shadow: none; width: 100%; padding: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn btn-print" onclick="window.print()">
        🖨️ Imprimir Reporte
    </button>
    <button class="btn btn-download" onclick="descargarPDF()">
        💾 Guardar como PDF
    </button>
    <button class="btn btn-close" onclick="window.parent.cerrarModalReporte()">
        Cerrar Vista Previa
    </button>
</div>

<div class="page-container" id="reporte-content">
    <div class="header">
        <h1>Reporte Detallado de Operaciones</h1>
        <p><?= $titulo_periodo ?></p>
        <p>Generado el: <?= date('d/m/Y H:i') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Fecha</th>
                <th style="width: 10%;">Hora</th>
                <th style="width: 12%;">Tipo</th>
                <th style="width: 33%;">Concepto / Producto</th>
                <th style="width: 18%;">Cliente</th>
                <th style="width: 12%;">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($det_data)): ?>
                <?php foreach ($det_data as $item): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($item['fecha_ref'])) ?></td>
                    <td><?= $item['hora'] ? substr($item['hora'],0,5) : '—' ?></td>
                    <td><?= $item['tipo'] ?></td>
                    <td><?= htmlspecialchars($item['detalle']) ?></td>
                    <td><?= htmlspecialchars($item['cliente'] ?? 'Venta Mostrador') ?></td>
                    <td style="text-align: right; font-weight: 600;">$<?= number_format($item['monto'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #999;">No hay registros para este periodo.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div style="font-size: 11px; color: #888;">
            Este documento es un reporte interno del sistema de Barbería.
        </div>
        <div class="summary">
            <p>Total Registros: <strong><?= count($det_data) ?></strong></p>
            <p class="total">Total: $<?= number_format($total_monto, 2) ?></p>
        </div>
    </div>
</div>

<script>
    function descargarPDF() {
        const element = document.getElementById('reporte-content');
        
        // Quitar sombra temporalmente para que no mueva el contenido en el PDF
        element.style.boxShadow = 'none';

        const opt = {
            margin: 0,
            filename: 'reporte_barberia_<?= date('Ymd_His') ?>.pdf',
            image: { type: 'jpeg', quality: 1 },
            html2canvas: { 
                scale: 2, 
                useCORS: true, 
                letterRendering: true,
                scrollY: 0
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        // Ejecutar la descarga y luego restaurar la sombra
        html2pdf().from(element).set(opt).save().then(() => {
            element.style.boxShadow = '0 0 20px rgba(0,0,0,0.4)';
        });
    }
</script>

</body>
</html>
