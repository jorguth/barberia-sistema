<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");

// Verificar que sea administrador
if (!esAdmin()) {
    die("<h1>Acceso Denegado</h1><p>Solo los administradores pueden acceder a esta sección.</p><a href='dashboard.php'>Volver al inicio</a>");
}

// LÓGICA DE REGISTRO DE USUARIOS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar'])) {
    try {
        $nombre = trim($_POST['nombre_usuario']);
        $pass = $_POST['contrasena']; 
        $rol = $_POST['id_rol'];

        $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_rol) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $pass, $rol);
        
        if ($stmt->execute()) {
            echo "<script>alert('¡Usuario guardado con éxito!'); window.location='usuarios.php';</script>";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        echo "<div style='color:red; background:#ffe; padding:10px; margin: 20px;'>Error al registrar: " . $e->getMessage() . "</div>";
    }
}

// LÓGICA PARA ELIMINAR USUARIO
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        
        // No permitir eliminar al usuario actual
        if ($id == $_SESSION['id_usuario']) {
            echo "<script>alert('No puedes eliminar tu propio usuario'); window.location='usuarios.php';</script>";
            exit();
        }
        
        $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: usuarios.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        echo "<div style='color:red; margin: 20px;'>Error al eliminar: " . $e->getMessage() . "</div>";
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #333;
        }
        
        /* Header */
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

<div class="header">
    <div class="header-content">
        <h1>👤 Gestión de Usuarios</h1>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
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
                           onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
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

</body>
</html>