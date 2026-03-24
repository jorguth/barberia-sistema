<?php
// 1. CONFIGURACIÓN DE ERRORES (Para ver qué pasa)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. INCLUIR CONEXIÓN (ahora está en la raíz)
require_once("conexion.php");

// 3. LÓGICA DE REGISTRO DE USUARIOS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar'])) {
    try {
        $nombre = $_POST['nombre_usuario'];
        $pass = $_POST['contrasena']; 
        $rol = $_POST['id_rol'];

        $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_rol) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $pass, $rol);
        
        if ($stmt->execute()) {
            echo "<script>alert('¡Usuario guardado con éxito!'); window.location='index.php';</script>";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        echo "<div style='color:red; background:#ffe; padding:10px;'>Error al registrar: " . $e->getMessage() . "</div>";
    }
}

// 4. LÓGICA PARA ELIMINAR USUARIO
if (isset($_GET['eliminar'])) {
    try {
        $id = intval($_GET['eliminar']);
        $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        echo "<div style='color:red;'>Error al eliminar: " . $e->getMessage() . "</div>";
    }
}

// 5. CONSULTA DE USUARIOS PARA LA TABLA
try {
    $usuarios = $conn->query("
        SELECT u.*, r.nombre_rol 
        FROM usuario u
        LEFT JOIN rol r ON u.id_rol = r.id_rol
    ");
} catch (mysqli_sql_exception $e) {
    $usuarios = false;
}

// 6. OBTENER ROLES DESDE LA BASE DE DATOS
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
    <title>Gestión de Usuarios - Barbería</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; padding: 30px; color: #333; }
        .container { max-width: 900px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .form-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #dee2e6; }
        input, select { padding: 10px; margin: 5px; border: 1px solid #ccc; border-radius: 5px; width: 200px; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2980b9; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #2c3e50; color: white; }
        .rol-tag { background: #e1f5fe; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; color: #0277bd; }
        .rol-tag.admin { background: #ffebee; color: #c62828; }
        .rol-tag.barbero { background: #e8f5e9; color: #2e7d32; }
        .btn-delete { color: #e74c3c; text-decoration: none; font-weight: bold; }
        .btn-delete:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h1>Módulo de Usuarios</h1>

    <div class="form-section">
        <h3>Registrar Nuevo Usuario</h3>
        <form method="POST">
            <input type="text" name="nombre_usuario" placeholder="Nombre de usuario" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <select name="id_rol" required>
                <option value="">-- Seleccionar Rol --</option>
                <?php if($roles && $roles->num_rows > 0): ?>
                    <?php while($rol = $roles->fetch_assoc()): ?>
                        <option value="<?php echo $rol['id_rol']; ?>">
                            <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <button type="submit" name="registrar">Guardar Registro</button>
        </form>
    </div>

    <h3>Lista de Usuarios Registrados</h3>
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
            <?php if($usuarios && $usuarios->num_rows > 0): ?>
                <?php while($row = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id_usuario']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['nombre_usuario']); ?></strong></td>
                    <td>
                        <span class="rol-tag <?php echo strtolower($row['nombre_rol']); ?>">
                            <?php echo htmlspecialchars($row['nombre_rol']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?eliminar=<?php echo $row['id_usuario']; ?>" 
                           class="btn-delete" 
                           onclick="return confirm('¿Estás seguro de eliminar a este usuario?')">Eliminar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">No hay usuarios en la base de datos.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>