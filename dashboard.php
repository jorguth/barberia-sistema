<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");

// Obtener estadísticas básicas
try {
    $total_usuarios = $conn->query("SELECT COUNT(*) as total FROM usuario")->fetch_assoc()['total'];
    $total_clientes = $conn->query("SELECT COUNT(*) as total FROM cliente")->fetch_assoc()['total'];
    $total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicio")->fetch_assoc()['total'];
    $total_productos = $conn->query("SELECT COUNT(*) as total FROM producto")->fetch_assoc()['total'];
    $citas_pendientes = $conn->query("SELECT COUNT(*) as total FROM cita WHERE estado = 'Pendiente'")->fetch_assoc()['total'];
} catch (Exception $e) {
    $total_usuarios = 0;
    $total_clientes = 0;
    $total_servicios = 0;
    $total_productos = 0;
    $citas_pendientes = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Barbería</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .welcome h2 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .welcome p {
            color: #666;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .menu-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .menu-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .menu-card p {
            color: #666;
            font-size: 14px;
        }
        
        .admin-only {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>✂️ Sistema Barbería</h1>
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
    <div class="welcome">
        <h2>¡Bienvenido/a, <?php echo htmlspecialchars(getNombreUsuario()); ?>!</h2>
        <p>Selecciona una opción del menú para comenzar.</p>
    </div>
    
    <!-- Estadísticas (solo para admin y barbero) -->
    <?php if (esAdmin() || esBarbero()): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-number"><?php echo $total_usuarios; ?></div>
            <div class="stat-label">Usuarios</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🧑‍🦱</div>
            <div class="stat-number"><?php echo $total_clientes; ?></div>
            <div class="stat-label">Clientes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✂️</div>
            <div class="stat-number"><?php echo $total_servicios; ?></div>
            <div class="stat-label">Servicios</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🛍️</div>
            <div class="stat-number"><?php echo $total_productos; ?></div>
            <div class="stat-label">Productos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-number"><?php echo $citas_pendientes; ?></div>
            <div class="stat-label">Citas Pendientes</div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Menú de Opciones -->
    <div class="menu-grid">
        <!-- Opción para TODOS -->
        <a href="citas.php" class="menu-card">
            <div class="menu-icon">📅</div>
            <h3>Citas</h3>
            <p>Ver, agendar y gestionar citas</p>
        </a>
        
        <!-- Solo Admin y Barbero -->
        <?php if (esAdmin() || esBarbero()): ?>
        <a href="clientes.php" class="menu-card">
            <div class="menu-icon">🧑‍🦱</div>
            <h3>Clientes</h3>
            <p>Gestionar información de clientes</p>
        </a>
        
        <a href="servicios.php" class="menu-card">
            <div class="menu-icon">✂️</div>
            <h3>Servicios</h3>
            <p>Administrar servicios y precios</p>
        </a>
        
        <a href="productos.php" class="menu-card">
            <div class="menu-icon">🛍️</div>
            <h3>Productos</h3>
            <p>Inventario y ventas de productos</p>
        </a>
        <?php endif; ?>
        
        <!-- Solo Admin -->
        <?php if (esAdmin()): ?>
        <a href="usuarios.php" class="menu-card">
            <div class="menu-icon">👤</div>
            <h3>Usuarios</h3>
            <p>Gestionar usuarios del sistema</p>
        </a>
        
        <a href="reportes.php" class="menu-card">
            <div class="menu-icon">📊</div>
            <h3>Reportes</h3>
            <p>Estadísticas y reportes del negocio</p>
        </a>
        <?php endif; ?>
        
        <!-- Opción para TODOS -->
        <a href="perfil.php" class="menu-card">
            <div class="menu-icon">⚙️</div>
            <h3>Mi Perfil</h3>
            <p>Configuración de cuenta</p>
        </a>
    </div>
</div>

</body>
</html>