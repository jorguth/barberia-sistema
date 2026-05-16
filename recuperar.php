<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

require_once("conexion.php");

$paso = 1;
$mensaje = "";
$tipo_mensaje = "";

$usuario = "";
$id_usuario_recuperar = null;

// PASO 1: Procesar Usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paso_1'])) {
    $usuario = trim($_POST['nombre_usuario']);
    if (!empty($usuario)) {
        $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE nombre_usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows == 1) {
            $row = $res->fetch_assoc();
            $id_usuario_recuperar = $row['id_usuario'];
            
            // Verificar si el usuario tiene preguntas configuradas
            $stmt2 = $conn->prepare("
                SELECT up.id_pregunta, ps.pregunta 
                FROM usuario_pregunta up
                JOIN pregunta_seguridad ps ON up.id_pregunta = ps.id_pregunta
                WHERE up.id_usuario = ?
            ");
            $stmt2->bind_param("i", $id_usuario_recuperar);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            
            if ($res2->num_rows >= 1 && $res2->num_rows <= 4) {
                // Guardar estado en sesión para el paso 2
                $_SESSION['recuperar_id_usuario'] = $id_usuario_recuperar;
                $_SESSION['recuperar_usuario'] = $usuario;
                
                $preguntas = [];
                while ($p = $res2->fetch_assoc()) {
                    $preguntas[] = $p;
                }
                $_SESSION['recuperar_preguntas'] = $preguntas;
                
                $paso = 2;
            } else {
                $mensaje = "Este usuario no ha configurado sus preguntas de seguridad. Contacta al administrador.";
                $tipo_mensaje = "error";
            }
            $stmt2->close();
        } else {
            $mensaje = "Usuario no encontrado.";
            $tipo_mensaje = "error";
        }
        $stmt->close();
    } else {
        $mensaje = "Por favor ingresa un nombre de usuario.";
        $tipo_mensaje = "warning";
    }
}

// PASO 2: Procesar Respuestas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paso_2'])) {
    $paso = 2;
    $id_usuario_recuperar = $_SESSION['recuperar_id_usuario'] ?? null;
    $preguntas = $_SESSION['recuperar_preguntas'] ?? [];
    
    $cantidad = count($preguntas);
    if ($id_usuario_recuperar && $cantidad >= 1 && $cantidad <= 4) {
        $todas_correctas = true;
        
        for ($i = 0; $i < $cantidad; $i++) {
            $id_preg = $preguntas[$i]['id_pregunta'];
            $resp_ingresada = strtolower(trim($_POST["respuesta_$i"] ?? ''));
            
            // Verificar respuesta en BD
            $stmt = $conn->prepare("SELECT respuesta FROM usuario_pregunta WHERE id_usuario = ? AND id_pregunta = ?");
            $stmt->bind_param("ii", $id_usuario_recuperar, $id_preg);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows == 1) {
                $row = $res->fetch_assoc();
                if ($row['respuesta'] !== $resp_ingresada) {
                    $todas_correctas = false;
                }
            } else {
                $todas_correctas = false;
            }
            $stmt->close();
        }
        
        if ($todas_correctas) {
            $paso = 3;
            $_SESSION['recuperar_autorizado'] = true;
        } else {
            $mensaje = "Una o más respuestas son incorrectas.";
            $tipo_mensaje = "error";
        }
    } else {
        // Sesión expirada o inválida
        $paso = 1;
        $mensaje = "Sesión inválida, por favor intenta de nuevo.";
        $tipo_mensaje = "error";
    }
}

