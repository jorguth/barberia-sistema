<?php
// Incluir autenticación
require_once("auth.php");
require_once("conexion.php");
require_once("inc/pagination_helper.php");

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// Auto-cancelar citas pasadas que quedaron pendientes
$conn->query("UPDATE cita SET estado = 'Cancelada' WHERE estado = 'Pendiente' AND fecha < CURDATE()");

// Semana actual (navegación)
$semana_offset = isset($_GET['semana']) ? intval($_GET['semana']) : 0;
$hoy = new DateTime();
$hoy->modify("{$semana_offset} week");

// Inicio de semana (lunes)
$inicio_semana = clone $hoy;
$dia_semana = $inicio_semana->format('N'); // 1=Lunes, 7=Domingo
$inicio_semana->modify('-' . ($dia_semana - 1) . ' days');
$fin_semana = clone $inicio_semana;
$fin_semana->modify('+6 days');

$inicio_str = $inicio_semana->format('Y-m-d');
$fin_str    = $fin_semana->format('Y-m-d');

// -------------------------------------------------------
// ELIMINAR CITA (solo admin)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['eliminar'])) {
    if (esAdmin()) {
        try {
            $id = intval($_GET['eliminar']);
            $stmt = $conn->prepare("DELETE FROM cita WHERE id_cita = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Cita eliminada correctamente";
            $tipo_mensaje = "success";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error al eliminar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "No tienes permisos para eliminar citas. Solo los administradores pueden realizar esta acción.";
        $tipo_mensaje = "warning";
    }
}

// -------------------------------------------------------
// CREAR CITA
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_cita'])) {
    try {
        $id_cita      = isset($_POST['id_cita']) ? intval($_POST['id_cita']) : 0;
        $id_cliente   = intval($_POST['id_cliente']);
        $fecha        = trim($_POST['fecha']);
        $hora         = trim($_POST['hora']);
        $servicios    = isset($_POST['servicios']) ? $_POST['servicios'] : [];
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Si el usuario es cliente, forzar su propio id_cliente
        if (esCliente()) {
            $stmt_cli = $conn->prepare("SELECT id_cliente FROM cliente WHERE id_usuario = ?");
            $stmt_cli->bind_param("i", $_SESSION['id_usuario']);
            $stmt_cli->execute();
            $res_cli = $stmt_cli->get_result()->fetch_assoc();
            $stmt_cli->close();
            if ($res_cli) {
                $id_cliente = $res_cli['id_cliente'];
            }
        }

        // VALIDACIÓN DE CLIENTE
        if ($id_cliente <= 0) {
            throw new Exception("Debes seleccionar un cliente válido.");
        }

        // VALIDACIÓN DE HORARIO
        $hora_num = (int)str_replace(':', '', $hora);
        if ($hora_num < 800 || $hora_num > 2000) {
            throw new Exception("La hora seleccionada ($hora) está fuera de nuestro horario de atención (08:00 a 20:00).");
        }

        // VALIDACIÓN DE FECHA PASADA
        $fecha_hora_reserva = strtotime("$fecha $hora");
        $ahora = time();
        
        if (!esAdmin()) {
            if ($id_cita == 0) {
                if ($fecha_hora_reserva < $ahora) {
                    throw new Exception("No puedes programar una cita en una fecha u hora que ya pasaron.");
                }
            } else {
                // Obtener fecha/hora antigua para comparar
                $stmt_old = $conn->prepare("SELECT fecha, hora FROM cita WHERE id_cita = ?");
                $stmt_old->bind_param("i", $id_cita);
                $stmt_old->execute();
                $res_old = $stmt_old->get_result()->fetch_assoc();
                $stmt_old->close();
                
                if ($res_old) {
                    $fecha_hora_antigua = strtotime($res_old['fecha'] . " " . $res_old['hora']);
                    // Si cambia la fecha a una en el pasado, rechazar
                    if ($fecha_hora_reserva != $fecha_hora_antigua && $fecha_hora_reserva < $ahora) {
                        throw new Exception("No puedes cambiar la cita a una fecha u hora que ya pasaron.");
                    }
                }
            }
        }

        // VALIDACIÓN 2: No permitir citas empalmadas/ocupadas en el mismo horario (excluyendo la actual)
        $stmt_check = $conn->prepare("SELECT COUNT(*) as cant FROM cita WHERE fecha = ? AND hora = ? AND estado != 'Cancelada' AND id_cita != ?");
        $stmt_check->bind_param("ssi", $fecha, $hora, $id_cita);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($res_check['cant'] > 0) {
            throw new Exception("Ya existe una cita agendada para la fecha {$fecha} y hora {$hora}. El horario ya está ocupado.");
        }

        if ($id_cita > 0) {
            // ACTUALIZAR
            $stmt = $conn->prepare("UPDATE cita SET id_cliente = ?, fecha = ?, hora = ? WHERE id_cita = ?");
            $stmt->bind_param("issi", $id_cliente, $fecha, $hora, $id_cita);
            $stmt->execute();
            $stmt->close();

            // Actualizar servicios (borrar e insertar)
            $stmt_del = $conn->prepare("DELETE FROM cita_servicio WHERE id_cita = ?");
            $stmt_del->bind_param("i", $id_cita);
            $stmt_del->execute();
            $stmt_del->close();

            $id_cita_final = $id_cita;
            $mensaje = "✅ Cita actualizada correctamente";
        } else {
            // CREAR
            $estado = 'Pendiente';
            $stmt = $conn->prepare("INSERT INTO cita (id_cliente, fecha, hora, estado) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id_cliente, $fecha, $hora, $estado);
            $stmt->execute();
            $id_cita_final = $conn->insert_id;
            $stmt->close();
            $mensaje = "✅ Cita agendada correctamente para el {$fecha} a las {$hora}";
        }

        // Insertar servicios asociados
        if (!empty($servicios)) {
            $stmt_srv = $conn->prepare("INSERT INTO cita_servicio (id_cita, id_servicio, observaciones) VALUES (?, ?, ?)");
            foreach ($servicios as $id_srv) {
                $id_srv = intval($id_srv);
                $stmt_srv->bind_param("iis", $id_cita_final, $id_srv, $observaciones);
                $stmt_srv->execute();
            }
            $stmt_srv->close();
        }

        $tipo_mensaje = "success";

    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// -------------------------------------------------------
// ACTUALIZAR ESTADO DE CITA
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    try {
        $id_cita  = intval($_POST['id_cita']);
        $estado   = $_POST['estado'];
        $estados_validos = ['Pendiente', 'Completada', 'Cancelada'];
        if (!in_array($estado, $estados_validos)) {
            throw new Exception("Estado inválido");
        }

        // Verificar si la cita ya estaba completada antes
        $stmt_check = $conn->prepare("SELECT estado, id_cliente, fecha, hora FROM cita WHERE id_cita = ?");
        $stmt_check->bind_param("i", $id_cita);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        // VALIDACIÓN ESTADO COMPLETADO
        if ($estado === 'Completada') {
            if ($res_check && $res_check['estado'] === 'Completada') {
                throw new Exception("Esta cita ya fue marcada como completada anteriormente.");
            }
            
            // Validar que la cita ya haya ocurrido (fecha/hora <= AHORA)
            if ($res_check) {
                $fecha_hora_cita = strtotime($res_check['fecha'] . ' ' . $res_check['hora']);
                // Permitir completar si la hora ya pasó o es exactamente ahora
                if ($fecha_hora_cita > time()) {
                    throw new Exception("No puedes completar una cita que aún no ha ocurrido o está en el futuro.");
                }
            }
        }

        $conn->begin_transaction(); // Iniciar transacción por seguridad
        
        // Calcular los totales si la cita pasa a completada
        $total_costo = 0.0;
        $metodo_pago = null;
        
        if ($estado === 'Completada') {
            if (!isset($_POST['metodo_pago']) || empty(trim($_POST['metodo_pago']))) {
                throw new Exception("Debes especificar un método de pago.");
            }
            $metodo_pago = trim($_POST['metodo_pago']);
            $metodos_validos = ['Efectivo', 'Tarjeta', 'Transferencia'];
            if (!in_array($metodo_pago, $metodos_validos)) {
                throw new Exception("Método de pago inválido.");
            }
            
            // 1. Calcular total y obtener los nombres de servicios
            $stmt_tot = $conn->prepare("SELECT s.precio FROM cita_servicio cs JOIN servicio s ON cs.id_servicio = s.id_servicio WHERE cs.id_cita = ?");
            $stmt_tot->bind_param("i", $id_cita);
            $stmt_tot->execute();
            $res_tot = $stmt_tot->get_result();
            
            while ($row_srv = $res_tot->fetch_assoc()) {
                $total_costo += floatval($row_srv['precio']);
            }
            $stmt_tot->close();
            
            // Actualizar tabla cita con el total y el metodo de pago
            $stmt = $conn->prepare("UPDATE cita SET estado = ?, metodo_pago = ?, total_servicios = ?, total_general = ?, fecha_completado = NOW() WHERE id_cita = ?");
            $stmt->bind_param("ssddi", $estado, $metodo_pago, $total_costo, $total_costo, $id_cita);
            $stmt->execute();
            $stmt->close();
            
        } else {
            // Actualización estándar para otros estados
            $stmt = $conn->prepare("UPDATE cita SET estado = ? WHERE id_cita = ?");
            $stmt->bind_param("si", $estado, $id_cita);
            $stmt->execute();
            $stmt->close();
        }

        // Si se cancela, registrar en tabla cancelacion
        if ($estado === 'Cancelada' && !empty($_POST['motivo'])) {
            $motivo = trim($_POST['motivo']);
            $stmt_c = $conn->prepare("INSERT INTO cancelacion (id_cita, motivo) VALUES (?, ?) ON DUPLICATE KEY UPDATE motivo=VALUES(motivo)");
            $stmt_c->bind_param("is", $id_cita, $motivo);
            $stmt_c->execute();
            $stmt_c->close();
        }

        $conn->commit();

        $mensaje = "Estado de cita actualizado a: {$estado}";
        $tipo_mensaje = "success";

    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}


// -------------------------------------------------------
// OBTENER CITAS DE LA SEMANA
// -------------------------------------------------------
try {
    if (esCliente()) {
        // El cliente solo ve sus propias citas
        $sql_citas = "
            SELECT c.*, cl.nombre as nombre_cliente, cl.telefono,
                   GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') as servicios,
                   GROUP_CONCAT(s.id_servicio ORDER BY s.nombre SEPARATOR ',') as service_ids
            FROM cita c
            JOIN cliente cl ON c.id_cliente = cl.id_cliente
            LEFT JOIN cita_servicio cs ON c.id_cita = cs.id_cita
            LEFT JOIN servicio s ON cs.id_servicio = s.id_servicio
            WHERE c.fecha BETWEEN ? AND ?
            AND cl.id_usuario = ?
            GROUP BY c.id_cita
            ORDER BY c.fecha, c.hora
        ";
        $stmt_citas = $conn->prepare($sql_citas);
        $stmt_citas->bind_param("ssi", $inicio_str, $fin_str, $_SESSION['id_usuario']);
    } else {
        $sql_citas = "
            SELECT c.*, cl.nombre as nombre_cliente, cl.telefono,
                   GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') as servicios,
                   GROUP_CONCAT(s.id_servicio ORDER BY s.nombre SEPARATOR ',') as service_ids
            FROM cita c
            JOIN cliente cl ON c.id_cliente = cl.id_cliente
            LEFT JOIN cita_servicio cs ON c.id_cita = cs.id_cita
            LEFT JOIN servicio s ON cs.id_servicio = s.id_servicio
            WHERE c.fecha BETWEEN ? AND ?
            GROUP BY c.id_cita
            ORDER BY c.fecha, c.hora
        ";
        $stmt_citas = $conn->prepare($sql_citas);
        $stmt_citas->bind_param("ss", $inicio_str, $fin_str);
    }
    $stmt_citas->execute();
    $res_citas = $stmt_citas->get_result();
    $stmt_citas->close();

    // Organizar por día y hora
    $citas_por_dia = [];
    while ($row = $res_citas->fetch_assoc()) {
        $dia = date('N', strtotime($row['fecha'])); // 1=Lun ... 7=Dom
        $citas_por_dia[$dia][] = $row;
    }
} catch (Exception $e) {
    $citas_por_dia = [];
}

// -------------------------------------------------------
// OBTENER CLIENTES Y SERVICIOS PARA EL FORMULARIO
// -------------------------------------------------------
try {
    $clientes_list = $conn->query("SELECT id_cliente, nombre FROM cliente ORDER BY nombre");
    $servicios_list = $conn->query("SELECT id_servicio, nombre, precio, duracion_minutos FROM servicio ORDER BY nombre");
} catch (Exception $e) {
    $clientes_list = false;
    $servicios_list = false;
}

// Obtener el id_cliente del usuario actual si es cliente
$id_cliente_actual = null;
if (esCliente()) {
    $stmt_me = $conn->prepare("SELECT id_cliente, nombre FROM cliente WHERE id_usuario = ?");
    $stmt_me->bind_param("i", $_SESSION['id_usuario']);
    $stmt_me->execute();
    $me = $stmt_me->get_result()->fetch_assoc();
    $stmt_me->close();
    if ($me) $id_cliente_actual = $me;
}

// Días de la semana para el encabezado
$dias_semana = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $inicio_semana;
    $d->modify("+{$i} days");
    $dias_semana[] = [
        'num'    => $i + 1,  // 1=Lun ... 7=Dom
        'nombre' => ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][$i],
        'fecha'  => $d->format('Y-m-d'),
        'label'  => $d->format('d/m'),
        'es_hoy' => $d->format('Y-m-d') === (new DateTime())->format('Y-m-d'),
    ];
}

// Horas de atención (8am - 8pm, en intervalos de 1 hora)
$horas_atencion = [];
for ($h = 8; $h <= 20; $h++) {
    $horas_atencion[] = sprintf('%02d:00', $h);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citas - Sistema Barbería</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        /* ============ HEADER ============ */
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
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

        /* ============ CONTAINER ============ */
        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 24px;
        }

        /* Alertas manejadas por inc/ui.php */

        /* ============ TOOLBAR ============ */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .week-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 8px 14px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .week-nav a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .week-nav a:hover { background: #f0f2ff; }
        .week-nav .week-label {
            font-size: 14px;
            font-weight: 600;
            color: #444;
            min-width: 200px;
            text-align: center;
        }
        .btn-nueva-cita {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(102,126,234,0.35);
        }
        .btn-nueva-cita:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(102,126,234,0.45);
        }

        /* ============ CALENDAR ============ */
        .calendar-wrapper {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .calendar-title {
            background: #2d2d3a;
            color: white;
            text-align: center;
            padding: 16px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: 110px repeat(7, 1fr);
            overflow-x: auto;
        }

        /* Header de días */
        .cal-header-cell {
            padding: 12px 8px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #3b3b4d;
            color: #ccc;
            border-right: 1px solid #4a4a5a;
            border-bottom: 1px solid #e5e7eb;
        }
        .cal-header-cell.hora-label {
            background: #3b3b4d;
            color: #aaa;
            font-size: 11px;
        }
        .cal-header-cell.hoy {
            background: #5a4fcf;
            color: white;
        }
        .cal-header-cell .fecha-num {
            font-size: 20px;
            font-weight: 700;
            display: block;
            color: white;
            margin-top: 4px;
        }
        .cal-header-cell.hoy .fecha-num {
            background: #ffd700;
            color: #333;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px auto 0;
            font-size: 16px;
        }

        /* Filas de horas */
        .cal-row {
            display: contents;
        }
        .cal-time-cell {
            padding: 8px;
            background: #f8f9fa;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80px;
        }
        .cal-day-cell {
            padding: 6px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            min-height: 80px;
            vertical-align: top;
            background: white;
            transition: background 0.15s;
            cursor: pointer;
            position: relative;
        }
        .cal-day-cell:hover {
            background: #f5f7ff;
        }
        .cal-day-cell.hoy-col {
            background: #fafafe;
        }
        .cal-day-cell:last-child { border-right: none; }

        /* Tarjeta de cita */
        .cita-card {
            background: linear-gradient(135deg, #a78bfa, #7c3aed);
            color: white;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 5px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(124,58,237,0.3);
            position: relative;
        }
        .cita-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124,58,237,0.4);
        }
        .cita-card.estado-Pendiente {
            background: linear-gradient(135deg, #93c5fd, #3b82f6);
            box-shadow: 0 2px 6px rgba(59,130,246,0.3);
        }
        .cita-card.estado-Completada {
            background: linear-gradient(135deg, #6ee7b7, #10b981);
            box-shadow: 0 2px 6px rgba(16,185,129,0.3);
        }
        .cita-card.estado-Cancelada {
            background: linear-gradient(135deg, #fca5a5, #ef4444);
            box-shadow: 0 2px 6px rgba(239,68,68,0.3);
            opacity: 0.8;
        }
        .cita-nombre {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cita-hora-tag {
            font-size: 10px;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        .cita-servicios {
            font-size: 10px;
            opacity: 0.85;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cita-estado-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            background: rgba(255,255,255,0.3);
            margin-top: 3px;
        }

        /* Legend */
        .legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            padding: 12px 16px;
            background: #f8f9fa;
            border-top: 1px solid #e5e7eb;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #666;
        }
        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 4px;
        }
        .legend-dot.pendiente  { background: linear-gradient(135deg, #93c5fd, #3b82f6); }
        .legend-dot.completada { background: linear-gradient(135deg, #6ee7b7, #10b981); }
        .legend-dot.cancelada  { background: linear-gradient(135deg, #fca5a5, #ef4444); }

        /* ============ LISTA (vista móvil / adicional) ============ */
        .lista-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-top: 24px;
            overflow: hidden;
        }
        .lista-header {
            padding: 20px 24px;
            border-bottom: 2px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .lista-header h3 { font-size: 16px; font-weight: 700; color: #333; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        th { background: #667eea; color: white; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }
        tr:hover td { background: #f8f9ff; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge.Pendiente  { background: #dbeafe; color: #1d4ed8; }
        .badge.Completada { background: #d1fae5; color: #065f46; }
        .badge.Cancelada  { background: #fee2e2; color: #991b1b; }

        .action-links { display: flex; gap: 8px; align-items: center; }
        .action-links button, .action-links a {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 5px;
            text-decoration: none;
            font-family: inherit;
            transition: all 0.2s;
        }
        .action-links button:hover, .action-links a:hover { background: #f0f2ff; }
        .action-links .delete { color: #e74c3c; }
        .action-links .delete:hover { background: #fff0f0; }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }

        /* ============ MODAL ============ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 18px; font-weight: 700; }
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.3); }

        .modal-body { padding: 24px; }

        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 11px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 9px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
            color: #333;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        .form-group textarea { resize: vertical; min-height: 70px; }

        /* Servicios checkboxes */
        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .srv-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9ff;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
        }
        .srv-checkbox:hover { border-color: #667eea; background: #f0f2ff; }
        .srv-checkbox input[type=checkbox] { width: 16px; height: 16px; accent-color: #667eea; }
        .srv-checkbox.checked { border-color: #667eea; background: #ede9fe; }
        
        /* Servicios pagination */
        .srv-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e5e7eb;
        }
        .srv-page-btn {
            background: #f8f9ff;
            border: 1px solid #667eea;
            color: #667eea;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .srv-page-btn:hover:not(:disabled) {
            background: #667eea;
            color: white;
        }
        .srv-page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            border-color: #ccc;
            color: #999;
        }
        .srv-page-info {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            min-width: 100px;
            text-align: center;
        }

        .srv-info { flex: 1; }
        .srv-nombre { font-weight: 600; color: #333; }
        .srv-detalle { color: #888; font-size: 11px; }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(102,126,234,0.4); }
        .btn-secondary { background: #f3f4f6; color: #666; }
        .btn-secondary:hover { background: #e5e7eb; }

        /* Modal de detalles */
        .detalle-row {
            display: flex;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 13px;
        }
        .detalle-row:last-child { border-bottom: none; }
        .detalle-icon { width: 30px; font-size: 16px; flex-shrink: 0; }
        .detalle-label { width: 120px; font-weight: 600; color: #888; flex-shrink: 0; }
        .detalle-valor { color: #333; }

        .estado-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .btn-estado {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-estado.pendiente  { background: #dbeafe; color: #1d4ed8; }
        .btn-estado.completada { background: #d1fae5; color: #065f46; }
        .btn-estado.cancelada  { background: #fee2e2; color: #991b1b; }
        .btn-estado.editar     { background: #ede9fe; color: #6d28d9; }
        .btn-estado:hover { filter: brightness(0.9); transform: translateY(-1px); }

        .motivo-group { margin-top: 10px; display: none; }
        .motivo-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            min-height: 60px;
        }
        .required { color: #e74c3c; }
        <?php echo getPaginationStyles(); ?>

        @media (max-width: 768px) {
            .calendar-grid { grid-template-columns: 70px repeat(7, minmax(100px, 1fr)); }
            .toolbar { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php require_once("inc/sidebar.php"); ?>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>📅 Gestión de Citas</h1>
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


    <!-- Toolbar -->
    <div class="toolbar">
        <div class="week-nav">
            <a href="?semana=<?php echo $semana_offset - 1; ?>" title="Semana anterior">&#8249;</a>
            <span class="week-label">
                <?php echo $inicio_semana->format('d M') . ' – ' . $fin_semana->format('d M Y'); ?>
            </span>
            <a href="?semana=0" title="Hoy" style="font-size:13px; padding: 0 6px;">Hoy</a>
            <a href="?semana=<?php echo $semana_offset + 1; ?>" title="Semana siguiente">&#8250;</a>
        </div>
        <button class="btn-nueva-cita" onclick="abrirModalNueva()">
            ➕ Nueva Cita
        </button>
    </div>

    <!-- CALENDARIO SEMANAL -->
    <div class="calendar-wrapper">
        <div class="calendar-title">
            Horario de Citas — <?php echo $inicio_semana->format('d M') . ' al ' . $fin_semana->format('d M Y'); ?>
        </div>

        <div class="calendar-grid">
            <!-- Header -->
            <div class="cal-header-cell hora-label">HORA</div>
            <?php foreach ($dias_semana as $dia): ?>
            <div class="cal-header-cell <?php echo $dia['es_hoy'] ? 'hoy' : ''; ?>">
                <?php echo $dia['nombre']; ?>
                <?php if ($dia['es_hoy']): ?>
                    <div class="fecha-num"><?php echo $dia['label']; ?></div>
                <?php else: ?>
                    <span class="fecha-num"><?php echo $dia['label']; ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Filas de horas -->
            <?php foreach ($horas_atencion as $hora): ?>
                <div class="cal-time-cell"><?php echo $hora; ?></div>
                <?php foreach ($dias_semana as $dia): ?>
                <div class="cal-day-cell <?php echo $dia['es_hoy'] ? 'hoy-col' : ''; ?>"
                     onclick="abrirModalNueva('<?php echo $dia['fecha']; ?>', '<?php echo $hora; ?>')">
                    <?php
                    // Mostrar citas de este día y hora
                    $num_dia = $dia['num'];
                    if (isset($citas_por_dia[$num_dia])) {
                        foreach ($citas_por_dia[$num_dia] as $cita) {
                            $hora_cita = substr($cita['hora'], 0, 5);
                            $hora_bloque = substr($hora, 0, 2);
                            $hora_cita_h = substr($hora_cita, 0, 2);
                            if ($hora_cita_h == $hora_bloque) {
                                $estado_class = 'estado-' . $cita['estado'];
                                echo '<div class="cita-card ' . $estado_class . '" onclick="event.stopPropagation(); verCita(' . $cita['id_cita'] . ')" 
                                    data-id="' . $cita['id_cita'] . '">';
                                echo '<div class="cita-nombre">✂️ ' . htmlspecialchars($cita['nombre_cliente']) . '</div>';
                                echo '<div class="cita-hora-tag">🕐 ' . $hora_cita . '</div>';
                                if (!empty($cita['servicios'])) {
                                    echo '<div class="cita-servicios">🪒 ' . htmlspecialchars($cita['servicios']) . '</div>';
                                }
                                echo '<span class="cita-estado-badge">' . $cita['estado'] . '</span>';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <!-- Leyenda -->
        <div class="legend">
            <div class="legend-item"><div class="legend-dot pendiente"></div>Pendiente</div>
            <div class="legend-item"><div class="legend-dot completada"></div>Completada</div>
            <div class="legend-item"><div class="legend-dot cancelada"></div>Cancelada</div>
            <span style="font-size:11px;color:#aaa;margin-left:auto;">Haz clic en una celda para agendar nueva cita</span>
        </div>
    </div>

    <!-- LISTA DE CITAS DE LA SEMANA -->
    <div class="lista-section" id="lista-semanal">
        <div class="lista-header">
            <h3>📋 Lista de Citas de la Semana</h3>
        </div>

        <?php
        // Recolectar todas las citas para la lista
        $todas_citas = [];
        foreach ($citas_por_dia as $dia_num => $citas_del_dia) {
            foreach ($citas_del_dia as $cita) {
                $todas_citas[] = $cita;
            }
        }
        usort($todas_citas, function($a, $b) {
            return strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']);
        });

        // Paginación manual para el array de citas semanales
        $limit_citas = 12;
        $total_citas = count($todas_citas);
        $total_pages_citas = ceil($total_citas / $limit_citas);
        $current_page_citas = isset($_GET['p_citas']) ? max(1, intval($_GET['p_citas'])) : 1;
        $offset_citas = ($current_page_citas - 1) * $limit_citas;
        $todas_citas_pag = array_slice($todas_citas, $offset_citas, $limit_citas);
        ?>

        <?php if (!empty($todas_citas_pag)): ?>
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Servicios</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todas_citas_pag as $cita): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($cita['nombre_cliente']); ?></strong>
                        <?php if (!empty($cita['telefono'])): ?>
                        <br><small style="color:#999;">📞 <?php echo htmlspecialchars($cita['telefono']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($cita['fecha'])); ?></td>
                    <td>🕐 <?php echo substr($cita['hora'], 0, 5); ?></td>
                    <td>
                        <?php if (!empty($cita['servicios'])): ?>
                            <small>✂️ <?php echo htmlspecialchars($cita['servicios']); ?></small>
                        <?php else: ?>
                            <small style="color:#ccc;">Sin servicios</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $cita['estado']; ?>"><?php echo $cita['estado']; ?></span></td>
                    <td>
                        <div class="action-links">
                            <button onclick="verCita(<?php echo $cita['id_cita']; ?>)">🔍 Ver</button>
                            <?php if (esAdmin()): ?>
                            <a href="?eliminar=<?php echo $cita['id_cita']; ?>&semana=<?php echo $semana_offset; ?>"
                               class="delete"
                               onclick="event.preventDefault(); confirmacion('¿Eliminar esta cita?', '🗑️ Eliminar', () => window.location.href='?eliminar=<?php echo $cita['id_cita']; ?>&semana=<?php echo $semana_offset; ?>')">🗑️ Eliminar</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php echo renderPagination($current_page_citas, $total_pages_citas, "?semana=$semana_offset&p_citas=", 'p_citas', '#lista-semanal'); ?>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <p>No hay citas registradas esta semana</p>
            <p style="font-size:12px;color:#bbb;margin-top:6px;">¡Haz clic en el calendario para agendar una!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ MODAL: NUEVA CITA ============ -->
<div class="modal-overlay" id="modalNueva">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalNuevaTitle">➕ Nueva Cita</h3>
            <button class="modal-close" onclick="cerrarModal('modalNueva')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="id_cita" id="id_cita_nueva" value="0">
            <input type="hidden" name="semana" value="<?php echo $semana_offset; ?>">
            <div class="modal-body">
                <?php if (!esCliente()): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_cliente">Cliente <span class="required">*</span></label>
                        <select name="id_cliente" id="id_cliente" required>
                            <option value="">-- Selecciona cliente --</option>
                            <?php if ($clientes_list && $clientes_list->num_rows > 0): ?>
                                <?php while ($cl = $clientes_list->fetch_assoc()): ?>
                                <option value="<?php echo $cl['id_cliente']; ?>">
                                    <?php echo htmlspecialchars($cl['nombre']); ?>
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="id_cliente" id="id_cliente" value="<?php echo $id_cliente_actual ? $id_cliente_actual['id_cliente'] : ''; ?>">
                    <?php if ($id_cliente_actual): ?>
                    <div class="detalle-row">
                        <div class="detalle-icon">👤</div>
                        <div class="detalle-label">Cliente:</div>
                        <div class="detalle-valor"><?php echo htmlspecialchars($id_cliente_actual['nombre']); ?></div>
                    </div>
                    <?php else: ?>
                    <div class="alert error">No tienes un perfil de cliente. Contacta al administrador.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_nueva">Fecha <span class="required">*</span></label>
                        <input type="date" name="fecha" id="fecha_nueva" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="hora_nueva">Hora <span class="required">*</span></label>
                        <select name="hora" id="hora_nueva" required>
                            <?php foreach ($horas_atencion as $h): ?>
                            <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Servicios</label>
                    <div class="servicios-grid">
                        <?php
                        if ($servicios_list && $servicios_list->num_rows > 0) {
                            $servicios_list->data_seek(0);
                            while ($srv = $servicios_list->fetch_assoc()) {
                                echo '<label class="srv-checkbox" id="srv-lbl-' . $srv['id_servicio'] . '">';
                                echo '<input type="checkbox" name="servicios[]" value="' . $srv['id_servicio'] . '" 
                                    onchange="toggleSrvLabel(this)">';
                                echo '<div class="srv-info">';
                                echo '<div class="srv-nombre">✂️ ' . htmlspecialchars($srv['nombre']) . '</div>';
                                echo '<div class="srv-detalle">$' . number_format($srv['precio'], 2) . ' · ' . $srv['duracion_minutos'] . ' min</div>';
                                echo '</div>';
                                echo '</label>';
                            }
                        } else {
                            echo '<p style="color:#999;font-size:13px;">No hay servicios registrados</p>';
                        }
                        ?>
                    </div>
                    
                    <div class="srv-pagination" id="srvPaginationControls" style="display: none;">
                        <button type="button" class="srv-page-btn" id="btnSrvPrev" onclick="changeSrvPage(-1)">
                            &laquo; Anterior
                        </button>
                        <span class="srv-page-info" id="srvPageInfo">Página 1 de 1</span>
                        <button type="button" class="srv-page-btn" id="btnSrvNext" onclick="changeSrvPage(1)">
                            Siguiente &raquo;
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" placeholder="Notas adicionales para la cita..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNueva')">Cancelar</button>
                <button type="submit" name="guardar_cita" class="btn btn-primary" id="btnGuardarCita">💾 Guardar Cita</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ MODAL: VER/EDITAR CITA ============ -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal">
        <div class="modal-header">
            <h3>🔍 Detalle de Cita</h3>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">&times;</button>
        </div>
        <div class="modal-body" id="modalDetalleBody">
            <div style="text-align:center; padding:30px;">
                <span style="font-size:32px;">⏳</span>
                <p>Cargando...</p>
            </div>
        </div>
    </div>
</div>

<!-- Datos de citas para JavaScript -->
<script>
const CITAS_DATA = <?php
$citas_js = [];
foreach ($todas_citas as $cita) {
    // Obtener observaciones (vienen en la primera fila de cita_servicio usualmente)
    // En nuestro query, observaciones no está en el GROUP BY pero podemos obtenerlo
    // Para simplificar, asumiremos que todas las observaciones de una cita son iguales
    $obs_query = $conn->query("SELECT observaciones FROM cita_servicio WHERE id_cita = " . $cita['id_cita'] . " LIMIT 1");
    $obs = $obs_query ? $obs_query->fetch_assoc()['observaciones'] : '';

    $citas_js[$cita['id_cita']] = [
        'id_cita'        => $cita['id_cita'],
        'id_cliente'     => $cita['id_cliente'],
        'nombre_cliente' => $cita['nombre_cliente'],
        'telefono'       => $cita['telefono'] ?? '',
        'fecha'          => $cita['fecha'],
        'hora'           => substr($cita['hora'], 0, 5),
        'estado'         => $cita['estado'],
        'servicios'      => $cita['servicios'] ?? '',
        'service_ids'    => array_map('intval', explode(',', $cita['service_ids'] ?? '')),
        'observaciones'  => $obs
    ];
}
echo json_encode($citas_js, JSON_UNESCAPED_UNICODE);
?>;

const CITAS_OCUPADAS = <?php
    $ocupadas = [];
    $stmt_oc = $conn->query("SELECT fecha, hora FROM cita WHERE estado != 'Cancelada'");
    if ($stmt_oc) {
        while ($row = $stmt_oc->fetch_assoc()) {
            $f = $row['fecha'];
            $h = substr($row['hora'], 0, 5);
            if (!isset($ocupadas[$f])) $ocupadas[$f] = [];
            $ocupadas[$f][] = $h;
        }
    }
    echo json_encode($ocupadas);
?>;

const ES_ADMIN   = <?php echo esAdmin()   ? 'true' : 'false'; ?>;
const ES_BARBERO = <?php echo esBarbero() ? 'true' : 'false'; ?>;
const SEMANA_OFFSET = <?php echo $semana_offset; ?>;

function abrirModalNueva(fecha = '', hora = '', id_cita = 0) {
    const modal = document.getElementById('modalNueva');
    const title = document.getElementById('modalNuevaTitle');
    const btn = document.getElementById('btnGuardarCita');
    const idInput = document.getElementById('id_cita_nueva');
    
    // Reset form
    idInput.value = '0';
    title.innerText = '📅 Agendar Nueva Cita';
    btn.innerText = '💾 Guardar Cita';
    document.getElementById('fecha_nueva').value = fecha;
    document.getElementById('hora_nueva').value = hora;
    document.getElementById('id_cliente').value = '';
    document.getElementById('observaciones').value = '';
    
    // Desmarcar todos los servicios
    document.querySelectorAll('input[name="servicios[]"]').forEach(cb => {
        cb.checked = false;
        toggleSrvLabel(cb);
    });

    // Reset pagination
    setTimeout(() => initSrvPagination(), 50);

    if (id_cita > 0) {
        const c = CITAS_DATA[id_cita];
        if (c) {
            idInput.value = id_cita;
            title.innerText = '✏️ Editar Cita';
            btn.innerText = '💾 Actualizar Cita';
            document.getElementById('fecha_nueva').value = c.fecha;
            document.getElementById('hora_nueva').value = c.hora;
            document.getElementById('id_cliente').value = c.id_cliente;
            document.getElementById('observaciones').value = c.observaciones || '';
            
            // Marcar servicios
            if (c.service_ids) {
                c.service_ids.forEach(sid => {
                    const cb = document.querySelector(`input[name="servicios[]"][value="${sid}"]`);
                    if (cb) {
                        cb.checked = true;
                        toggleSrvLabel(cb);
                    }
                });
            }
        }
    }
    
    actualizarHorasDisponibles();
    modal.classList.add('active');
}

function actualizarHorasDisponibles() {
    const fechaSelect = document.getElementById('fecha_nueva').value;
    const horaSelect = document.getElementById('hora_nueva');
    const idCitaActual = document.getElementById('id_cita_nueva').value;
    
    let horaOriginal = '';
    if (idCitaActual > 0 && CITAS_DATA[idCitaActual] && CITAS_DATA[idCitaActual].fecha === fechaSelect) {
        horaOriginal = CITAS_DATA[idCitaActual].hora;
    }

    const horasOcupadas = CITAS_OCUPADAS[fechaSelect] || [];
    
    Array.from(horaSelect.options).forEach(opt => {
        if (horasOcupadas.includes(opt.value) && opt.value !== horaOriginal) {
            opt.disabled = true;
            opt.text = opt.value + ' (Ocupado)';
        } else {
            opt.disabled = false;
            opt.text = opt.value;
        }
    });
}

document.getElementById('fecha_nueva').addEventListener('change', actualizarHorasDisponibles);

function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
}

function toggleSrvLabel(cb) {
    const lbl = document.getElementById('srv-lbl-' + cb.value);
    if (lbl) lbl.classList.toggle('checked', cb.checked);
}

/* PAGINACIÓN DE SERVICIOS EN EL MODAL */
let currentSrvPage = 1;
const srvPerPage = 6;

function initSrvPagination() {
    const services = document.querySelectorAll('.srv-checkbox');
    const totalPages = Math.ceil(services.length / srvPerPage);
    
    if (totalPages <= 1) {
        document.getElementById('srvPaginationControls').style.display = 'none';
        services.forEach(s => s.style.display = 'flex');
        return;
    }

    document.getElementById('srvPaginationControls').style.display = 'flex';
    showSrvPage(1);
}

function showSrvPage(page) {
    const services = document.querySelectorAll('.srv-checkbox');
    const totalPages = Math.ceil(services.length / srvPerPage);
    
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    
    currentSrvPage = page;
    
    services.forEach((srv, index) => {
        const start = (page - 1) * srvPerPage;
        const end = start + srvPerPage;
        if (index >= start && index < end) {
            srv.style.display = 'flex';
        } else {
            srv.style.display = 'none';
        }
    });
    
    document.getElementById('srvPageInfo').innerText = `Página ${page} de ${totalPages}`;
    document.getElementById('btnSrvPrev').disabled = (page === 1);
    document.getElementById('btnSrvNext').disabled = (page === totalPages);
}

function changeSrvPage(delta) {
    showSrvPage(currentSrvPage + delta);
}

function verCita(id) {
    const c = CITAS_DATA[id];
    if (!c) return;

    const estadoColor = {
        'Pendiente':  '#dbeafe',
        'Completada': '#d1fae5',
        'Cancelada':  '#fee2e2'
    };
    const estadoTxt = {
        'Pendiente':  '#1d4ed8',
        'Completada': '#065f46',
        'Cancelada':  '#991b1b'
    };

    let botonesEstado = '';
    let formEstado = '';
    let btnEditar = '';

    if ((ES_ADMIN || ES_BARBERO) && c.estado === 'Pendiente') {
        btnEditar = `
            <button type="button" class="btn-estado editar" style="flex:1; padding: 10px 0; font-size: 13px;" onclick="cerrarModal('modalDetalle'); abrirModalNueva('','',${id})">
                ✏️ Editar Datos
            </button>
        `;
    }

    if (ES_ADMIN || ES_BARBERO) {
        formEstado = `
            <form method="POST" id="formEstado_${id}" style="margin-top:12px;">
                <input type="hidden" name="id_cita" value="${id}">
                <input type="hidden" name="cambiar_estado" value="1">
                <input type="hidden" name="semana" value="${SEMANA_OFFSET}">
                <div style="font-size:13px;font-weight:600;color:#888;margin-bottom:8px;">Cambiar estado:</div>
                <div class="estado-btns">
                    <button type="button" class="btn-estado pendiente" onclick="setEstado(${id},'Pendiente')">🕐 Pendiente</button>
                    <button type="button" class="btn-estado completada" onclick="setEstado(${id},'Completada')">✅ Completada</button>
                    <button type="button" class="btn-estado cancelada" onclick="setEstado(${id},'Cancelada')">❌ Cancelar</button>
                </div>
                <input type="hidden" name="estado" id="estadoInput_${id}" value="${c.estado}">
                <div class="motivo-group" id="motivoGroup_${id}">
                    <label style="font-size:12px;font-weight:600;color:#666;display:block;margin-bottom:6px;">Motivo de cancelación:</label>
                    <textarea name="motivo" placeholder="Describe el motivo..."></textarea>
                </div>
                <!-- Bloque de Método de Pago -->
                <div id="pagoGroup_${id}" style="display:none; margin-top:10px;">
                    <label style="font-size:12px;font-weight:600;color:#666;display:block;margin-bottom:6px;">Confirmar Método de Pago:</label>
                    <select name="metodo_pago" style="width:100%; padding:10px; border:2px solid #e5e7eb; border-radius:8px; font-size:13px; outline:none; font-family:inherit;">
                        <option value="Efectivo">💵 Efectivo</option>
                        <option value="Tarjeta">💳 Tarjeta</option>
                        <option value="Transferencia">📲 Transferencia</option>
                    </select>
                    <small style="color:#888; font-size:11px; margin-top:4px; display:block;">El total se calculará y registrará automáticamente en Ventas.</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    ${btnEditar}
                    <button type="submit" class="btn btn-primary" style="flex:1; padding: 10px 0; font-size: 13px; display: flex; align-items: center; justify-content: center; text-align: center;">
                        💾 Guardar cambios
                    </button>
                </div>
            </form>
        `;
    }

    document.getElementById('modalDetalleBody').innerHTML = `
        <div class="detalle-row">
            <div class="detalle-icon">👤</div>
            <div class="detalle-label">Cliente:</div>
            <div class="detalle-valor"><strong>${c.nombre_cliente}</strong>${c.telefono ? '<br><small style="color:#999;">📞 ' + c.telefono + '</small>' : ''}</div>
        </div>
        <div class="detalle-row">
            <div class="detalle-icon">📅</div>
            <div class="detalle-label">Fecha:</div>
            <div class="detalle-valor">${formatFecha(c.fecha)}</div>
        </div>
        <div class="detalle-row">
            <div class="detalle-icon">🕐</div>
            <div class="detalle-label">Hora:</div>
            <div class="detalle-valor">${c.hora}</div>
        </div>
        <div class="detalle-row">
            <div class="detalle-icon">✂️</div>
            <div class="detalle-label">Servicios:</div>
            <div class="detalle-valor">${c.servicios || '<span style="color:#ccc">Sin servicios asignados</span>'}</div>
        </div>
        <div class="detalle-row">
            <div class="detalle-icon">🏷️</div>
            <div class="detalle-label">Estado:</div>
            <div class="detalle-valor">
                <span style="background:${estadoColor[c.estado]};color:${estadoTxt[c.estado]};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
                    ${c.estado}
                </span>
            </div>
        </div>
        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:10px;">
            ${formEstado}
        </div>
    `;

    document.getElementById('modalDetalle').classList.add('active');
}

function setEstado(id, estado) {
    document.getElementById('estadoInput_' + id).value = estado;
    
    // Controles de cancelación
    const grupoCancel = document.getElementById('motivoGroup_' + id);
    if (grupoCancel) {
        grupoCancel.style.display = estado === 'Cancelada' ? 'block' : 'none';
    }
    
    // Controles de pago
    const grupoPago = document.getElementById('pagoGroup_' + id);
    if (grupoPago) {
        grupoPago.style.display = estado === 'Completada' ? 'block' : 'none';
    }
}

function formatFecha(fecha) {
    const [y, m, d] = fecha.split('-');
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${d} ${meses[parseInt(m)-1]} ${y}`;
}

// Cerrar modal al hacer clic fuera
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) cerrarModal(this.id);
    });
});

// Auto-ocultar alertas
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'all 0.5s';
        a.style.opacity = '0';
        a.style.transform = 'translateY(-10px)';
        setTimeout(() => a.remove(), 500);
    });
}, 4000);
</script>

<?php include 'inc/ui.php'; ?>
    </div> <!-- .main-content -->
</div> <!-- .dashboard-layout -->

</body>
</html>
