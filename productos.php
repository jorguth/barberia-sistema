<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");

// Verificar que sea administrador o barbero
if (!esAdmin() && !esBarbero()) {
    die("<h1>Acceso Denegado</h1><p>No tienes permisos para acceder a esta sección.</p><a href='dashboard.php'>Volver al inicio</a>");
}

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// CREAR/ACTUALIZAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    try {
        $id = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
        $nombre = trim($_POST['nombre']);
        $stock = intval($_POST['stock']);
        $precio = floatval($_POST['precio']);
        
        if ($id > 0) {
            // ACTUALIZAR
            $stmt = $conn->prepare("UPDATE producto SET nombre = ?, stock = ?, precio = ? WHERE id_producto = ?");
            $stmt->bind_param("sidi", $nombre, $stock, $precio, $id);
            $stmt->execute();
            $mensaje = "Producto actualizado correctamente";
        } else {
            // CREAR
            $stmt = $conn->prepare("INSERT INTO producto (nombre, stock, precio) VALUES (?, ?, ?)");
            $stmt->bind_param("sid", $nombre, $stock, $precio);
            $stmt->execute();
            $mensaje = "Producto registrado correctamente";
        }
        
        $tipo_mensaje = "success";
        $stmt->close();
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// AJUSTAR STOCK (AGREGAR O QUITAR)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajustar_stock'])) {
    try {
        $id = intval($_POST['id_producto_ajuste']);
        $cantidad = intval($_POST['cantidad_ajuste']);
        $tipo_ajuste = $_POST['tipo_ajuste']; // 'agregar' o 'quitar'
        
        if ($tipo_ajuste == 'agregar') {
            $stmt = $conn->prepare("UPDATE producto SET stock = stock + ? WHERE id_producto = ?");
        } else {
            $stmt = $conn->prepare("UPDATE producto SET stock = stock - ? WHERE id_producto = ? AND stock >= ?");
        }
        
        if ($tipo_ajuste == 'agregar') {
            $stmt->bind_param("ii", $cantidad, $id);
        } else {
            $stmt->bind_param("iii", $cantidad, $id, $cantidad);
        }
        
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $mensaje = "Stock ajustado correctamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "No se pudo ajustar el stock (verifica que haya suficiente cantidad)";
            $tipo_mensaje = "error";
        }
        
        $stmt->close();
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ELIMINAR PRODUCTO
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        $stmt = $conn->prepare("DELETE FROM producto WHERE id_producto = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Producto eliminado correctamente";
        $tipo_mensaje = "success";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER PRODUCTO PARA EDITAR
$producto_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare("SELECT * FROM producto WHERE id_producto = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $producto_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// CONSULTAR PRODUCTOS
try {
    $productos = $conn->query("SELECT * FROM producto ORDER BY nombre");
    
    // Calcular valor total del inventario
    $valor_inventario = $conn->query("SELECT SUM(stock * precio) as total FROM producto")->fetch_assoc()['total'] ?? 0;
    $total_productos = $conn->query("SELECT COUNT(*) as total FROM producto")->fetch_assoc()['total'] ?? 0;
    $productos_bajo_stock = $conn->query("SELECT COUNT(*) as total FROM producto WHERE stock < 5")->fetch_assoc()['total'] ?? 0;
    
} catch (mysqli_sql_exception $e) {
    $productos = false;
    $valor_inventario = 0;
    $total_productos = 0;
    $productos_bajo_stock = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Barbería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Alertas manejadas por inc/ui.php */
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .table-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .price {
            color: #28a745;
            font-weight: 600;
            font-size: 16px;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .stock-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-bajo {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-agotado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .action-links a,
        .action-links button {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        
        .action-links a:hover,
        .action-links button:hover {
            text-decoration: underline;
        }
        
        .action-links a.delete {
            color: #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .input-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        /* Modal para ajustar stock */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            animation: slideUp 0.3s;
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .modal-header h3 {
            color: #333;
            margin: 0;
        }
        
        .close {
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .close:hover {
            color: #333;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>🛍️ Gestión de Productos</h1>
                <div class="user-info">
                    <div class="user-badge">
                        👤 <?php echo htmlspecialchars(getNombreUsuario()); ?> 
                    </div>
                </div>
            </div>
        </div>

<div class="container">

    
    <!-- Estadísticas del Inventario -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-number"><?php echo $total_productos; ?></div>
            <div class="stat-label">Productos en Catálogo</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-number">$<?php echo number_format($valor_inventario, 2); ?></div>
            <div class="stat-label">Valor del Inventario</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div class="stat-number"><?php echo $productos_bajo_stock; ?></div>
            <div class="stat-label">Productos con Bajo Stock</div>
        </div>
    </div>
    
    <!-- Formulario -->
    <div class="form-section">
        <h3><?php echo $producto_editar ? 'Editar Producto' : 'Registrar Nuevo Producto'; ?></h3>
        <form method="POST">
            <?php if ($producto_editar): ?>
            <input type="hidden" name="id_producto" value="<?php echo $producto_editar['id_producto']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre del Producto <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nombre"
                        name="nombre" 
                        placeholder="Ej: Gel para Cabello"
                        value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['nombre']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="stock">Stock Inicial <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="stock"
                        name="stock" 
                        placeholder="Ej: 25"
                        min="0"
                        value="<?php echo $producto_editar ? $producto_editar['stock'] : '0'; ?>"
                        required
                    >
                    <span class="input-hint">Cantidad disponible</span>
                </div>
                
                <div class="form-group">
                    <label for="precio">Precio ($) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="precio"
                        name="precio" 
                        placeholder="Ej: 120.00"
                        step="0.01"
                        min="0"
                        value="<?php echo $producto_editar ? $producto_editar['precio'] : ''; ?>"
                        required
                    >
                    <span class="input-hint">Precio de venta</span>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn btn-primary">
                    <?php echo $producto_editar ? '💾 Actualizar Producto' : '💾 Guardar Producto'; ?>
                </button>
                <?php if ($producto_editar): ?>
                <a href="productos.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Productos -->
    <div class="table-section">
        <h3>Inventario de Productos</h3>
        
        <?php if($productos && $productos->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Stock</th>
                    <th>Precio</th>
                    <th>Valor Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $productos->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?php echo $row['id_producto']; ?></strong></td>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td>
                        <?php 
                        $stock = $row['stock'];
                        if ($stock == 0) {
                            echo '<span class="stock-badge stock-agotado">🚫 Agotado</span>';
                        } elseif ($stock < 5) {
                            echo '<span class="stock-badge stock-bajo">⚠️ ' . $stock . ' unidades</span>';
                        } else {
                            echo '<span class="stock-badge stock-ok">✓ ' . $stock . ' unidades</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="price">$<?php echo number_format($row['precio'], 2); ?></span>
                    </td>
                    <td>
                        <strong>$<?php echo number_format($row['stock'] * $row['precio'], 2); ?></strong>
                    </td>
                    <td>
                        <div class="action-links">
                            <button onclick="abrirModalStock(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>', <?php echo $row['stock']; ?>)">
                                📊 Stock
                            </button>
                            <a href="?editar=<?php echo $row['id_producto']; ?>">✏️ Editar</a>
                            <a href="?eliminar=<?php echo $row['id_producto']; ?>" 
                               class="delete"
                               onclick="event.preventDefault(); confirmacion('¿Estás seguro de eliminar este producto?', '🗑️ Eliminar', () => window.location=this.href)">
                               🗑️ Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p>No hay productos registrados en el inventario</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Ajustar Stock -->
<div id="modalStock" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Ajustar Stock</h3>
            <button class="close" onclick="cerrarModalStock()">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" id="id_producto_ajuste" name="id_producto_ajuste">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label><strong id="nombre_producto_modal">Producto</strong></label>
                <p style="color: #666; font-size: 14px;">Stock actual: <strong id="stock_actual_modal">0</strong> unidades</p>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="tipo_ajuste">Tipo de Ajuste</label>
                <select name="tipo_ajuste" id="tipo_ajuste" required>
                    <option value="agregar">➕ Agregar al stock</option>
                    <option value="quitar">➖ Quitar del stock</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="cantidad_ajuste">Cantidad</label>
                <input 
                    type="number" 
                    id="cantidad_ajuste"
                    name="cantidad_ajuste" 
                    placeholder="Ej: 10"
                    min="1"
                    required
                >
            </div>
            
            <div class="form-actions">
                <button type="submit" name="ajustar_stock" class="btn btn-primary">
                    💾 Guardar Ajuste
                </button>
                <button type="button" onclick="cerrarModalStock()" class="btn btn-secondary">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalStock(id, nombre, stockActual) {
    document.getElementById('id_producto_ajuste').value = id;
    document.getElementById('nombre_producto_modal').textContent = nombre;
    document.getElementById('stock_actual_modal').textContent = stockActual;
    document.getElementById('cantidad_ajuste').value = '';
    document.getElementById('modalStock').style.display = 'block';
}

function cerrarModalStock() {
    document.getElementById('modalStock').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalStock');
    if (event.target == modal) {
        cerrarModalStock();
    }
}
</script>

</div> <!-- .container -->
</div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

<?php include 'inc/ui.php'; ?>
</body>
</html>