<?php
require_once("auth.php");
require_once("conexion.php");

if (!esAdmin() && !esBarbero()) {
    die("<h1>Acceso Denegado</h1><a href='dashboard.php'>Volver</a>");
}

/* ============================================================
   FILTROS DE PERÍODO
   ============================================================ */
$periodo  = $_GET['periodo'] ?? 'mes';
$anio     = intval($_GET['anio'] ?? date('Y'));

switch ($periodo) {
    case 'semana':
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'anio':
        $desde = "$anio-01-01";
        $hasta = "$anio-12-31";
        break;
    case 'personalizado':
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        break;
    default: // mes
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        break;
}

/* ============================================================
   KPIs PRINCIPALES
   ============================================================ */
$ingr_ventas  = (float)($conn->query("SELECT SUM(total) t FROM venta WHERE DATE(fecha_venta) BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$num_ventas   = (int)($conn->query("SELECT COUNT(*) t FROM venta WHERE DATE(fecha_venta) BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$num_citas    = (int)($conn->query("SELECT COUNT(*) t FROM cita WHERE estado='Completada' AND fecha BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$ingr_citas   = (float)($conn->query("SELECT SUM(total_general) t FROM cita WHERE estado='Completada' AND fecha BETWEEN '$desde' AND '$hasta'")->fetch_assoc()['t'] ?? 0);
$total_ingr   = $ingr_ventas + $ingr_citas;
$ticket_prom  = ($num_ventas + $num_citas) > 0 ? $total_ingr / ($num_ventas + $num_citas) : 0;
$total_clientes = (int)($conn->query("SELECT COUNT(*) t FROM cliente")->fetch_assoc()['t'] ?? 0);

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

    $v = (float)($conn->query("SELECT SUM(total) t FROM venta WHERE YEAR(fecha_venta)=$y AND MONTH(fecha_venta)=$m")->fetch_assoc()['t'] ?? 0);
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
    FROM venta WHERE DATE(fecha_venta) BETWEEN '$desde' AND '$hasta'
    GROUP BY metodo_pago
");
$pago_labels = [];
$pago_data   = [];
while ($r = $pago_res->fetch_assoc()) {
    $pago_labels[] = $r['metodo_pago'];
    $pago_data[]   = round((float)$r['total'], 2);
}

/* ============================================================
   TABLA – Top productos vendidos
   ============================================================ */
$top_productos = $conn->query("
    SELECT p.nombre, SUM(vd.cantidad) AS total_cant, SUM(vd.subtotal) AS total_ing
    FROM venta_detalle vd
    JOIN producto p ON vd.id_producto = p.id_producto
    JOIN venta v ON vd.id_venta = v.id_venta
    WHERE DATE(v.fecha_venta) BETWEEN '$desde' AND '$hasta'
    GROUP BY vd.id_producto ORDER BY total_cant DESC LIMIT 10
");

/* ============================================================
   TABLA – Últimas citas completadas
   ============================================================ */
$ult_citas = $conn->query("
    SELECT c.id_cita, c.fecha, c.hora, c.total_general, c.metodo_pago,
           cl.nombre AS cliente
    FROM cita c
    LEFT JOIN cliente cl ON c.id_cliente = cl.id_cliente
    WHERE c.estado='Completada' AND c.fecha BETWEEN '$desde' AND '$hasta'
    ORDER BY c.fecha DESC, c.hora DESC LIMIT 8
");
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 0;
            box-shadow: 0 4px 20px rgba(102,126,234,0.35);
        }
        .header-content {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 22px; font-weight: 700; }
        .btn-back {
            background: rgba(255,255,255,0.18);
            color: white;
            padding: 8px 18px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.28); }

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
                    </div>
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
        <div class="table-card">
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
        </div>

        <!-- Últimas citas completadas -->
        <div class="table-card">
            <div class="table-header">📅 Citas Completadas Recientes</div>
            <?php if ($ult_citas && $ult_citas->num_rows > 0): ?>
            <table class="rep">
                <thead><tr><th>#</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pago</th></tr></thead>
                <tbody>
                <?php while ($r = $ult_citas->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= $r['id_cita'] ?></strong></td>
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
        </div>

    </div>

</div><!-- /container -->

<script>
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
