<?php
require_once("auth.php");
require_once("conexion.php");

if (!esAdmin() && !esBarbero()) {
    die("<h1>Acceso Denegado</h1><p>No tienes permisos.</p><a href='dashboard.php'>Volver</a>");
}

$mensaje     = "";
$tipo_mensaje = "";

/* ============================================================
   GUARDAR VENTA
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_venta'])) {
    try {
        $id_cliente  = !empty($_POST['id_cliente'])  ? intval($_POST['id_cliente'])  : null;
        $metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
        $descuento   = floatval($_POST['descuento'] ?? 0);
        $notas       = trim($_POST['notas'] ?? '');
        $productos   = $_POST['productos']   ?? [];
        $cantidades  = $_POST['cantidades']  ?? [];

        if (empty($productos)) throw new Exception("Debes agregar al menos un producto.");

        $conn->begin_transaction();

        $subtotal = 0;
        $items    = [];

        foreach ($productos as $idx => $id_prod) {
            $id_prod  = intval($id_prod);
            $cantidad = intval($cantidades[$idx] ?? 1);
            if ($cantidad <= 0) continue;

            // Verificar stock
            $r = $conn->prepare("SELECT nombre, precio, stock FROM producto WHERE id_producto = ?");
            $r->bind_param("i", $id_prod);
            $r->execute();
            $prod = $r->get_result()->fetch_assoc();
            $r->close();

            if (!$prod) throw new Exception("Producto #$id_prod no encontrado.");
            if ($prod['stock'] < $cantidad) {
                throw new Exception("Stock insuficiente de \"{$prod['nombre']}\" (disponible: {$prod['stock']}).");
            }

            $precio_unit = floatval($prod['precio']);
            $sub_item    = $precio_unit * $cantidad;
            $subtotal   += $sub_item;

            $items[] = ['id' => $id_prod, 'cantidad' => $cantidad, 'precio' => $precio_unit, 'sub' => $sub_item];
        }

        if (empty($items)) throw new Exception("Sin ítems válidos.");

        $total       = max(0, $subtotal - $descuento);
        $id_usuario  = $_SESSION['id_usuario'];

        // Insertar venta
        $st = $conn->prepare("INSERT INTO venta (id_cliente, id_usuario, subtotal, descuento, total, metodo_pago, notas) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param("iidddss", $id_cliente, $id_usuario, $subtotal, $descuento, $total, $metodo_pago, $notas);
        $st->execute();
        $id_venta = $conn->insert_id;
        $st->close();

        foreach ($items as $item) {
            // Insertar detalle
            // El trigger 'actualizar_stock_venta_insert' descontará el stock automáticamente
            $sd = $conn->prepare("INSERT INTO venta_detalle (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
            $sd->bind_param("iiidd", $id_venta, $item['id'], $item['cantidad'], $item['precio'], $item['sub']);
            $sd->execute();
            $sd->close();
        }

        $conn->commit();
        $mensaje      = "Venta #$id_venta registrada correctamente — Total: $" . number_format($total, 2);
        $tipo_mensaje = "success";

    } catch (Exception $e) {
        if ($conn->in_transaction) $conn->rollback();
        $mensaje      = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

/* ============================================================
   ELIMINAR VENTA (solo admin)
   ============================================================ */
