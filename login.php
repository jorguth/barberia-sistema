<?php
// Iniciar sesión
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

// Incluir conexión
require_once("conexion.php");

// Variables para mensajes
$error = "";

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $usuario = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];
    
    if (!empty($usuario) && !empty($contrasena)) {
        try {
            // Buscar usuario en la base de datos
            $stmt = $conn->prepare("
                SELECT u.id_usuario, u.nombre_usuario, u.id_rol, r.nombre_rol
                FROM usuario u
                LEFT JOIN rol r ON u.id_rol = r.id_rol
                WHERE u.nombre_usuario = ? AND u.contrasena = ?
            ");
            $stmt->bind_param("ss", $usuario, $contrasena);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows == 1) {
                // Usuario encontrado - Crear sesión
                $datos = $resultado->fetch_assoc();
                
                $_SESSION['id_usuario'] = $datos['id_usuario'];
                $_SESSION['nombre_usuario'] = $datos['nombre_usuario'];
                $_SESSION['id_rol'] = $datos['id_rol'];
                $_SESSION['nombre_rol'] = $datos['nombre_rol'];
                $_SESSION['login_time'] = time();
                
                // Redirigir al dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
            
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Barbería</title>
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
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Alertas manejadas por inc/ui.php */
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 25px 0;
            color: #999;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">
        <h1>✂️ Barbería</h1>
        <p>Sistema de Gestión</p>
    </div>
    

    
    <form method="POST" action="">
        <div class="form-group">
            <label for="nombre_usuario">Usuario</label>
            <input 
                type="text" 
                id="nombre_usuario" 
                name="nombre_usuario" 
                placeholder="Ingresa tu usuario"
                required
                autofocus
            >
        </div>
        
        <div class="form-group">
            <label for="contrasena">Contraseña</label>
            <input 
                type="password" 
                id="contrasena" 
                name="contrasena" 
                placeholder="Ingresa tu contraseña"
                required
            >
        </div>
        
        <button type="submit" name="login" class="btn-login">
            Iniciar Sesión
        </button>
    </form>
    
    <div class="divider">○ ○ ○</div>
    
    <div class="register-link">
        ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
    </div>
</div>

<?php include 'inc/ui.php'; ?>
</body>
</html>