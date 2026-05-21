<?php
require_once("auth.php");
require_once("conexion.php");
require_once("inc/pagination_helper.php");

if (!esAdmin() && !esBarbero()) {
    die("<h1>Acceso Denegado</h1><a href='dashboard.php'>Volver</a>");
}

/* ============================================================
   FILTROS DE PERÍODO
   ============================================================ */
$periodo  = $_GET['periodo'] ?? 'mes';
$anio     = intval($_GET['anio'] ?? date('Y'));

switch ($periodo) {
    case 'todo':
        $desde = '2020-01-01'; 
        $hasta = date('Y-m-d');
        break;
    case 'semana':
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'anio':
        $desde = "$anio-01-01";
        $hasta = "$anio-12-31";
        break;
    case 'personalizado':
        $desde = !empty($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
        $hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');
        break;
    default: // mes
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        break;
}

$where_sql = " AND fecha_venta >= '$desde 00:00:00' AND fecha_venta <= '$hasta 23:59:59'";

/* ============================================================
   KPIs PRINCIPALES (Solo ventas activas)
   ============================================================ */
$ingr_ventas  = (float)($conn->query("SELECT SUM(total) t FROM venta WHERE estado='Activa' $where_sql")->fetch_assoc()['t'] ?? 0);
$num_ventas   = (int)($conn->query("SELECT COUNT(*) t FROM venta WHERE estado='Activa' $where_sql")->fetch_assoc()['t'] ?? 0);
$num_citas    = (int)($conn->query("SELECT COUNT(*) t FROM cita WHERE estado='Completada' AND fecha BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$ingr_citas   = (float)($conn->query("SELECT SUM(total_general) t FROM cita WHERE estado='Completada' AND fecha BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$total_ingr   = $ingr_ventas + $ingr_citas;
$ticket_prom  = ($num_ventas + $num_citas) > 0 ? $total_ingr / ($num_ventas + $num_citas) : 0;
$total_clientes = (int)($conn->query("SELECT COUNT(*) t FROM cliente WHERE activo = 1")->fetch_assoc()['t'] ?? 0);

// KPIs de Cancelaciones
$num_canceladas = (int)($conn->query("SELECT COUNT(*) t FROM venta WHERE estado='Cancelada' $where_sql")->fetch_assoc()['t'] ?? 0);
$monto_cancelado = (float)($conn->query("SELECT SUM(total) t FROM venta WHERE estado='Cancelada' $where_sql")->fetch_assoc()['t'] ?? 0);

/* ============================================================
   GRÁFICA 1 – Ingresos por mes (últimos 12 meses)
   ============================================================ */
$meses_labels = [];
$meses_ventas = [];
$meses_citas  = [];

for ($i = 11; $i >= 0; $i--) {
    $fecha_mes = date('Y-m', strtotime("-$i months"));
    [$y, $m]   = explode('-', $fecha_mes);
    $meses_labels[] = date('M Y', mktime(0,0,0,$m,1,$y));

    $v = (float)($conn->query("SELECT SUM(total) t FROM venta WHERE estado='Activa' AND YEAR(fecha_venta)=$y AND MONTH(fecha_venta)=$m")->fetch_assoc()['t'] ?? 0);
    $c = (float)($conn->query("SELECT SUM(total_general) t FROM cita WHERE estado='Completada' AND YEAR(fecha)=$y AND MONTH(fecha)=$m")->fetch_assoc()['t'] ?? 0);
    $meses_ventas[] = $v;
    $meses_citas[]  = $c;
}

/* ============================================================
   GRÁFICA 2 – Top 5 servicios más populares
   ============================================================ */
$top_servicios = $conn->query("
    SELECT s.nombre, COUNT(cs.id_cita_servicio) AS veces
    FROM cita_servicio cs JOIN servicio s ON cs.id_servicio = s.id_servicio
    JOIN cita c ON cs.id_cita = c.id_cita
    WHERE c.fecha BETWEEN '$desde' AND '$hasta'
    GROUP BY cs.id_servicio ORDER BY veces DESC LIMIT 5
");
$tsrv_labels = [];
$tsrv_data   = [];
while ($r = $top_servicios->fetch_assoc()) {
    $tsrv_labels[] = $r['nombre'];
    $tsrv_data[]   = (int)$r['veces'];
}

/* ============================================================
   GRÁFICA 3 – Ventas por método de pago
   ============================================================ */
$pago_res = $conn->query("
    SELECT metodo_pago, COUNT(*) AS cant, SUM(total) AS total
    FROM venta WHERE estado='Activa' $where_sql
    GROUP BY metodo_pago
");
$pago_labels = [];
$pago_data   = [];
while ($r = $pago_res->fetch_assoc()) {
    $pago_labels[] = $r['metodo_pago'];
    $pago_data[]   = round((float)$r['total'], 2);
}

/* ============================================================
   TABLA – Top productos vendidos (Paginado)
   ============================================================ */
$pag_prod = getPaginationData($conn, "SELECT COUNT(DISTINCT vd.id_producto) as total 
                                     FROM venta_detalle vd
                                     JOIN venta v ON vd.id_venta = v.id_venta
                                     WHERE v.estado = 'Activa' $where_sql", 10, 'p_prod');
$top_productos = $conn->query("
    SELECT p.nombre, SUM(vd.cantidad) AS total_cant, SUM(vd.subtotal) AS total_ing
    FROM venta_detalle vd
    JOIN producto p ON vd.id_producto = p.id_producto
    JOIN venta v ON vd.id_venta = v.id_venta
    WHERE v.estado='Activa' $where_sql
    GROUP BY vd.id_producto ORDER BY total_cant DESC LIMIT 10 OFFSET {$pag_prod['offset']}
");

/* ============================================================
   TABLA – Últimas citas completadas (Paginado)
   ============================================================ */
$pag_citas = getPaginationData($conn, "SELECT COUNT(*) as total FROM cita WHERE estado = 'Completada' AND fecha BETWEEN '$desde' AND '$hasta'", 10, 'p_citas');
$ult_citas = $conn->query("
    SELECT c.id_cita, c.fecha, c.hora, c.total_general, c.metodo_pago,
           cl.nombre AS cliente
    FROM cita c
    LEFT JOIN cliente cl ON c.id_cliente = cl.id_cliente
    WHERE c.estado='Completada' AND c.fecha BETWEEN '$desde' AND '$hasta'
    ORDER BY c.fecha DESC, c.hora DESC LIMIT 10 OFFSET {$pag_citas['offset']}
");

$pag_cancel = getPaginationData($conn, "SELECT COUNT(*) as total FROM venta WHERE estado = 'Cancelada' $where_sql", 10, 'p_cancel');
$ventas_canceladas = $conn->query("
    SELECT v.id_venta, v.fecha_venta, v.total, v.motivo_cancelacion, 
           c.nombre AS cliente, u.nombre_usuario AS vendedor
    FROM venta v
    LEFT JOIN cliente c ON v.id_cliente = c.id_cliente
    LEFT JOIN usuario u ON v.id_usuario = u.id_usuario
    WHERE v.estado='Cancelada' $where_sql
    ORDER BY v.fecha_venta DESC LIMIT 10 OFFSET {$pag_cancel['offset']}
");

/* ============================================================
   REPORTE DETALLADO (Citas y Productos)
   ============================================================ */
$det_tipo    = $_GET['det_tipo']    ?? 'ambos';
$det_periodo = $_GET['det_periodo'] ?? 'semana';
$det_fecha   = $_GET['det_fecha']   ?? date('Y-m-d'); 

if ($det_periodo == 'semana') {
    $ts = strtotime($det_fecha);
    $dia_semana = date('w', $ts);
    $lunes_ts = ($dia_semana == 1) ? $ts : strtotime('last monday', $ts);
    $det_desde = date('Y-m-d', $lunes_ts);
    $det_hasta = date('Y-m-d', strtotime('next sunday', $lunes_ts));
} else {
    $ts = strtotime($det_fecha);
    $det_desde = date('Y-m-01', $ts);
    $det_hasta = date('Y-m-t', $ts);
}

$det_data_all = [];

if ($det_tipo == 'citas' || $det_tipo == 'ambos') {
    $res_citas = $conn->query("SELECT 'Cita' as tipo, c.fecha as fecha_ref, c.hora, cl.nombre as cliente, c.total_general as monto, 'Servicio(s)' as detalle FROM cita c LEFT JOIN cliente cl ON c.id_cliente = cl.id_cliente WHERE c.estado='Completada' AND c.fecha BETWEEN '$det_desde' AND '$det_hasta'");
    while($r = $res_citas->fetch_assoc()) $det_data_all[] = $r;
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
    while($r = $res_prod->fetch_assoc()) $det_data_all[] = $r;
}

// Ordenar por fecha y luego por hora
usort($det_data_all, function($a, $b) {
    $cmp = strcmp($a['fecha_ref'], $b['fecha_ref']);
    if ($cmp === 0) return strcmp($a['hora'], $b['hora']);
    return $cmp;
});

// Paginación para reporte detallado (Ahora que ya tenemos todos los datos ordenados)
$total_det_records = count($det_data_all);
$limit_det = 10;
$pag_det_current = isset($_GET['p_det']) ? max(1, intval($_GET['p_det'])) : 1;
$total_det_pages = ceil($total_det_records / $limit_det);
$offset_det = ($pag_det_current - 1) * $limit_det;
$det_data = array_slice($det_data_all, $offset_det, $limit_det);

$total_monto = 0;
foreach($det_data_all as $r) $total_monto += (float)$r['monto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema Barbería</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f8;
            color: #333;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            overflow-y: auto;
            background: #f0f2f5;
        }

        /* ---- HEADER ---- */
        .header {
            background: #ffffff;
            color: #1c1e21;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-content {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-badge {
            background: #f0f2f5;
            color: #333;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 500;
        }
        .btn-logout {
            background: #fff;
            color: #ef4444;
            padding: 6px 16px;
            border: 1px solid #ef4444;
            border-radius: 16px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            background: #ef4444;
            color: white;
        }

        .container { max-width: 1300px; margin: 28px auto; padding: 0 24px; }

        /* ---- FILTROS ---- */
        .filter-strip {
            background: white;
            border-radius: 14px;
            padding: 16px 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-strip label { font-size: 13px; font-weight: 600; color: #555; }
        .btn-periodo {
            padding: 8px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-periodo:hover, .btn-periodo.active {
            border-color: #667eea;
            color: #667eea;
            background: #f0f2ff;
        }
        .filter-strip input[type=date] {
            padding: 7px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
        }
        .btn-aplicar {
            padding: 8px 18px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }

        /* ---- KPI CARDS ---- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: white;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
        }
        .kpi-card.green::before  { background: #10b981; }
        .kpi-card.purple::before { background: #667eea; }
        .kpi-card.blue::before   { background: #3b82f6; }
        .kpi-card.orange::before { background: #f59e0b; }
        .kpi-card.pink::before   { background: #ec4899; }

        .kpi-icon  { font-size: 28px; margin-bottom: 10px; }
        .kpi-val   { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .kpi-label { font-size: 13px; color: #888; }

        /* ---- CHARTS GRID ---- */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media(max-width:900px) { .charts-grid { grid-template-columns: 1fr; } }
        .chart-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .chart-header {
            padding: 16px 20px;
            border-bottom: 2px solid #667eea;
            font-size: 15px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-body { padding: 20px; }
        .chart-full { grid-column: 1 / -1; }

        /* ---- TABLES ---- */
        .table-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 24px;
            scroll-margin-top: 80px;
        }
        .table-header {
            padding: 16px 20px;
            border-bottom: 2px solid #667eea;
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }
        table.rep { width: 100%; border-collapse: collapse; font-size: 13px; }
        .rep th {
            background: #667eea;
            color: white;
            padding: 11px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .rep td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
        .rep tr:hover td { background: #f8f9ff; }
        .rep tr:last-child td { border-bottom: none; }

        .rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px; height: 24px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
        }
        .rank-1 { background: #fef08a; color: #854d0e; }
        .rank-2 { background: #e5e7eb; color: #374151; }
        .rank-3 { background: #fed7aa; color: #7c2d12; }
        .rank-n { background: #f3f4f6; color: #6b7280; }

        .badge-pago {
            display: inline-block; padding: 3px 10px;
            border-radius: 12px; font-size: 11px; font-weight: 700;
        }
        .badge-Efectivo     { background: #d1fae5; color: #065f46; }
        .badge-Tarjeta      { background: #dbeafe; color: #1d4ed8; }
        .badge-Transferencia{ background: #ede9fe; color: #5b21b6; }

        .empty-state { text-align: center; padding: 40px; color: #bbb; font-size: 14px; }

        /* Estilos nuevos para filtros detallados */
        .det-filters {
            display: flex; gap: 15px; align-items: center; padding: 15px 20px;
            background: #f8f9ff; border-bottom: 1px solid #e0e0e0; flex-wrap: wrap;
        }
        .det-filters select, .det-filters input {
            padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;
        }
        .btn-pdf {
            background: #ef4444; color: white; padding: 8px 15px; border-radius: 8px;
            text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-pdf:hover { background: #dc2626; transform: scale(1.02); }

        <?= getPaginationStyles() ?>

        /* ============ MODAL ============ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>📊 Reportes y Estadísticas</h1>
                <div class="user-info">
                    <div class="user-badge">
                        👤 <?php echo htmlspecialchars(getNombreUsuario()); ?> 
                        (<?php echo htmlspecialchars(getRolUsuario()); ?>)
                    </div>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>
        </div>

<div class="container">

    <!-- ===== FILTROS ===== -->
    <form method="GET" class="filter-strip">
        <label>Período:</label>
        <a href="?periodo=semana" class="btn-periodo <?= $periodo=='semana'?'active':'' ?>">Esta semana</a>
        <a href="?periodo=mes"    class="btn-periodo <?= $periodo=='mes'?'active':'' ?>">Este mes</a>
        <a href="?periodo=anio&anio=<?= $anio ?>" class="btn-periodo <?= $periodo=='anio'?'active':'' ?>">Este año</a>
        <a href="?periodo=todo"   class="btn-periodo <?= $periodo=='todo'?'active':'' ?>">General</a>
        <span style="color:#ccc">|</span>
        <input type="date" name="desde" value="<?= $periodo=='personalizado'?$desde:'' ?>" placeholder="Desde">
        <input type="date" name="hasta" value="<?= $periodo=='personalizado'?$hasta:'' ?>" placeholder="Hasta">
        <input type="hidden" name="periodo" value="personalizado">
        <button type="submit" class="btn-aplicar">📅 Aplicar</button>
        <span style="color:#aaa;font-size:12px;margin-left:auto;">
            <?= date('d/m/Y', strtotime($desde)) ?> — <?= date('d/m/Y', strtotime($hasta)) ?>
        </span>
    </form>

    <!-- ===== KPIs ===== -->
    <div class="kpi-grid">
        <div class="kpi-card green">
            <div class="kpi-icon">💰</div>
            <div class="kpi-val">$<?= number_format($total_ingr, 2) ?></div>
            <div class="kpi-label">Ingresos Totales</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon">🛒</div>
            <div class="kpi-val">$<?= number_format($ingr_ventas, 2) ?></div>
            <div class="kpi-label">Ventas de Productos (<?= $num_ventas ?>)</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon">✂️</div>
            <div class="kpi-val">$<?= number_format($ingr_citas, 2) ?></div>
            <div class="kpi-label">Ingresos por Citas (<?= $num_citas ?>)</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-val">$<?= number_format($ticket_prom, 2) ?></div>
            <div class="kpi-label">Ticket Promedio</div>
        </div>
        <div class="kpi-card pink">
            <div class="kpi-icon">👥</div>
            <div class="kpi-val"><?= $total_clientes ?></div>
            <div class="kpi-label">Clientes Registrados</div>
        </div>
    </div>

    <!-- ===== GRÁFICAS ===== -->
    <div class="charts-grid">

        <!-- Gráfica 1: Ingresos por mes (barra) -->
        <div class="chart-card chart-full">
            <div class="chart-header">📈 Ingresos Mensuales (últimos 12 meses)</div>
            <div class="chart-body">
                <canvas id="chartMeses" height="90"></canvas>
            </div>
        </div>

        <!-- Gráfica 2: Top servicios (dona) -->
        <div class="chart-card">
            <div class="chart-header">✂️ Servicios Más Populares</div>
            <div class="chart-body" style="max-height:280px;display:flex;align-items:center;justify-content:center;">
                <?php if (!empty($tsrv_data)): ?>
                <canvas id="chartServicios"></canvas>
                <?php else: ?>
                <div class="empty-state">Sin datos de servicios en el período.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gráfica 3: Método de pago (pie) -->
        <div class="chart-card">
            <div class="chart-header">💳 Ventas por Método de Pago</div>
            <div class="chart-body" style="max-height:280px;display:flex;align-items:center;justify-content:center;">
                <?php if (!empty($pago_data)): ?>
                <canvas id="chartPago"></canvas>
                <?php else: ?>
                <div class="empty-state">Sin ventas en el período.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ===== TABLAS ===== -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

        <!-- Top productos -->
        <div class="table-card" id="top_prod">
            <div class="table-header">🛍️ Top Productos Vendidos</div>
            <?php if ($top_productos && $top_productos->num_rows > 0): ?>
            <table class="rep">
                <thead><tr><th>#</th><th>Producto</th><th>Und. Vendidas</th><th>Ingreso</th></tr></thead>
                <tbody>
                <?php $rank=1; while ($r = $top_productos->fetch_assoc()): ?>
                <tr>
                    <td><span class="rank rank-<?= $rank <= 3 ? $rank : 'n' ?>"><?= $rank ?></span></td>
                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                    <td><strong><?= $r['total_cant'] ?></strong></td>
                    <td style="color:#10b981;font-weight:700;">$<?= number_format($r['total_ing'], 2) ?></td>
                </tr>
                <?php $rank++; endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">Sin ventas de productos en el período.</div>
            <?php endif; ?>
            <?= renderPagination($pag_prod['current_page'], $pag_prod['total_pages'], $_SERVER['QUERY_STRING'] ?? '', 'p_prod', '#top_prod') ?>
        </div>

        <!-- Últimas citas completadas -->
        <div class="table-card" id="ult_citas">
            <div class="table-header">📅 Citas Completadas Recientes</div>
            <?php if ($ult_citas && $ult_citas->num_rows > 0): ?>
            <table class="rep">
                <thead><tr><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pago</th></tr></thead>
                <tbody>
                <?php while ($r = $ult_citas->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cliente'] ?? '—') ?></td>
                    <td><?= date('d/m', strtotime($r['fecha'])) ?> <?= substr($r['hora'],0,5) ?></td>
                    <td style="color:#667eea;font-weight:700;">$<?= number_format($r['total_general'], 2) ?></td>
                    <td>
                        <?php if ($r['metodo_pago']): ?>
                        <span class="badge-pago badge-<?= $r['metodo_pago'] ?>"><?= $r['metodo_pago'] ?></span>
                        <?php else: ?>
                        <span style="color:#ccc;font-size:11px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">Sin citas completadas en el período.</div>
            <?php endif; ?>
            <?= renderPagination($pag_citas['current_page'], $pag_citas['total_pages'], $_SERVER['QUERY_STRING'] ?? '', 'p_citas', '#ult_citas') ?>
        </div>

    <!-- ===== TABLA CANCELACIONES ===== -->
    <div class="table-card" id="ventas_cancel" style="margin-top: 20px;">
        <div class="table-header" style="border-bottom-color: #ef4444; color: #ef4444;">🚫 Ventas Canceladas en el Período</div>
        <?php if ($ventas_canceladas && $ventas_canceladas->num_rows > 0): ?>
        <table class="rep">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Total</th>
                    <th>Motivo de Cancelación</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = $ventas_canceladas->fetch_assoc()): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($r['fecha_venta'])) ?></td>
                <td><?= htmlspecialchars($r['cliente'] ?? 'Sin cliente') ?></td>
                <td><?= htmlspecialchars($r['vendedor'] ?? '—') ?></td>
                <td style="color:#ef4444; font-weight:700;">$<?= number_format($r['total'], 2) ?></td>
                <td style="font-style: italic; color: #666;"><?= htmlspecialchars($r['motivo_cancelacion'] ?: 'Sin motivo especificado') ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No se registraron cancelaciones en este período.</div>
        <?php endif; ?>
        <?= renderPagination($pag_cancel['current_page'], $pag_cancel['total_pages'], $_SERVER['QUERY_STRING'] ?? '', 'p_cancel', '#ventas_cancel') ?>
    </div>

    <!-- ===== REPORTE DETALLADO ===== -->
    <div class="table-card" style="margin-top: 30px;" id="seccion-detallada">
        <div class="table-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span>📋 Reporte Detallado de Operaciones</span>
            <button type="button" class="btn-pdf" onclick="abrirModalReporte()">
                📄 Generar PDF
            </button>
        </div>
        
        <form method="GET" action="#seccion-detallada" class="det-filters">
            <!-- Mantener filtros globales -->
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <input type="hidden" name="desde" value="<?= $_GET['desde'] ?? '' ?>">
            <input type="hidden" name="hasta" value="<?= $_GET['hasta'] ?? '' ?>">

            <div>
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;">Ver:</label>
                <select name="det_tipo">
                    <option value="ambos" <?= $det_tipo=='ambos'?'selected':'' ?>>Citas y Productos</option>
                    <option value="citas" <?= $det_tipo=='citas'?'selected':'' ?>>Solo Citas</option>
                    <option value="productos" <?= $det_tipo=='productos'?'selected':'' ?>>Solo Productos</option>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;">Frecuencia:</label>
                <select name="det_periodo">
                    <option value="semana" <?= $det_periodo=='semana'?'selected':'' ?>>Semana</option>
                    <option value="mes" <?= $det_periodo=='mes'?'selected':'' ?>>Mes</option>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;">Fecha Ref:</label>
                <input type="date" name="det_fecha" value="<?= $det_fecha ?>">
            </div>

            <div style="align-self: flex-end;">
                <button type="submit" class="btn-aplicar" style="padding: 7px 15px;">🔍 Filtrar</button>
            </div>

            <div style="margin-left: auto; text-align: right; font-size: 12px; color: #666;">
                Mostrando: <strong><?= date('d/m/Y', strtotime($det_desde)) ?></strong> al <strong><?= date('d/m/Y', strtotime($det_hasta)) ?></strong>
            </div>
        </form>

        <?php if (!empty($det_data)): ?>
        <table class="rep">
            <thead>
                <tr>
                    <th>Fecha / Hora</th>
                    <th>Tipo</th>
                    <th>Concepto</th>
                    <th>Cliente</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($det_data as $item): ?>
            <tr>
                <td>
                    <?= date('d/m/Y', strtotime($item['fecha_ref'])) ?> 
                    <span style="color:#888;"><?= $item['hora'] ? substr($item['hora'],0,5) : '' ?></span>
                </td>
                <td>
                    <span style="padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; color:white; background: <?= $item['tipo']=='Cita'?'#667eea':'#10b981' ?>;">
                        <?= $item['tipo'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($item['detalle']) ?></td>
                <td><?= htmlspecialchars($item['cliente'] ?? 'Venta Mostrador') ?></td>
                <td style="font-weight:700;">$<?= number_format($item['monto'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No se encontraron registros para los filtros seleccionados.</div>
        <?php endif; ?>
        <?= renderPagination($pag_det_current, $total_det_pages, $_SERVER['QUERY_STRING'] ?? '', 'p_det', '#seccion-detallada') ?>
    </div>

</div><!-- /container -->

<!-- Modal Reporte -->
<div id="modalReporte" class="modal-overlay">
    <div class="modal" style="max-width: 1100px; width: 95%; background: #525659; border: none;">
        <div class="modal-body" style="padding: 0; height: 90vh; background: #525659; border-radius: 16px; overflow: hidden;">
            <iframe id="frameReporte" src="" style="width: 100%; height: 100%; border: none; display: block;"></iframe>
        </div>
    </div>
</div>

<script>
function abrirModalReporte() {
    const tipo = document.querySelector('[name="det_tipo"]').value;
    const periodo = document.querySelector('[name="det_periodo"]').value;
    const fecha = document.querySelector('[name="det_fecha"]').value;
    
    const url = `reporte_imprimible.php?det_tipo=${tipo}&det_periodo=${periodo}&det_fecha=${fecha}`;
    document.getElementById('frameReporte').src = url;
    document.getElementById('modalReporte').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarModalReporte() {
    document.getElementById('modalReporte').classList.remove('active');
    document.getElementById('frameReporte').src = "";
    document.body.style.overflow = 'auto';
}

/* ===== PALETA ===== */
const PURPLE = 'rgba(102,126,234,1)';
const PURPLE_L = 'rgba(102,126,234,0.15)';
const VIOLET = 'rgba(118,75,162,1)';
const GREEN  = 'rgba(16,185,129,1)';
const GREEN_L = 'rgba(16,185,129,0.15)';

Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";

/* ===== GRÁFICA 1: INGRESOS POR MES ===== */
new Chart(document.getElementById('chartMeses'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses_labels) ?>,
        datasets: [
            {
                label: 'Ventas Directas ($)',
                data: <?= json_encode($meses_ventas) ?>,
                backgroundColor: 'rgba(102,126,234,0.8)',
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Citas Completadas ($)',
                data: <?= json_encode($meses_citas) ?>,
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderRadius: 6,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ` $${parseFloat(ctx.parsed.y).toFixed(2)}`
                }
            }
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => `$${v}` }
            }
        }
    }
});

<?php if (!empty($tsrv_data)): ?>
/* ===== GRÁFICA 2: SERVICIOS ===== */
new Chart(document.getElementById('chartServicios'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($tsrv_labels) ?>,
        datasets: [{
            data: <?= json_encode($tsrv_data) ?>,
            backgroundColor: [
                'rgba(102,126,234,0.85)',
                'rgba(118,75,162,0.85)',
                'rgba(16,185,129,0.85)',
                'rgba(245,158,11,0.85)',
                'rgba(239,68,68,0.85)',
            ],
            borderWidth: 2,
            borderColor: 'white',
        }]
    },
    options: {
        responsive: true,
        cutout: '60%',
        plugins: {
            legend: { position: 'right', labels: { font: { size: 12 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} veces` } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($pago_data)): ?>
/* ===== GRÁFICA 3: MÉTODO DE PAGO ===== */
new Chart(document.getElementById('chartPago'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($pago_labels) ?>,
        datasets: [{
            data: <?= json_encode($pago_data) ?>,
            backgroundColor: [
                'rgba(16,185,129,0.85)',
                'rgba(59,130,246,0.85)',
                'rgba(139,92,246,0.85)',
            ],
            borderWidth: 2,
            borderColor: 'white',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right', labels: { font: { size: 12 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: $${parseFloat(ctx.parsed).toFixed(2)}` } }
        }
    }
});
<?php endif; ?>
</script>

</div> <!-- .container -->
</div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

<?php include 'inc/ui.php'; ?>
</body>
</html>
