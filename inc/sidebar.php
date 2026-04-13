<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Función para determinar si un item es el activo
function is_active($page) {
    global $current_page;
    return $current_page == $page ? 'active' : '';
}

// Determinar si un submenú debe estar abierto
$inventario_open = in_array($current_page, ['productos.php', 'ventas.php']);
$configuracion_open = in_array($current_page, ['usuarios.php', 'perfil.php']);
?>

<style>
    /* ----- SIDEBAR LAYOUT STYLES ----- */
    .dashboard-layout { display: flex; height: 100vh; overflow: hidden; }
    .sidebar { width: 260px; background: #15171e; color: #a0a5b1; display: flex; flex-direction: column; flex-shrink: 0; z-index: 200; font-family: 'Inter', sans-serif; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .sidebar.collapsed { width: 80px; }
    
    .sidebar-header { padding: 24px 20px; display: flex; align-items: center; justify-content: space-between; color: white; font-size: 20px; font-weight: 700; white-space: nowrap; overflow: hidden; flex-direction: row; transition: padding 0.3s, gap 0.3s; }
    .sidebar.collapsed .sidebar-header { flex-direction: column; padding: 24px 0; justify-content: center; gap: 16px; }
    
    .sidebar-header .brand { display: flex; align-items: center; gap: 12px; transition: opacity 0.3s; }
    .sidebar.collapsed .sidebar-header .brand span { display: none; }
    
    .sidebar-header .logo-icon { background: white; color: #15171e; min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 16px; }
    
    .toggle-btn { background: none; border: none; color: #a0a5b1; cursor: pointer; font-size: 20px; padding: 4px; border-radius: 6px; display: flex; transition: all 0.3s; }
    .sidebar.collapsed .toggle-btn { transform: rotate(180deg); }
    .toggle-btn:hover { background: #2d303e; color: white; }
    
    .sidebar-search { padding: 0 20px 20px; transition: opacity 0.3s; }
    .sidebar.collapsed .sidebar-search { opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; overflow: hidden; }
    .sidebar-search input { width: 100%; background: #1c1e26; border: 1px solid #2d303e; border-radius: 8px; color: white; padding: 10px 14px 10px 36px; font-size: 14px; outline: none; transition: border 0.3s; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a0a5b1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: 10px center; background-size: 16px; }
    .sidebar-search input:focus { border-color: #667eea; }
    
    .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0 12px; }
    .sidebar-nav::-webkit-scrollbar { width: 0; }
    
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; margin-bottom: 4px; border-radius: 8px; color: #a0a5b1; text-decoration: none; font-size: 15px; font-weight: 500; transition: all 0.2s; cursor: pointer; user-select: none; white-space: nowrap; }
    .nav-item:hover, .nav-item.active { background: #2d303e; color: #ffffff; }
    .nav-item.active { background: rgba(102, 126, 234, 0.15); color: #667eea; border-right: 3px solid #667eea; border-radius: 8px 0 0 8px; }
    
    .nav-item .icon { font-size: 18px; min-width: 24px; text-align: center; }
    .nav-item .nav-text { flex: 1; transition: opacity 0.2s; opacity: 1; }
    .sidebar.collapsed .nav-text { opacity: 0; width: 0; display: none; }
    .nav-item .chevron { font-size: 10px; transition: transform 0.3s; }
    .sidebar.collapsed .chevron { display: none; }
    .nav-item.open .chevron { transform: rotate(180deg); }
    
    .submenu { display: none; padding-left: 52px; position: relative; margin-bottom: 8px; white-space: nowrap; }
    .sidebar.collapsed .submenu { display: none !important; }
    .submenu::before { content: ''; position: absolute; left: 36px; top: 4px; bottom: 4px; width: 2px; background: #2d303e; border-radius: 2px; }
    .submenu.open { display: block; }
    .submenu-item { display: block; padding: 8px 16px; color: #8b909c; text-decoration: none; font-size: 14px; transition: color 0.2s; position: relative; }
    .submenu-item.active { color: #667eea; font-weight: 600; }
    .submenu-item.active::after { content: ''; position: absolute; left: -16px; top: 12px; bottom: 12px; width: 4px; background: #667eea; border-radius: 2px; }
    .submenu-item:hover { color: #ffffff; }
    
    .main-content { flex: 1; min-width: 0; overflow-y: auto; background: #f0f2f5; transition: margin 0.3s; }
    
    @media (max-width: 768px) {
        .dashboard-layout { flex-direction: column; height: auto; display: block; }
        .sidebar { width: 100%; height: auto; position: static; }
        .sidebar.collapsed { width: 100%; }
        .sidebar-header { justify-content: space-between !important; flex-direction: row !important; padding: 24px 20px !important; }
        .sidebar-nav { overflow-y: visible; padding-bottom: 20px;}
        .main-content { overflow-y: visible; }
    }
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <div class="logo-icon">✂️</div>
            <span>Barbería</span>
        </div>
        <button class="toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">☰</button>
    </div>
    <div class="sidebar-search">
        <input type="text" placeholder="Buscar...">
    </div>
    <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo is_active('dashboard.php'); ?>">
            <span class="icon">🏠</span>
            <span class="nav-text">Inicio</span>
        </a>
        <a href="citas.php" class="nav-item <?php echo is_active('citas.php'); ?>">
            <span class="icon">📅</span>
            <span class="nav-text">Citas</span>
        </a>
        
        <?php if (esAdmin() || esBarbero()): ?>
        <a href="clientes.php" class="nav-item <?php echo is_active('clientes.php'); ?>">
            <span class="icon">🧑‍🦱</span>
            <span class="nav-text">Clientes</span>
        </a>
        <a href="servicios.php" class="nav-item <?php echo is_active('servicios.php'); ?>">
            <span class="icon">✂️</span>
            <span class="nav-text">Servicios</span>
        </a>
        
        <div class="nav-item <?php echo $inventario_open ? 'open' : ''; ?>" onclick="if(!document.getElementById('sidebar').classList.contains('collapsed')) { this.nextElementSibling.classList.toggle('open'); this.classList.toggle('open'); }">
            <span class="icon">🛍️</span>
            <span class="nav-text">Inventario</span>
            <span class="chevron">▼</span>
        </div>
        <div class="submenu <?php echo $inventario_open ? 'open' : ''; ?>">
            <a href="productos.php" class="submenu-item <?php echo is_active('productos.php'); ?>">Productos</a>
            <a href="ventas.php" class="submenu-item <?php echo is_active('ventas.php'); ?>">Ventas</a>
        </div>
        <?php endif; ?>

        <?php if (esAdmin()): ?>
        <a href="reportes.php" class="nav-item <?php echo is_active('reportes.php'); ?>">
            <span class="icon">📊</span>
            <span class="nav-text">Reportes</span>
        </a>
        <?php endif; ?>

        <div class="nav-item <?php echo $configuracion_open ? 'open' : ''; ?>" onclick="if(!document.getElementById('sidebar').classList.contains('collapsed')) { this.nextElementSibling.classList.toggle('open'); this.classList.toggle('open'); }">
            <span class="icon">⚙️</span>
            <span class="nav-text">Configuración</span>
            <span class="chevron">▼</span>
        </div>
        <div class="submenu <?php echo $configuracion_open ? 'open' : ''; ?>">
            <?php if (esAdmin()): ?>
            <a href="usuarios.php" class="submenu-item <?php echo is_active('usuarios.php'); ?>">Usuarios</a>
            <?php endif; ?>
            <a href="perfil.php" class="submenu-item <?php echo is_active('perfil.php'); ?>">Mi Perfil</a>
        </div>
    </div>
</aside>
