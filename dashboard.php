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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #1c1e21;
        }
        
        /* Header */
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

        /* Bento Grid Layout - SIMETRÍA PERFECTA 6 COLUMNAS */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 20px;
            grid-auto-flow: dense;
        }

        .bento-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .bento-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            border-color: rgba(102, 126, 234, 0.3);
        }

        /* Welcome Card (Spans across all 6) */
        .card-welcome {
            grid-column: span 6;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            justify-content: center;
            border: none;
            padding: 24px 30px;
        }
        
        .card-welcome h2 {
            font-size: 24px;
            margin-bottom: 6px;
            color: white;
        }
        
        .card-welcome p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
        }

        /* Stat Cards */
        .card-stat {
            grid-column: span 1;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
            text-align: center;
        }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: #1c1e21;
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .stat-label {
            color: #666;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Menu Cards */
        .card-menu {
            grid-column: span 3;
            flex-direction: row;
            align-items: center;
            gap: 16px;
            min-height: 90px;
            padding: 16px 20px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        
        .card-menu .menu-icon {
            font-size: 28px;
            margin-bottom: 0;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            background: #f0f2f5;
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            border-radius: 12px;
            transition: background 0.3s, color 0.3s;
        }
        
        .card-menu > div:not(.menu-icon) {
            flex: 1;
        }

        .card-menu h3 {
            font-size: 17px;
            margin-bottom: 4px;
            color: #1c1e21;
            font-weight: 700;
        }
        
        .card-menu p {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Specific Menu Card Colors slightly tinted on hover */
        .card-citas:hover .menu-icon { background: #e3f2fd; color: #1565c0; }
        .card-clientes:hover .menu-icon { background: #f3e5f5; color: #6a1b9a; }
        .card-servicios:hover .menu-icon { background: #fff3e0; color: #e65100; }
        .card-productos:hover .menu-icon { background: #e8f5e9; color: #2e7d32; }
        .card-ventas:hover .menu-icon { background: #ffebee; color: #c62828; }
        .card-usuarios:hover .menu-icon { background: #e0f7fa; color: #00838f; }
        .card-reportes:hover .menu-icon { background: #fce4ec; color: #ad1457; }
        .card-perfil:hover .menu-icon { background: #eceff1; color: #455a64; }

        @media (max-width: 1024px) {
            .bento-grid { grid-template-columns: repeat(4, 1fr); }
            .card-welcome { grid-column: span 4; }
            .card-stat { grid-column: span 2; }
            .card-menu { grid-column: span 2; }
        }

        @media (max-width: 768px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
            .card-welcome { grid-column: span 2; }
            .card-stat { grid-column: span 1; }
            .card-menu { grid-column: span 2; }
        }
        
        @media (max-width: 480px) {
            .bento-grid { grid-template-columns: 1fr; }
            .card-welcome, .card-stat, .card-menu { grid-column: span 1; }
        }

    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <!-- Main Content wrapper -->
    <div class="main-content">
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

<div class="bento-grid">
    <!-- Bienvenida -->
    <div class="bento-card card-welcome">
        <h2>¡Bienvenido/a, <?php echo htmlspecialchars(getNombreUsuario()); ?>!</h2>
        <p>Selecciona una opción del menú para comenzar tu día de manera eficiente.</p>
    </div>

    <?php
    // Alerta de Stock Bajo
    $stock_bajo = [];
    if (esAdmin() || esBarbero()) {
        try {
            // Utilizamos la nueva vista de stock_bajo
            $rsb = $conn->query("SELECT nombre, stock FROM v_stock_bajo LIMIT 5");
            if ($rsb) {
                while($s = $rsb->fetch_assoc()) {
                    $stock_bajo[] = $s;
                }
            }
        } catch(Exception $e){}
    }
    ?>
    <?php if(!empty($stock_bajo)): ?>
    <div class="bento-card card-welcome" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
        <h2>⚠️ Alerta de Inventario</h2>
        <p>Los siguientes productos tienen muy poco stock y necesitan reposición:</p>
        <ul style="margin-top:10px; margin-left: 20px; font-weight:600; color: #fff;">
            <?php foreach($stock_bajo as $psb): ?>
                <li><?php echo htmlspecialchars($psb['nombre']); ?> (Quedan: <?php echo $psb['stock']; ?>)</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Estadísticas (solo para admin y barbero) -->
    <?php if (esAdmin() || esBarbero()): ?>
        <div class="bento-card card-stat">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_usuarios; ?></div>
                <div class="stat-label">Usuarios</div>
            </div>
        </div>
        
        <div class="bento-card card-stat">
            <div class="stat-icon">🧑‍🦱</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_clientes; ?></div>
                <div class="stat-label">Clientes</div>
            </div>
        </div>
        
        <div class="bento-card card-stat">
            <div class="stat-icon">✂️</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_servicios; ?></div>
                <div class="stat-label">Servicios</div>
            </div>
        </div>
        
        <div class="bento-card card-stat">
            <div class="stat-icon">🛍️</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_productos; ?></div>
                <div class="stat-label">Productos</div>
            </div>
        </div>
        
        <div class="bento-card card-stat">
            <div class="stat-icon">📅</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $citas_pendientes; ?></div>
                <div class="stat-label">Citas Pdtes.</div>
            </div>
        </div>

        <div class="bento-card card-stat">
            <div class="stat-icon">🛒</div>
            <div class="stat-info">
                <div class="stat-number"><?php
                    $tv = $conn->query("SELECT COUNT(*) t FROM venta WHERE MONTH(fecha_venta)=MONTH(NOW()) AND YEAR(fecha_venta)=YEAR(NOW())");
                    echo $tv ? $tv->fetch_assoc()['t'] : 0;
                ?></div>
                <div class="stat-label">Ventas Mes</div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Menú de Opciones -->
    
    <!-- Opción para TODOS -->
    <a href="citas.php" class="bento-card card-menu card-citas">
        <div class="menu-icon">📅</div>
        <div>
            <h3>Citas</h3>
            <p>Ver, agendar y gestionar el calendario de clientes de la barbería.</p>
        </div>
    </a>
    
    <!-- Solo Admin y Barbero -->
    <?php if (esAdmin() || esBarbero()): ?>
    <a href="clientes.php" class="bento-card card-menu card-clientes">
        <div class="menu-icon">🧑‍🦱</div>
        <div>
            <h3>Clientes</h3>
            <p>Gestionar y mantener el registro de clientes habituales y nuevos.</p>
        </div>
    </a>
    
    <a href="servicios.php" class="bento-card card-menu card-servicios">
        <div class="menu-icon">✂️</div>
        <div>
            <h3>Servicios</h3>
            <p>Administrar el catálogo de cortes, arreglos y sus respectivos precios.</p>
        </div>
    </a>
    
    <a href="productos.php" class="bento-card card-menu card-productos">
        <div class="menu-icon">🛍️</div>
        <div>
            <h3>Productos</h3>
            <p>Control de inventario, stock y registro de productos en tienda.</p>
        </div>
    </a>
    <a href="ventas.php" class="bento-card card-menu card-ventas">
        <div class="menu-icon">🛒</div>
        <div>
            <h3>Ventas</h3>
            <p>Registrar ventas rápidas directas y llevar el control de caja.</p>
        </div>
    </a>
    <?php endif; ?>
    
    <!-- Solo Admin -->
    <?php if (esAdmin()): ?>
    <a href="usuarios.php" class="bento-card card-menu card-usuarios">
        <div class="menu-icon">👤</div>
        <div>
            <h3>Usuarios</h3>
            <p>Gestión de cuentas, barberos y administradores del sistema.</p>
        </div>
    </a>
    
    <a href="reportes.php" class="bento-card card-menu card-reportes">
        <div class="menu-icon">📊</div>
        <div>
            <h3>Reportes</h3>
            <p>Monitoreo inteligente, analíticas de negocio y rendimiento mensual.</p>
        </div>
    </a>
    <?php endif; ?>
    
    <!-- Opción para TODOS -->
    <a href="perfil.php" class="bento-card card-menu card-perfil">
        <div class="menu-icon">⚙️</div>
        <div>
            <h3>Mi Perfil</h3>
            <p>Configuración personal de cuenta, cambio de contraseñas y datos.</p>
        </div>
    </a>
</div>

    </div> <!-- End main-content -->
</div> <!-- End dashboard-layout -->

</body>
</html>