// PASO 3: Guardar Nueva Contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paso_3'])) {
    $paso = 3;
    if (isset($_SESSION['recuperar_autorizado']) && $_SESSION['recuperar_autorizado'] === true) {
        $id_usuario_recuperar = $_SESSION['recuperar_id_usuario'];
        $pass1 = $_POST['nueva_contrasena'];
        $pass2 = $_POST['confirmar_contrasena'];
        
        if ($pass1 === $pass2) {
            if (strlen($pass1) >= 6) {
                try {
                    $stmt = $conn->prepare("UPDATE usuario SET contrasena = ? WHERE id_usuario = ?");
                    $stmt->bind_param("si", $pass1, $id_usuario_recuperar);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Limpiar variables de sesión de recuperación
                    unset($_SESSION['recuperar_id_usuario']);
                    unset($_SESSION['recuperar_usuario']);
                    unset($_SESSION['recuperar_preguntas']);
                    unset($_SESSION['recuperar_autorizado']);
                    
                    header("Location: login.php?msg=Contraseña actualizada con éxito. Inicia sesión.");
                    exit();
                } catch (Exception $e) {
                    $mensaje = "Error al actualizar la contraseña.";
                    $tipo_mensaje = "error";
                }
            } else {
                $mensaje = "La contraseña debe tener al menos 6 caracteres.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Las contraseñas no coinciden.";
            $tipo_mensaje = "error";
        }
    } else {
        $paso = 1;
        $mensaje = "Acceso no autorizado.";
        $tipo_mensaje = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Barbería</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #667eea; font-size: 24px; margin-bottom: 5px; }
        .logo p { color: #666; font-size: 14px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        
        .back-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .back-link a { color: #667eea; text-decoration: none; font-weight: 600; }
        .back-link a:hover { text-decoration: underline; }
        
        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: #e0e0e0; }
        .step-dot.active { background: #667eea; }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">
        <h1>🔐 Recuperar Contraseña</h1>
        <p>Restablece tu acceso de forma segura</p>
    </div>
    
    <div class="step-indicator">
        <div class="step-dot <?php echo $paso >= 1 ? 'active' : ''; ?>"></div>
        <div class="step-dot <?php echo $paso >= 2 ? 'active' : ''; ?>"></div>
        <div class="step-dot <?php echo $paso >= 3 ? 'active' : ''; ?>"></div>
    </div>

    <!-- MOSTRAR MENSAJES -->
    <?php if(!empty($mensaje)): ?>
        <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; 
            <?php echo $tipo_mensaje == 'error' ? 'background: #fee2e2; color: #991b1b; border: 1px solid #f87171;' : 
            ($tipo_mensaje == 'success' ? 'background: #d1fae5; color: #065f46; border: 1px solid #34d399;' : 'background: #fef3c7; color: #92400e; border: 1px solid #fbbf24;'); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- PASO 1: Buscar Usuario -->
    <?php if($paso == 1): ?>
        <form method="POST">
            <div class="form-group">
                <label for="nombre_usuario">Nombre de Usuario</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" placeholder="Ingresa tu usuario" required autofocus>
            </div>
            <button type="submit" name="paso_1" class="btn">Continuar</button>
        </form>
    <?php endif; ?>

    <!-- PASO 2: Preguntas de Seguridad -->
    <?php if($paso == 2): ?>
        <form method="POST">
            <p style="margin-bottom: 20px; font-size: 14px; color: #555;">Responde las siguientes preguntas de seguridad para el usuario <strong><?php echo htmlspecialchars($_SESSION['recuperar_usuario']); ?></strong>.</p>
            
            <?php foreach($_SESSION['recuperar_preguntas'] as $index => $preg): ?>
            <div class="form-group">
                <label><?php echo htmlspecialchars($preg['pregunta']); ?></label>
                <input type="text" name="respuesta_<?php echo $index; ?>" placeholder="Tu respuesta" required <?php echo $index==0?'autofocus':''; ?>>
            </div>
            <?php endforeach; ?>
            
            <button type="submit" name="paso_2" class="btn">Verificar Respuestas</button>
        </form>
    <?php endif; ?>

    <!-- PASO 3: Nueva Contraseña -->
    <?php if($paso == 3): ?>
        <form method="POST">
            <p style="margin-bottom: 20px; font-size: 14px; color: #555;">Respuestas correctas. Ahora puedes establecer una nueva contraseña.</p>
            
            <div class="form-group">
                <label for="nueva_contrasena">Nueva Contraseña</label>
                <input type="password" id="nueva_contrasena" name="nueva_contrasena" placeholder="Mínimo 6 caracteres" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="confirmar_contrasena">Confirmar Contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Repite la nueva contraseña" required>
            </div>
            
            <button type="submit" name="paso_3" class="btn">Guardar Nueva Contraseña</button>
        </form>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="login.php">Volver al inicio de sesión</a>
    </div>
</div>

</body>
</html>
