<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");

// Verificar que sea administrador
if (!esAdmin()) {
    die("<h1>Acceso Denegado</h1><p>Solo los administradores pueden acceder a esta sección.</p><a href='dashboard.php'>Volver al inicio</a>");
}

// LÓGICA DE REGISTRO DE USUARIOS
$mensaje = "";
$tipo_mensaje = "";

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar'])) {
    try {
        $nombre = trim($_POST['nombre_usuario']);
        $pass = $_POST['contrasena']; 
        $rol = $_POST['id_rol'];

        $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_rol) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $pass, $rol);
        
        if ($stmt->execute()) {
            $mensaje = "Usuario guardado con éxito";
            $tipo_mensaje = "success";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al registrar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// LÓGICA PARA ELIMINAR USUARIO
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        
        if ($id == $_SESSION['id_usuario']) {
            $mensaje = "No puedes eliminar tu propio usuario";
            $tipo_mensaje = "warning";
        } else {
            $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Usuario eliminado correctamente";
            $tipo_mensaje = "success";
        }
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// CONSULTA DE USUARIOS PARA LA TABLA
try {
    $usuarios = $conn->query("
        SELECT u.*, r.nombre_rol 
        FROM usuario u
        LEFT JOIN rol r ON u.id_rol = r.id_rol
        ORDER BY u.id_usuario DESC
    ");
} catch (mysqli_sql_exception $e) {
    $usuarios = false;
}

// OBTENER ROLES DESDE LA BASE DE DATOS
try {
    $roles = $conn->query("SELECT * FROM rol ORDER BY id_rol");
} catch (mysqli_sql_exception $e) {
    $roles = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Barbería</title>
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
            background: #ffffff;
            color: #1c1e21;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
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
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-title {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title h2 {
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: #666;
            font-size: 14px;
        }
        
        /* Form Section */
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
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Table Section */
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
        
        .rol-tag {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .rol-tag.administrador {
            background: #fee;
            color: #c33;
        }
        
        .rol-tag.barbero {
            background: #efe;
            color: #2d7d32;
        }
        
        .rol-tag.cliente {
            background: #e1f5fe;
            color: #0277bd;
        }
        
        .btn-delete {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            color: #c0392b;
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>👤 Gestión de Usuarios</h1>
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
    <div class="page-title">
        <h2>Administración de Usuarios</h2>
        <p>Crea, edita y elimina usuarios del sistema</p>
    </div>
    
    <!-- Formulario de Registro -->
    <div class="form-section">
        <h3>Registrar Nuevo Usuario</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre_usuario">Nombre de Usuario *</label>
                    <input 
                        type="text" 
                        id="nombre_usuario"
                        name="nombre_usuario" 
                        placeholder="Ej: juan.perez"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Contraseña *</label>
                    <input 
                        type="password" 
                        id="contrasena"
                        name="contrasena" 
                        placeholder="Mínimo 6 caracteres"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="id_rol">Rol *</label>
                    <select name="id_rol" id="id_rol" required>
                        <option value="">-- Seleccionar --</option>
                        <?php if($roles && $roles->num_rows > 0): ?>
                            <?php while($rol = $roles->fetch_assoc()): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <button type="submit" name="registrar">Guardar Usuario</button>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Usuarios -->
    <div class="table-section">
        <h3>Lista de Usuarios Registrados</h3>
        
        <?php if($usuarios && $usuarios->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?php echo $row['id_usuario']; ?></strong></td>
                    <td><?php echo htmlspecialchars($row['nombre_usuario']); ?></td>
                    <td>
                        <span class="rol-tag <?php echo strtolower($row['nombre_rol']); ?>">
                            <?php echo htmlspecialchars($row['nombre_rol']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['id_usuario'] != $_SESSION['id_usuario']): ?>
                        <a href="?eliminar=<?php echo $row['id_usuario']; ?>" 
                           class="btn-delete" 
                           onclick="event.preventDefault(); confirmacion('¿Estás seguro de eliminar este usuario?', '🗑️ Eliminar', () => window.location=this.href)">
                           🗑️ Eliminar
                        </a>
                        <?php else: ?>
                        <span style="color: #999; font-size: 13px;">Tu usuario</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p>No hay usuarios registrados en el sistema</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</div> <!-- .container -->
</div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

<?php include 'inc/ui.php'; ?>
</body>
</html>