<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");
require_once("inc/pagination_helper.php");
$conn->query("SET time_zone = '-06:00'"); // America/Mexico_City (CST)

// Verificar que sea administrador o barbero
if (!esAdmin() && !esBarbero()) {
    die("<h1>Acceso Denegado</h1><p>No tienes permisos para acceder a esta sección.</p><a href='dashboard.php'>Volver al inicio</a>");
}

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// CREAR/ACTUALIZAR CLIENTE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    try {
        $id = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
        $nombre = trim($_POST['nombre']);
        $telefono = trim($_POST['telefono']);
        $id_usuario = !empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : NULL;
        
        // Validación del teléfono
        if (!empty($telefono)) {
            if (!preg_match('/^[0-9]{10}$/', $telefono)) {
                throw new Exception("El teléfono debe contener exactamente 10 dígitos numéricos.");
            }
        }
        
        // Validación para evitar duplicidad de Nombre + Teléfono
        $stmt_dup = $conn->prepare("SELECT id_cliente FROM cliente WHERE nombre = ? AND telefono = ? AND id_cliente != ?");
        $stmt_dup->bind_param("ssi", $nombre, $telefono, $id);
        $stmt_dup->execute();
        $res_dup = $stmt_dup->get_result();
        if ($res_dup->num_rows > 0) {
            $stmt_dup->close();
            throw new Exception("Ya existe un cliente registrado con el nombre '$nombre' y el teléfono '$telefono'.");
        }
        $stmt_dup->close();
        
        if ($id > 0) {
            // ACTUALIZAR
            $stmt = $conn->prepare("UPDATE cliente SET nombre = ?, telefono = ?, id_usuario = ? WHERE id_cliente = ?");
            $stmt->bind_param("ssii", $nombre, $telefono, $id_usuario, $id);
            $stmt->execute();
            $mensaje = "Cliente actualizado correctamente";
        } else {
            // CREAR
            $stmt = $conn->prepare("INSERT INTO cliente (nombre, telefono, id_usuario) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $nombre, $telefono, $id_usuario);
            $stmt->execute();
            $mensaje = "Cliente registrado correctamente";
        }
        
        $tipo_mensaje = "success";
        $stmt->close();
        
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ELIMINAR CLIENTE
if (isset($_GET['eliminar'])) {
    if (esAdmin()) {
        try {
            $id = intval($_GET['eliminar']);
            $stmt = $conn->prepare("DELETE FROM cliente WHERE id_cliente = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Cliente eliminado correctamente";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "Error al eliminar cliente: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "No tienes permisos para eliminar clientes. Solo los administradores pueden realizar esta acción.";
        $tipo_mensaje = "warning";
    }
}

// OBTENER CLIENTE PARA EDITAR
$cliente_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre_usuario 
        FROM cliente c 
        LEFT JOIN usuario u ON c.id_usuario = u.id_usuario 
        WHERE c.id_cliente = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cliente_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// CONSULTAR CLIENTES CON PAGINACIÓN Y BÚSQUEDA
try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $where_sql = "";
    if ($search !== '') {
        $where_sql = " WHERE c.nombre LIKE '%" . $conn->real_escape_string($search) . "%' OR c.telefono LIKE '%" . $conn->real_escape_string($search) . "%'";
    }

    // Obtener los IDs de los últimos 3 clientes para la lógica de "NUEVO"
    $res_top = $conn->query("SELECT id_cliente FROM cliente ORDER BY id_cliente DESC LIMIT 3");
    $top_ids = [];
    while($r = $res_top->fetch_assoc()) $top_ids[] = $r['id_cliente'];
    $top_ids_str = !empty($top_ids) ? implode(',', $top_ids) : '0';

    $pagData = getPaginationData($conn, "SELECT COUNT(*) as total FROM cliente c $where_sql", 12);
    $limit = 12;
    $offset = $pagData['offset'];
    
    // Lógica de orden:
    // 1. Los 3 últimos con menos de 2 minutos van arriba
    // 2. Pasados los 2 minutos o si no son de los 3 últimos, se ordenan alfabéticamente
    $time_limit = date('Y-m-d H:i:s', strtotime('-2 minutes'));
    $clientes = $conn->query("
        SELECT c.*, u.nombre_usuario,
        (c.creado_en > (NOW() - INTERVAL 2 MINUTE) AND c.id_cliente IN ($top_ids_str)) as es_nuevo 
        FROM cliente c
        LEFT JOIN usuario u ON c.id_usuario = u.id_usuario
        $where_sql
        ORDER BY es_nuevo DESC, 
                 IF(c.creado_en > (NOW() - INTERVAL 2 MINUTE) AND c.id_cliente IN ($top_ids_str), c.id_cliente, 0) DESC, 
                 c.nombre ASC
        LIMIT $limit OFFSET $offset
    ");
} catch (mysqli_sql_exception $e) {
    $clientes = false;
}

// OBTENER USUARIOS DISPONIBLES (solo clientes)
try {
    $usuarios_disponibles = $conn->query("
        SELECT u.id_usuario, u.nombre_usuario 
        FROM usuario u
        WHERE u.id_rol = 3 
        AND u.id_usuario NOT IN (SELECT id_usuario FROM cliente WHERE id_usuario IS NOT NULL)
        ORDER BY u.nombre_usuario
    ");
} catch (mysqli_sql_exception $e) {
    $usuarios_disponibles = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Barbería</title>
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
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Alertas manejadas por inc/ui.php */
        
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
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
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.with-user {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.no-user {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-links {
            display: flex;
            gap: 10px;
        }
        
        .action-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        .action-links a.delete {
            color: #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .required {
            color: #e74c3c;
        }
        <?php echo getPaginationStyles(); ?>
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>🧑‍🦱 Gestión de Clientes</h1>
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

    
    <!-- Formulario -->
    <div class="form-section">
        <h3><?php echo $cliente_editar ? 'Editar Cliente' : 'Registrar Nuevo Cliente'; ?></h3>
        <form method="POST">
            <?php if ($cliente_editar): ?>
            <input type="hidden" name="id_cliente" value="<?php echo $cliente_editar['id_cliente']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre Completo <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nombre"
                        name="nombre" 
                        placeholder="Ej: Juan Pérez García"
                        value="<?php echo $cliente_editar ? htmlspecialchars($cliente_editar['nombre']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono <span class="required">*</span></label>
                    <input 
                        type="tel" 
                        id="telefono"
                        name="telefono" 
                        placeholder="Ej: 5512345678"
                        pattern="[0-9]{10}"
                        maxlength="10"
                        title="El teléfono debe contener exactamente 10 dígitos"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                        value="<?php echo $cliente_editar ? htmlspecialchars($cliente_editar['telefono']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="id_usuario">Vincular con Usuario (opcional)</label>
                    <select name="id_usuario" id="id_usuario">
                        <option value="">-- Sin usuario --</option>
                        <?php if($usuarios_disponibles && $usuarios_disponibles->num_rows > 0): ?>
                            <?php while($usuario = $usuarios_disponibles->fetch_assoc()): ?>
                                <option value="<?php echo $usuario['id_usuario']; ?>"
                                    <?php echo ($cliente_editar && $cliente_editar['id_usuario'] == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        <?php if($cliente_editar && $cliente_editar['id_usuario']): ?>
                            <option value="<?php echo $cliente_editar['id_usuario']; ?>" selected>
                                <?php echo htmlspecialchars($cliente_editar['nombre_usuario']); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn btn-primary">
                    <?php echo $cliente_editar ? '💾 Actualizar Cliente' : '💾 Guardar Cliente'; ?>
                </button>
                <?php if ($cliente_editar): ?>
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Clientes -->
    <div class="table-section" id="tabla-clientes">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea;">
            <h3 style="border:none; margin:0; padding:0;">Lista de Clientes</h3>
            
            <form id="searchForm" style="display: flex; gap: 8px;">
                <input 
                    type="text" 
                    id="searchInput"
                    name="q" 
                    placeholder="Buscar cliente..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    autocomplete="off"
                    style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; width: 200px;"
                >
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 13px;">🔍</button>
            </form>
        </div>
        
        <?php if($clientes && $clientes->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Usuario Vinculado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $clientes->fetch_assoc()): ?>
                <tr <?php echo ($row['es_nuevo']) ? 'style="background: #f0f4ff; border-left: 4px solid #667eea;"' : ''; ?>>
                    <td>
                        <?php echo htmlspecialchars($row['nombre']); ?>
                        <?php if ($row['es_nuevo']): ?>
                            <span style="font-size: 10px; background: #667eea; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: 700;">NUEVO</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['telefono'] ?? 'Sin teléfono'); ?></td>
                    <td>
                        <?php if($row['id_usuario']): ?>
                            <span class="badge with-user">
                                👤 <?php echo htmlspecialchars($row['nombre_usuario']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge no-user">Sin usuario</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-links">
                            <a href="?editar=<?php echo $row['id_cliente']; ?>">✏️ Editar</a>
                            <a href="?eliminar=<?php echo $row['id_cliente']; ?>" 
                               class="delete"
                               onclick="event.preventDefault(); confirmacion('¿Estás seguro de eliminar este cliente?\nTambién se eliminarán todas sus citas.', '🗑️ Eliminar', () => window.location.href='?eliminar=<?php echo $row['id_cliente']; ?>')">
                               🗑️ Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php echo renderPagination($pagData['current_page'], $pagData['total_pages'], "q=" . urlencode($search), 'p', '#tabla-clientes'); ?>
        
        <?php else: ?>
        <div class="empty-state">
            <p>No hay clientes registrados</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</div> <!-- .container -->
</div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

<?php include 'inc/ui.php'; ?>
<script>
    // Búsqueda automática con debounce (500ms)
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');

    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            // Filtrado inmediato
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });

            // Búsqueda global en el servidor
            searchTimeout = setTimeout(() => {
                const currentQ = "<?php echo addslashes($search); ?>";
                if (this.value.trim() !== currentQ) {
                    searchForm.submit();
                }
            }, 600);
        });

        // Mantener el foco
        window.addEventListener('load', () => {
            if (searchInput.value !== "") {
                searchInput.focus();
                const val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }
        });
    }
</script>
</body>
</html>