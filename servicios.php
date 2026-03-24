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

// CREAR/ACTUALIZAR SERVICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    try {
        $id = isset($_POST['id_servicio']) ? intval($_POST['id_servicio']) : 0;
        $nombre = trim($_POST['nombre']);
        $duracion = intval($_POST['duracion_minutos']);
        $precio = floatval($_POST['precio']);
        
        if ($id > 0) {
            // ACTUALIZAR
            $stmt = $conn->prepare("UPDATE servicio SET nombre = ?, duracion_minutos = ?, precio = ? WHERE id_servicio = ?");
            $stmt->bind_param("sidi", $nombre, $duracion, $precio, $id);
            $stmt->execute();
            $mensaje = "Servicio actualizado correctamente";
        } else {
            // CREAR
            $stmt = $conn->prepare("INSERT INTO servicio (nombre, duracion_minutos, precio) VALUES (?, ?, ?)");
            $stmt->bind_param("sid", $nombre, $duracion, $precio);
            $stmt->execute();
            $mensaje = "Servicio registrado correctamente";
        }
        
        $tipo_mensaje = "success";
        $stmt->close();
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ELIMINAR SERVICIO
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        $stmt = $conn->prepare("DELETE FROM servicio WHERE id_servicio = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Servicio eliminado correctamente";
        $tipo_mensaje = "success";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER SERVICIO PARA EDITAR
$servicio_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare("SELECT * FROM servicio WHERE id_servicio = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $servicio_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// CONSULTAR SERVICIOS
try {
    $servicios = $conn->query("SELECT * FROM servicio ORDER BY nombre");
} catch (mysqli_sql_exception $e) {
    $servicios = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Servicios - Barbería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #333;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .form-group input {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
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
        
        .duration {
            color: #667eea;
            font-weight: 500;
        }
        
        .action-links {
            display: flex;
            gap: 10px;
        }
        
        .action-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .action-links a:hover {
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
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>✂️ Gestión de Servicios</h1>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">
    <?php if (!empty($mensaje)): ?>
    <div class="alert <?php echo $tipo_mensaje; ?>">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <div class="form-section">
        <h3><?php echo $servicio_editar ? 'Editar Servicio' : 'Registrar Nuevo Servicio'; ?></h3>
        <form method="POST">
            <?php if ($servicio_editar): ?>
            <input type="hidden" name="id_servicio" value="<?php echo $servicio_editar['id_servicio']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre del Servicio <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nombre"
                        name="nombre" 
                        placeholder="Ej: Corte de Cabello"
                        value="<?php echo $servicio_editar ? htmlspecialchars($servicio_editar['nombre']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="duracion_minutos">Duración (minutos) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="duracion_minutos"
                        name="duracion_minutos" 
                        placeholder="Ej: 30"
                        min="1"
                        value="<?php echo $servicio_editar ? $servicio_editar['duracion_minutos'] : ''; ?>"
                        required
                    >
                    <span class="input-hint">Tiempo aproximado del servicio</span>
                </div>
                
                <div class="form-group">
                    <label for="precio">Precio ($) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="precio"
                        name="precio" 
                        placeholder="Ej: 150.00"
                        step="0.01"
                        min="0"
                        value="<?php echo $servicio_editar ? $servicio_editar['precio'] : ''; ?>"
                        required
                    >
                    <span class="input-hint">Precio en pesos mexicanos</span>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn btn-primary">
                    <?php echo $servicio_editar ? '💾 Actualizar Servicio' : '💾 Guardar Servicio'; ?>
                </button>
                <?php if ($servicio_editar): ?>
                <a href="servicios.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Servicios -->
    <div class="table-section">
        <h3>Catálogo de Servicios</h3>
        
        <?php if($servicios && $servicios->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Servicio</th>
                    <th>Duración</th>
                    <th>Precio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $servicios->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?php echo $row['id_servicio']; ?></strong></td>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td>
                        <span class="duration">⏱️ <?php echo $row['duracion_minutos']; ?> min</span>
                    </td>
                    <td>
                        <span class="price">$<?php echo number_format($row['precio'], 2); ?></span>
                    </td>
                    <td>
                        <div class="action-links">
                            <a href="?editar=<?php echo $row['id_servicio']; ?>">✏️ Editar</a>
                            <a href="?eliminar=<?php echo $row['id_servicio']; ?>" 
                               class="delete"
                               onclick="return confirm('¿Estás seguro de eliminar este servicio?')">
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
            <p>No hay servicios registrados</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>