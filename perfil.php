<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// ACTUALIZAR PERFIL DE USUARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_usuario'])) {
    try {
        $nombre_usuario = trim($_POST['nombre_usuario']);
        $contrasena_actual = $_POST['contrasena_actual'];
        $contrasena_nueva = $_POST['contrasena_nueva'];
        $confirmar_nueva = $_POST['confirmar_nueva'];
        
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT contrasena FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $_SESSION['id_usuario']);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        
        if ($resultado['contrasena'] !== $contrasena_actual) {
            $mensaje = "La contraseña actual es incorrecta";
            $tipo_mensaje = "error";
        } else {
            // Si quiere cambiar contraseña
            if (!empty($contrasena_nueva)) {
                if ($contrasena_nueva !== $confirmar_nueva) {
                    $mensaje = "Las contraseñas nuevas no coinciden";
                    $tipo_mensaje = "error";
                } elseif (strlen($contrasena_nueva) < 6) {
                    $mensaje = "La contraseña debe tener al menos 6 caracteres";
                    $tipo_mensaje = "error";
                } else {
                    // Actualizar con nueva contraseña
                    $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario = ?, contrasena = ? WHERE id_usuario = ?");
                    $stmt->bind_param("ssi", $nombre_usuario, $contrasena_nueva, $_SESSION['id_usuario']);
                    $stmt->execute();
                    $_SESSION['nombre_usuario'] = $nombre_usuario;
                    $mensaje = "Perfil actualizado correctamente (contraseña cambiada)";
                    $tipo_mensaje = "success";
                }
            } else {
                // Actualizar solo nombre de usuario
                $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario = ? WHERE id_usuario = ?");
                $stmt->bind_param("si", $nombre_usuario, $_SESSION['id_usuario']);
                $stmt->execute();
                $_SESSION['nombre_usuario'] = $nombre_usuario;
                $mensaje = "Perfil actualizado correctamente";
                $tipo_mensaje = "success";
            }
        }
        
        $stmt->close();
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ACTUALIZAR DATOS DE CLIENTE (si es cliente)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_cliente'])) {
    try {
        $nombre_completo = trim($_POST['nombre_completo']);
        $telefono = trim($_POST['telefono']);
        
        $stmt = $conn->prepare("UPDATE cliente SET nombre = ?, telefono = ? WHERE id_usuario = ?");
        $stmt->bind_param("ssi", $nombre_completo, $telefono, $_SESSION['id_usuario']);
        $stmt->execute();
        $stmt->close();
        
        $mensaje = "Información de cliente actualizada correctamente";
        $tipo_mensaje = "success";
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER DATOS DEL USUARIO ACTUAL
try {
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol 
        FROM usuario u
        LEFT JOIN rol r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?
    ");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    $usuario_actual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    die("Error al cargar perfil");
}

// OBTENER DATOS DE CLIENTE (si es cliente)
$datos_cliente = null;
if ($_SESSION['id_rol'] == 3) {
    try {
        $stmt = $conn->prepare("SELECT * FROM cliente WHERE id_usuario = ?");
        $stmt->bind_param("i", $_SESSION['id_usuario']);
        $stmt->execute();
        $datos_cliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $datos_cliente = null;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Barbería</title>
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
            max-width: 900px;
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
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Alertas manejadas por inc/ui.php */
        
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
        }
        
        .profile-header h2 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-role {
            display: inline-block;
            padding: 6px 15px;
            background: #e1f5fe;
            color: #0277bd;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
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
        
        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .required {
            color: #e74c3c;
        }
        
        .input-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        .divider {
            margin: 30px 0;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>⚙️ Mi Perfil</h1>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">

    
    <!-- Cabecera del Perfil -->
    <div class="profile-header">
        <div class="profile-avatar">👤</div>
        <h2><?php echo htmlspecialchars($usuario_actual['nombre_usuario']); ?></h2>
        <span class="profile-role"><?php echo htmlspecialchars($usuario_actual['nombre_rol']); ?></span>
    </div>
    
    <!-- Formulario de Usuario -->
    <div class="form-section">
        <h3>Información de Cuenta</h3>
        <form method="POST">
            <div class="form-group">
                <label for="id_usuario">ID de Usuario</label>
                <input 
                    type="text" 
                    id="id_usuario"
                    value="#<?php echo $usuario_actual['id_usuario']; ?>"
                    readonly
                >
            </div>
            
            <div class="form-group">
                <label for="nombre_usuario">Nombre de Usuario <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="nombre_usuario"
                    name="nombre_usuario" 
                    value="<?php echo htmlspecialchars($usuario_actual['nombre_usuario']); ?>"
                    required
                >
            </div>
            
            <div class="divider"></div>
            
            <h4 style="color: #667eea; margin-bottom: 15px;">Cambiar Contraseña</h4>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Deja en blanco si no deseas cambiar tu contraseña
            </p>
            
            <div class="form-group">
                <label for="contrasena_actual">Contraseña Actual <span class="required">*</span></label>
                <input 
                    type="password" 
                    id="contrasena_actual"
                    name="contrasena_actual" 
                    placeholder="Ingresa tu contraseña actual"
                    required
                >
                <span class="input-hint">Requerida para confirmar cambios</span>
            </div>
            
            <div class="form-group">
                <label for="contrasena_nueva">Nueva Contraseña (opcional)</label>
                <input 
                    type="password" 
                    id="contrasena_nueva"
                    name="contrasena_nueva" 
                    placeholder="Mínimo 6 caracteres"
                >
            </div>
            
            <div class="form-group">
                <label for="confirmar_nueva">Confirmar Nueva Contraseña</label>
                <input 
                    type="password" 
                    id="confirmar_nueva"
                    name="confirmar_nueva" 
                    placeholder="Repite la nueva contraseña"
                >
            </div>
            
            <button type="submit" name="actualizar_usuario" class="btn btn-primary">
                💾 Actualizar Información
            </button>
        </form>
    </div>
    
    <!-- Formulario de Cliente (solo si es cliente) -->
    <?php if ($datos_cliente): ?>
    <div class="form-section">
        <h3>Información Personal</h3>
        <form method="POST">
            <div class="form-group">
                <label for="nombre_completo">Nombre Completo <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="nombre_completo"
                    name="nombre_completo" 
                    value="<?php echo htmlspecialchars($datos_cliente['nombre']); ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input 
                    type="tel" 
                    id="telefono"
                    name="telefono" 
                    value="<?php echo htmlspecialchars($datos_cliente['telefono'] ?? ''); ?>"
                    placeholder="Ej: 5512345678"
                >
            </div>
            
            <button type="submit" name="actualizar_cliente" class="btn btn-primary">
                💾 Actualizar Datos Personales
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include 'inc/ui.php'; ?>
</body>
</html>