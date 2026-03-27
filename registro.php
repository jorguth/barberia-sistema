<?php
// Incluir conexión
require_once("conexion.php");

// Variables para mensajes
$error = "";
$exito = "";

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar'])) {
    $nombre = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];
    $confirmar = $_POST['confirmar_contrasena'];
    $nombre_completo = trim($_POST['nombre_completo']);
    $telefono = trim($_POST['telefono']);
    
    // Validaciones
    if (empty($nombre) || empty($contrasena) || empty($nombre_completo)) {
        $error = "Por favor, completa todos los campos obligatorios";
    } elseif ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE nombre_usuario = ?");
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows > 0) {
                $error = "Este nombre de usuario ya está registrado";
            } else {
                // Iniciar transacción
                $conn->begin_transaction();
                
                // Insertar usuario con rol Cliente (id_rol = 3)
                $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_rol) VALUES (?, ?, 3)");
                $stmt->bind_param("ss", $nombre, $contrasena);
                $stmt->execute();
                $id_usuario = $conn->insert_id;
                
                // Insertar cliente
                $stmt = $conn->prepare("INSERT INTO cliente (nombre, telefono, id_usuario) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $nombre_completo, $telefono, $id_usuario);
                $stmt->execute();
                
                // Confirmar transacción
                $conn->commit();
                
                $exito = "¡Registro exitoso! Ahora puedes iniciar sesión.";
                
                // Limpiar formulario
                $_POST = array();
            }
            
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "Error en el sistema: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema Barbería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
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
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        /* Alertas manejadas por inc/ui.php */
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .required {
            color: #c33;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="logo">
        <h1>✂️ Registro</h1>
        <p>Crea tu cuenta de cliente</p>
    </div>
    

    
    <form method="POST" action="">
        <div class="form-group">
            <label for="nombre_completo">Nombre Completo <span class="required">*</span></label>
            <input 
                type="text" 
                id="nombre_completo" 
                name="nombre_completo" 
                placeholder="Ej: Juan Pérez"
                value="<?php echo isset($_POST['nombre_completo']) ? htmlspecialchars($_POST['nombre_completo']) : ''; ?>"
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
                value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>"
            >
        </div>
        
        <div class="form-group">
            <label for="nombre_usuario">Usuario <span class="required">*</span></label>
            <input 
                type="text" 
                id="nombre_usuario" 
                name="nombre_usuario" 
                placeholder="Elige un nombre de usuario"
                value="<?php echo isset($_POST['nombre_usuario']) ? htmlspecialchars($_POST['nombre_usuario']) : ''; ?>"
                required
            >
        </div>
        
        <div class="form-group">
            <label for="contrasena">Contraseña <span class="required">*</span></label>
            <input 
                type="password" 
                id="contrasena" 
                name="contrasena" 
                placeholder="Mínimo 6 caracteres"
                required
            >
        </div>
        
        <div class="form-group">
            <label for="confirmar_contrasena">Confirmar Contraseña <span class="required">*</span></label>
            <input 
                type="password" 
                id="confirmar_contrasena" 
                name="confirmar_contrasena" 
                placeholder="Repite tu contraseña"
                required
            >
        </div>
        
        <button type="submit" name="registrar" class="btn-register">
            Crear Cuenta
        </button>
    </form>
    
    <div class="login-link">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
    </div>
</div>

<?php include 'inc/ui.php'; ?>
</body>
</html>