if (isset($_GET['eliminar']) && esAdmin()) {
    try {
        $id = intval($_GET['eliminar']);

        // La restauración del stock se maneja automáticamente en la DB 
        // mediante el trigger 'actualizar_stock_venta_delete' al eliminar la venta en cascada.

        $st = $conn->prepare("DELETE FROM venta WHERE id_venta = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $st->close();

        $mensaje      = "Venta eliminada y stock restaurado.";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje      = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

/* ============================================================
   DATOS PARA EL FORMULARIO
   ============================================================ */
$clientes  = $conn->query("SELECT id_cliente, nombre FROM cliente ORDER BY nombre");
$productos_disp = $conn->query("SELECT id_producto, nombre, precio, stock FROM producto WHERE stock > 0 ORDER BY nombre");

/* ============================================================
   HISTORIAL – con filtro de fecha
   ============================================================ */
$filtro_desde = $_GET['desde'] ?? date('Y-m-01');
$filtro_hasta = $_GET['hasta'] ?? date('Y-m-d');

$ventas = $conn->query("
    SELECT *
    FROM v_historial_ventas
    WHERE DATE(fecha_venta) BETWEEN '$filtro_desde' AND '$filtro_hasta'
    ORDER BY fecha_venta DESC
");

$total_periodo = $conn->query("
    SELECT SUM(total) as total FROM venta
    WHERE DATE(fecha_venta) BETWEEN '$filtro_desde' AND '$filtro_hasta'
")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Sistema Barbería</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .container {
            max-width: 1300px;
            margin: 28px auto;
            padding: 0 24px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }
        @media(max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

        /* ---- CARD ---- */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 24px;
            font-size: 16px;
            font-weight: 700;
        }
        .card-body { padding: 24px; }

        /* ---- FORM ---- */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 9px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
            color: #333;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        .required { color: #ef4444; }

        /* ---- TABLA DE PRODUCTOS ---- */
        #tabla-productos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 13px;
        }
        #tabla-productos th {
            background: #f3f4f6;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e5e7eb;
        }
        #tabla-productos td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        #tabla-productos select,
        #tabla-productos input[type=number] {
            width: 100%;
            padding: 7px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 7px;
            font-size: 12px;
            font-family: inherit;
        }
        .btn-rm-row {
            background: #fee2e2;
            border: none;
            color: #dc2626;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.2s;
        }
        .btn-rm-row:hover { background: #fecaca; }

        .btn-add-row {
            background: #ede9fe;
            border: none;
            color: #7c3aed;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-add-row:hover { background: #ddd6fe; }

        /* Totales */
        .totales-box {
            background: #f8f9ff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .totales-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
        }
        .totales-row.total-final {
            border-top: 2px solid #667eea;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        /* ---- BTN ---- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 11px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102,126,234,0.35);
            width: 100%;
            justify-content: center;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(102,126,234,0.45); }

        /* ---- HISTORIAL ---- */
        .historial-section { margin-top: 28px; }
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
        }
        .filter-bar .form-group { margin-bottom: 0; flex: 1; min-width: 160px; }
        .filter-bar .btn { padding: 8px 18px; }
        .btn-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .summary-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            background: #f8f9ff;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .summary-bar strong { color: #667eea; font-size: 18px; }

        table.hist { width: 100%; border-collapse: collapse; font-size: 13px; }
        .hist th {
            background: #667eea;
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .hist td { padding: 13px 16px; border-bottom: 1px solid #f0f0f0; }
        .hist tr:hover td { background: #f8f9ff; }

        .badge-pago {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-Efectivo    { background: #d1fae5; color: #065f46; }
        .badge-Tarjeta     { background: #dbeafe; color: #1d4ed8; }
        .badge-Transferencia { background: #ede9fe; color: #5b21b6; }

        .action-links { display: flex; gap: 8px; }
        .action-links a, .action-links button {
            font-size: 12px; font-weight: 600; cursor: pointer;
            padding: 4px 10px; border-radius: 6px; border: none;
            text-decoration: none; transition: all 0.2s; font-family: inherit;
        }
        .btn-ver  { background: #ede9fe; color: #5b21b6; }
        .btn-del  { background: #fee2e2; color: #dc2626; }
        .btn-ver:hover { background: #ddd6fe; }
        .btn-del:hover { background: #fecaca; }

        .empty-state { text-align: center; padding: 50px 20px; color: #bbb; }
        .empty-state .ei { font-size: 48px; margin-bottom: 12px; }

        /* ---- MODAL DETALLE VENTA ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.2s;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }

        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 18px 24px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 17px; font-weight: 700; }
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none; color: white;
            font-size: 22px; cursor: pointer;
            border-radius: 8px; width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.3); }
        .modal-body { padding: 24px; }
        .det-row {
            display: flex; justify-content: space-between;
            padding: 8px 0; border-bottom: 1px solid #f5f5f5;
            font-size: 13px;
        }
        .det-row:last-child { border-bottom: none; }
        .det-label { color: #888; font-weight: 600; }
        .det-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        .det-table th { background: #f3f4f6; padding: 8px 12px; text-align: left; font-weight: 600; color: #666; }
        .det-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>🛒 Ventas</h1>
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

    <!-- GRID: Nueva Venta + Historial -->
    <div class="grid-2">

        <!-- ===== FORMULARIO NUEVA VENTA ===== -->
        <div class="card">
            <div class="card-header">➕ Nueva Venta</div>
            <div class="card-body">
                <form method="POST" id="formVenta">

                    <div class="form-group">
                        <label>Cliente (opcional)</label>
                        <select name="id_cliente">
                            <option value="">-- Sin cliente registrado --</option>
                            <?php if ($clientes && $clientes->num_rows > 0): ?>
                                <?php while ($cl = $clientes->fetch_assoc()): ?>
                                <option value="<?= $cl['id_cliente'] ?>"><?= htmlspecialchars($cl['nombre']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Tabla de productos -->
                    <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:8px;">
                        Productos <span class="required">*</span>
                    </label>
                    <table id="tabla-productos">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style="width:80px">Cant.</th>
                                <th style="width:90px">Precio</th>
                                <th style="width:90px">Subtotal</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="agregarFila()">➕ Agregar producto</button>

                    <!-- Totales -->
                    <div class="totales-box" style="margin-top:16px;">
                        <div class="totales-row">
                            <span>Subtotal</span>
                            <span id="disp-subtotal">$0.00</span>
                        </div>
                        <div class="totales-row">
                            <span>Descuento ($)</span>
                            <span id="disp-descuento">$0.00</span>
                        </div>
                        <div class="totales-row total-final">
                            <span>TOTAL</span>
                            <span id="disp-total">$0.00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descuento ($)</label>
                        <input type="number" name="descuento" id="inp-descuento" min="0" step="0.01" value="0" oninput="recalcular()">
                    </div>

                    <div class="form-group">
                        <label>Método de Pago</label>
                        <select name="metodo_pago">
                            <option value="Efectivo">💵 Efectivo</option>
                            <option value="Tarjeta">💳 Tarjeta</option>
                            <option value="Transferencia">📲 Transferencia</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notas (opcional)</label>
                        <textarea name="notas" rows="2" placeholder="Observaciones..."></textarea>
                    </div>

                    <button type="submit" name="nueva_venta" class="btn btn-primary">💾 Registrar Venta</button>
                </form>
            </div>
        </div>

        <!-- ===== RESUMEN y mini-info ===== -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="card">
                <div class="card-header">📋 Productos Disponibles</div>
                <div class="card-body" style="padding:0;">
                    <table class="hist">
                        <thead>
                            <tr><th>Producto</th><th>Stock</th><th>Precio</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $productos_disp->data_seek(0);
                            while ($p = $productos_disp->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td>
                                    <?php if ($p['stock'] == 0): ?>
                                        <span style="color:#ef4444;font-weight:700;">Agotado</span>
                                    <?php elseif ($p['stock'] < 5): ?>
                                        <span style="color:#f59e0b;font-weight:700;">⚠️ <?= $p['stock'] ?></span>
                                    <?php else: ?>
                                        <span style="color:#10b981;font-weight:700;">✓ <?= $p['stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>$<?= number_format($p['precio'], 2) ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /grid-2 -->

    <!-- ===== HISTORIAL DE VENTAS ===== -->
    <div class="card historial-section">
        <div class="card-header">📊 Historial de Ventas</div>

        <!-- Filtro -->
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Desde</label>
                <input type="date" name="desde" value="<?= $filtro_desde ?>">
            </div>
            <div class="form-group">
                <label>Hasta</label>
                <input type="date" name="hasta" value="<?= $filtro_hasta ?>">
            </div>
            <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
        </form>

        <div class="summary-bar">
            <span>Total del período seleccionado:</span>
            <strong>$<?= number_format($total_periodo, 2) ?></strong>
        </div>

        <?php if ($ventas && $ventas->num_rows > 0): ?>
        <table class="hist">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Total</th>
                    <th>Pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($v = $ventas->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= $v['id_venta'] ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
                    <td><?= htmlspecialchars($v['nombre_cliente'] ?? 'Sin cliente') ?></td>
                    <td><?= htmlspecialchars($v['nombre_usuario'] ?? '—') ?></td>
                    <td><strong style="color:#667eea">$<?= number_format($v['total'], 2) ?></strong></td>
                    <td><span class="badge-pago badge-<?= $v['metodo_pago'] ?>"><?= $v['metodo_pago'] ?></span></td>
                    <td>
                        <div class="action-links">
                            <button class="btn-ver" onclick="verVenta(<?= $v['id_venta'] ?>)">🔍 Ver</button>
                            <?php if (esAdmin()): ?>
                            <a class="btn-del"
                               href="?eliminar=<?= $v['id_venta'] ?>&desde=<?= $filtro_desde ?>&hasta=<?= $filtro_hasta ?>"
                               onclick="event.preventDefault(); confirmacion('¿Eliminar venta #<?= $v['id_venta'] ?>? El stock será restaurado.', '🗑️ Eliminar', () => window.location=this.href)">
                               🗑️
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="ei">🛒</div>
            <p>No hay ventas en el período seleccionado.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /container -->

<!-- ===== MODAL DETALLE VENTA ===== -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-titulo">Detalle Venta</h3>
            <button class="modal-close" onclick="cerrarModal()">&#x2715;</button>
        </div>
        <div class="modal-body" id="modal-cuerpo">
            <div style="text-align:center;padding:30px;"><span style="font-size:32px;">⏳</span><p>Cargando...</p></div>
        </div>
    </div>
</div>

<?php
// Cargar datos de ventas para JS (detalle)
$ventas_js = [];
try {
    $rv = $conn->query("SELECT * FROM v_historial_ventas ORDER BY fecha_venta DESC LIMIT 200");
    while ($row = $rv->fetch_assoc()) {
        $id = $row['id_venta'];
        $detalles = [];
        $rd = $conn->query("SELECT vd.*, p.nombre FROM venta_detalle vd JOIN producto p ON vd.id_producto = p.id_producto WHERE vd.id_venta = $id");
        while ($det = $rd->fetch_assoc()) $detalles[] = $det;
        $row['detalles'] = $detalles;
        $ventas_js[$id] = $row;
    }
} catch(Exception $e) {}

// Datos de productos para el formulario
$prods_js = [];
try {
    $rp = $conn->query("SELECT id_producto, nombre, precio, stock FROM producto ORDER BY nombre");
    while ($pr = $rp->fetch_assoc()) $prods_js[] = $pr;
} catch(Exception $e) {}
?>

<script>
/* ===== DATOS ===== */
const VENTAS = <?= json_encode($ventas_js, JSON_UNESCAPED_UNICODE) ?>;
const PRODUCTOS = <?= json_encode($prods_js, JSON_UNESCAPED_UNICODE) ?>;

/* ===== TABLA DE ITEMS DINÁMICA ===== */
let rowCount = 0;

// Construir options de producto
function buildProdOptions(selectedId = 0) {
    let html = '<option value="">-- Selecciona --</option>';
    PRODUCTOS.forEach(p => {
        const sel = p.id_producto == selectedId ? 'selected' : '';
        const stock = p.stock > 0 ? `(stock: ${p.stock})` : '⚠️ agotado';
        html += `<option value="${p.id_producto}" data-precio="${p.precio}" data-stock="${p.stock}" ${sel}>${p.nombre} – $${parseFloat(p.precio).toFixed(2)} ${stock}</option>`;
    });
    return html;
}

function agregarFila(prodId = 0, cant = 1) {
    const tbody = document.getElementById('items-body');
    const id    = rowCount++;
    const tr    = document.createElement('tr');
    tr.id       = `row-${id}`;
    tr.innerHTML = `
        <td>
            <select name="productos[]" id="prod-${id}" onchange="onProdChange(${id})" required>
                ${buildProdOptions(prodId)}
            </select>
        </td>
        <td><input type="number" name="cantidades[]" id="cant-${id}" min="1" value="${cant}" oninput="recalcular()"></td>
        <td id="precio-${id}" style="font-weight:600;color:#667eea;">$0.00</td>
        <td id="sub-${id}"    style="font-weight:700;">$0.00</td>
        <td><button type="button" class="btn-rm-row" onclick="quitarFila(${id})">×</button></td>
    `;
    tbody.appendChild(tr);
    if (prodId) { onProdChange(id); }
    recalcular();
}

function onProdChange(id) {
    const sel  = document.getElementById(`prod-${id}`);
    const opt  = sel.selectedOptions[0];
    const prec = parseFloat(opt?.dataset?.precio || 0);
    document.getElementById(`precio-${id}`).textContent = `$${prec.toFixed(2)}`;
    recalcular();
}

function quitarFila(id) {
    document.getElementById(`row-${id}`)?.remove();
    recalcular();
}

function recalcular() {
    let sub = 0;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const rowId   = tr.id.replace('row-','');
        const sel     = document.getElementById(`prod-${rowId}`);
        const cantEl  = document.getElementById(`cant-${rowId}`);
        const subEl   = document.getElementById(`sub-${rowId}`);
        if (!sel || !cantEl) return;
        const precio  = parseFloat(sel.selectedOptions[0]?.dataset?.precio || 0);
        const cant    = parseInt(cantEl.value) || 0;
        const itemSub = precio * cant;
        sub += itemSub;
        if (subEl) subEl.textContent = `$${itemSub.toFixed(2)}`;
    });
    const desc  = parseFloat(document.getElementById('inp-descuento')?.value || 0);
    const total = Math.max(0, sub - desc);
    document.getElementById('disp-subtotal').textContent  = `$${sub.toFixed(2)}`;
    document.getElementById('disp-descuento').textContent = `$${desc.toFixed(2)}`;
    document.getElementById('disp-total').textContent     = `$${total.toFixed(2)}`;
}

// Agregar fila inicial
agregarFila();

/* ===== MODAL DETALLE ===== */
function verVenta(id) {
    const v = VENTAS[id];
    if (!v) return;
    document.getElementById('modal-titulo').textContent = `Venta #${id}`;
    let metodoBadge = `<span class="badge-pago badge-${v.metodo_pago}">${v.metodo_pago}</span>`;
    let detallesHtml = '';
    if (v.detalles && v.detalles.length > 0) {
        detallesHtml = `<table class="det-table">
            <thead><tr><th>Producto</th><th>Cant.</th><th>Precio Unit.</th><th>Subtotal</th></tr></thead>
            <tbody>
                ${v.detalles.map(d => `<tr>
                    <td>${d.nombre}</td>
                    <td>${d.cantidad}</td>
                    <td>$${parseFloat(d.precio_unitario).toFixed(2)}</td>
                    <td><strong>$${parseFloat(d.subtotal).toFixed(2)}</strong></td>
                </tr>`).join('')}
            </tbody>
        </table>`;
    } else {
        detallesHtml = '<p style="color:#bbb;font-size:13px;">Sin detalle disponible.</p>';
    }

    document.getElementById('modal-cuerpo').innerHTML = `
        <div class="det-row"><span class="det-label">Fecha:</span><span>${new Date(v.fecha_venta.replace(' ','T')).toLocaleString('es-MX')}</span></div>
        <div class="det-row"><span class="det-label">Cliente:</span><span>${v.nombre_cliente || 'Sin cliente'}</span></div>
        <div class="det-row"><span class="det-label">Vendedor:</span><span>${v.nombre_usuario || '—'}</span></div>
        <div class="det-row"><span class="det-label">Método:</span>${metodoBadge}</div>
        ${v.notas ? `<div class="det-row"><span class="det-label">Notas:</span><span>${v.notas}</span></div>` : ''}
        <h4 style="margin:16px 0 8px;color:#667eea;font-size:14px;">Productos</h4>
        ${detallesHtml}
        <div style="border-top:2px solid #667eea;margin-top:16px;padding-top:12px;">
            <div class="det-row"><span class="det-label">Subtotal:</span><span>$${parseFloat(v.subtotal).toFixed(2)}</span></div>
            ${parseFloat(v.descuento) > 0 ? `<div class="det-row"><span class="det-label">Descuento:</span><span style="color:#ef4444">-$${parseFloat(v.descuento).toFixed(2)}</span></div>` : ''}
            <div class="det-row" style="font-size:18px;font-weight:700;color:#667eea"><span>TOTAL:</span><span>$${parseFloat(v.total).toFixed(2)}</span></div>
        </div>
    `;
    document.getElementById('modalDetalle').classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalDetalle').classList.remove('active');
}
document.getElementById('modalDetalle').addEventListener('click', e => { if (e.target === document.getElementById('modalDetalle')) cerrarModal(); });
</script>

</div> <!-- .container -->
</div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

<?php include 'inc/ui.php'; ?>
</body>
</html>
