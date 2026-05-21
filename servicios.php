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

// CREAR/ACTUALIZAR SERVICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    try {
        $id = isset($_POST['id_servicio']) ? intval($_POST['id_servicio']) : 0;
        $nombre = trim($_POST['nombre']);
        $duracion = intval($_POST['duracion_minutos']);
        $precio = floatval($_POST['precio']);
        
        // VALIDACIÓN: Duración máxima 2 horas (120 min)
        if ($duracion > 120) {
            $mensaje = "Error: La duración máxima permitida es de 120 minutos (2 horas).";
            $tipo_mensaje = "error";
        } else {
            // VERIFICAR DUPLICADOS
            $stmt_check = $conn->prepare("SELECT id_servicio FROM servicio WHERE nombre = ? AND id_servicio != ? AND activo = 1");
            $stmt_check->bind_param("si", $nombre, $id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $mensaje = "Error: Ya existe un servicio con el nombre '$nombre'.";
                $tipo_mensaje = "error";
            } else if ($id > 0) {
                // ACTUALIZAR
                $stmt = $conn->prepare("UPDATE servicio SET nombre = ?, duracion_minutos = ?, precio = ? WHERE id_servicio = ?");
                $stmt->bind_param("sidi", $nombre, $duracion, $precio, $id);
                $stmt->execute();
                $mensaje = "Servicio actualizado correctamente";
                $tipo_mensaje = "success";
                $stmt->close();
            } else {
                // CREAR
                $stmt = $conn->prepare("INSERT INTO servicio (nombre, duracion_minutos, precio) VALUES (?, ?, ?)");
                $stmt->bind_param("sid", $nombre, $duracion, $precio);
                $stmt->execute();
                $mensaje = "Servicio registrado correctamente";
                $tipo_mensaje = "success";
                $stmt->close();
            }
            $stmt_check->close();
        }
        
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ELIMINAR SERVICIO
if (isset($_GET['eliminar'])) {
    if (esAdmin()) {
        try {
            $id = intval($_GET['eliminar']);
            $stmt = $conn->prepare("DELETE FROM servicio WHERE id_servicio = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Servicio eliminado permanentemente (no tenía citas asociadas)";
            $tipo_mensaje = "success";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                // Borrado Lógico (Soft Delete)
                $stmt_soft = $conn->prepare("UPDATE servicio SET activo = 0 WHERE id_servicio = ?");
                $stmt_soft->bind_param("i", $id);
                $stmt_soft->execute();
                $stmt_soft->close();
                $mensaje = "Servicio archivado (oculto). No se borró permanentemente porque ya tiene citas registradas en el historial.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar servicio: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } else {
        $mensaje = "No tienes permisos para eliminar servicios. Solo los administradores pueden realizar esta acción.";
        $tipo_mensaje = "warning";
    }
}

// OBTENER SERVICIO PARA EDITAR
$servicio_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $conn->prepare("SELECT * FROM servicio WHERE id_servicio = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $servicio_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// CONSULTAR SERVICIOS CON PAGINACIÓN Y BÚSQUEDA
try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $where_sql = " WHERE activo = 1";
    if ($search !== '') {
        $where_sql .= " AND nombre LIKE '%" . $conn->real_escape_string($search) . "%'";
    }

    // Obtener los IDs de los últimos 3 servicios registrados para la lógica de "NUEVO"
    $res_top = $conn->query("SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio DESC LIMIT 3");
    $top_ids = [];
    while($r = $res_top->fetch_assoc()) $top_ids[] = $r['id_servicio'];
    $top_ids_str = !empty($top_ids) ? implode(',', $top_ids) : '0';

    $pagData = getPaginationData($conn, "SELECT COUNT(*) as total FROM servicio $where_sql", 12);
    $limit = 12;
    $offset = $pagData['offset'];
    
    // Lógica de orden: 
    // 1. Los que cumplen ser de los últimos 3 Y tener menos de 2 min van arriba
    // 2. Entre los nuevos, el más reciente primero
    // 3. El resto alfabéticamente
    $servicios = $conn->query("SELECT *, 
                               (creado_en > (NOW() - INTERVAL 2 MINUTE) AND id_servicio IN ($top_ids_str)) as es_nuevo 
                               FROM servicio 
                               $where_sql 
                               ORDER BY es_nuevo DESC, 
                                        IF(creado_en > (NOW() - INTERVAL 2 MINUTE) AND id_servicio IN ($top_ids_str), id_servicio, 0) DESC, 
                                        nombre ASC 
                               LIMIT $limit OFFSET $offset");
} catch (mysqli_sql_exception $e) {
    $servicios = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Servicios - Barbería</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .form-group input {
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
        
        .price {
            color: #28a745;
            font-weight: 600;
            font-size: 16px;
        }
        
        .duration {
            color: #667eea;
            font-weight: 500;
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
        
        .input-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
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
                <h1>✂️ Gestión de Servicios</h1>
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
        <h3><?php echo $servicio_editar ? 'Editar Servicio' : 'Registrar Nuevo Servicio'; ?></h3>
        <form method="POST">
            <?php if ($servicio_editar): ?>
            <input type="hidden" name="id_servicio" value="<?php echo $servicio_editar['id_servicio']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre del Servicio <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nombre"
                        name="nombre" 
                        placeholder="Ej: Corte de Cabello"
                        value="<?php echo $servicio_editar ? htmlspecialchars($servicio_editar['nombre']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="duracion_minutos">Duración (minutos) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="duracion_minutos"
                        name="duracion_minutos" 
                        placeholder="Ej: 30"
                        min="1"
                        max="120"
                        value="<?php echo $servicio_editar ? $servicio_editar['duracion_minutos'] : ''; ?>"
                        required
                    >
                    <span class="input-hint">Tiempo aproximado (Máx 120 min)</span>
                </div>
                
                <div class="form-group">
                    <label for="precio">Precio ($) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        id="precio"
                        name="precio" 
                        placeholder="Ej: 150.00"
                        step="0.01"
                        min="0"
                        value="<?php echo $servicio_editar ? $servicio_editar['precio'] : ''; ?>"
                        required
                    >
                    <span class="input-hint">Precio en pesos mexicanos</span>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn btn-primary">
                    <?php echo $servicio_editar ? '💾 Actualizar Servicio' : '💾 Guardar Servicio'; ?>
                </button>
                <?php if ($servicio_editar): ?>
                <a href="servicios.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de Servicios -->
    <div class="table-section" id="catalogo-servicios">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea;">
            <h3 style="border:none; margin:0; padding:0;">Catálogo de Servicios</h3>
            
            <form id="searchForm" action="#catalogo-servicios" style="display: flex; gap: 8px;">
                <input 
                    type="text" 
                    id="searchInput"
                    name="q" 
                    placeholder="Buscar servicio..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    autocomplete="off"
                    style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; width: 200px;"
                >
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 13px;">🔍</button>
            </form>
        </div>
        
        <?php if($servicios && $servicios->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>Duración</th>
                    <th>Precio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $servicios->fetch_assoc()): ?>
                <tr <?php echo ($row['es_nuevo']) ? 'style="background: #f0f4ff; border-left: 4px solid #667eea;"' : ''; ?>>
                    <td>
                        <?php echo htmlspecialchars($row['nombre']); ?>
                        <?php if ($row['es_nuevo']): ?>
                            <span style="font-size: 10px; background: #667eea; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: 700;">NUEVO</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="duration">⏱️ <?php echo $row['duracion_minutos']; ?> min</span>
                    </td>
                    <td>
                        <span class="price">$<?php echo number_format($row['precio'], 2); ?></span>
                    </td>
                    <td>
                        <div class="action-links">
                            <a href="?editar=<?php echo $row['id_servicio']; ?>">✏️ Editar</a>
                            <a href="?eliminar=<?php echo $row['id_servicio']; ?>" 
                               class="delete"
                               onclick="event.preventDefault(); confirmacion('¿Estás seguro de eliminar este servicio?', '🗑️ Eliminar', () => window.location.href='?eliminar=<?php echo $row['id_servicio']; ?>')">
                               🗑️ Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php echo renderPagination($pagData['current_page'], $pagData['total_pages'], "q=" . urlencode($search), 'p', '#catalogo-servicios'); ?>
        
        <?php else: ?>
        <div class="empty-state">
            <p>No hay servicios registrados</p>
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

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        // Efecto visual inmediato para el usuario
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
        }, 400);
    });

    // Mantener el foco al final del texto después de recargar
    window.addEventListener('load', () => {
        if (searchInput.value !== "") {
            searchInput.focus();
            const val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
        }
    });
</script>
</body>
</html>