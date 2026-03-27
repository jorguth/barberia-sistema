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

// CREAR/ACTUALIZAR CLIENTE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    try {
        $id = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
        $nombre = trim($_POST['nombre']);
        $telefono = trim($_POST['telefono']);
        $id_usuario = !empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : NULL;
        
        if ($id > 0) {
            // ACTUALIZAR
            $stmt = $conn->prepare("UPDATE cliente SET nombre = ?, telefono = ?, id_usuario = ? WHERE id_cliente = ?");
            $stmt->bind_param("ssii", $nombre, $telefono, $id_usuario, $id);
            $stmt->execute();
            $mensaje = "Cliente actualizado correctamente";
        } else {
            // CREAR
            $stmt = $conn->prepare("INSERT INTO cliente (nombre, telefono, id_usuario) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $nombre, $telefono, $id_usuario);
            $stmt->execute();
            $mensaje = "Cliente registrado correctamente";
        }
        
        $tipo_mensaje = "success";
        $stmt->close();
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ELIMINAR CLIENTE
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        $stmt = $conn->prepare("DELETE FROM cliente WHERE id_cliente = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Cliente eliminado correctamente";
        $tipo_mensaje = "success";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER CLIENTE PARA EDITAR
$cliente_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare("SELECT * FROM cliente WHERE id_cliente = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cliente_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// CONSULTAR CLIENTES
try {
    $clientes = $conn->query("
        SELECT c.*, u.nombre_usuario 
        FROM cliente c
        LEFT JOIN usuario u ON c.id_usuario = u.id_usuario
        ORDER BY c.id_cliente DESC
    ");
} catch (mysqli_sql_exception $e) {
    $clientes = false;
}

// OBTENER USUARIOS DISPONIBLES (solo clientes)
try {
    $usuarios_disponibles = $conn->query("
        SELECT u.id_usuario, u.nombre_usuario 
        FROM usuario u
        WHERE u.id_rol = 3 
        AND u.id_usuario NOT IN (SELECT id_usuario FROM cliente WHERE id_usuario IS NOT NULL)
        ORDER BY u.nombre_usuario
    ");
} catch (mysqli_sql_exception $e) {
    $usuarios_disponibles = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Barbería</title>
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
        
        /* Alertas manejadas por inc/ui.php */
        
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.with-user {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.no-user {
            background: #f8d7da;
            color: #721c24;
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
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>🧑‍🦱 Gestión de Clientes</h1>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">

    
    <!-- Formulario -->
    <div class="form-section">
        <h3><?php echo $cliente_editar ? 'Editar Cliente' : 'Registrar Nuevo Cliente'; ?></h3>
        <form method="POST">
            <?php if ($cliente_editar): ?>
            <input type="hidden" name="id_cliente" value="<?php echo $cliente_editar['id_cliente']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre Completo <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nombre"
                        name="nombre" 
                        placeholder="Ej: Juan Pérez García"
                        value="<?php echo $cliente_editar ? htmlspecialchars($cliente_editar['nombre']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input 
                        type="tel" 
                        id="telefono"
                        name="telefono" 
                        placeholder="Ej: 5512345678"
                        value="<?php echo $cliente_editar ? htmlspecialchars($cliente_editar['telefono']) : ''; ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="id_usuario">Vincular con Usuario (opcional)</label>
                    <select name="id_usuario" id="id_usuario">
                        <option value="">-- Sin usuario --</option>
                        <?php if($usuarios_disponibles && $usuarios_disponibles->num_rows > 0): ?>
                            <?php while($usuario = $usuarios_disponibles->fetch_assoc()): ?>
                                <option value="<?php echo $usuario['id_usuario']; ?>"
                                    <?php echo ($cliente_editar && $cliente_editar['id_usuario'] == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        <?php if($cliente_editar && $cliente_editar['id_usuario']): ?>
                            <option value="<?php echo $cliente_editar['id_usuario']; ?>" selected>
                                <?php echo htmlspecialchars($cliente_editar['nombre_usuario']); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn btn-primary">
                    <?php echo $cliente_editar ? '💾 Actualizar Cliente' : '💾 Guardar Cliente'; ?>
                </button>
                <?php if ($cliente_editar): ?>
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Clientes -->
    <div class="table-section">
        <h3>Lista de Clientes</h3>
        
        <?php if($clientes && $clientes->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Usuario Vinculado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $clientes->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?php echo $row['id_cliente']; ?></strong></td>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($row['telefono'] ?? 'Sin teléfono'); ?></td>
                    <td>
                        <?php if($row['id_usuario']): ?>
                            <span class="badge with-user">
                                👤 <?php echo htmlspecialchars($row['nombre_usuario']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge no-user">Sin usuario</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-links">
                            <a href="?editar=<?php echo $row['id_cliente']; ?>">✏️ Editar</a>
                            <a href="?eliminar=<?php echo $row['id_cliente']; ?>" 
                               class="delete"
                               onclick="event.preventDefault(); confirmacion('¿Estás seguro de eliminar este cliente?\nTambién se eliminarán todas sus citas.', '🗑️ Eliminar', () => window.location=this.href)">
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
            <p>No hay clientes registrados</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'inc/ui.php'; ?>
</body>
</html>