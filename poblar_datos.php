<?php
// Script para insertar 200 registros de prueba en cada módulo de la base de datos de barberiapo
require_once 'conexion.php';

// Limpiar tiempo límite de ejecución para que no se detenga a mitad
set_time_limit(0);

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>Iniciando inserción masiva de datos (200 registros por módulo)...</h2>";

// Arrays de nombres y apellidos para generar nombres aleatorios
$nombres = ['Juan', 'Pedro', 'Maria', 'Ana', 'Luis', 'Carlos', 'Jose', 'Marta', 'Lucia', 'Jorge', 'Miguel', 'Elena', 'Laura', 'Diego', 'Sofia', 'Andres', 'Paula', 'David', 'Carmen', 'Daniel', 'Alejandro', 'Manuel', 'Isabel', 'Raul', 'Fernando', 'Javier', 'Rosa', 'Teresa', 'Victor', 'Roberto', 'Ricardo', 'Antonio', 'Francisco', 'Julia', 'Patricia', 'Alberto', 'Ruben', 'Oscar'];
$apellidos = ['Garcia', 'Martinez', 'Rodriguez', 'Lopez', 'Perez', 'Gonzalez', 'Gomez', 'Fernandez', 'Ruiz', 'Diaz', 'Alvarez', 'Moreno', 'Munoz', 'Romero', 'Alonso', 'Gutierrez', 'Navarro', 'Torres', 'Dominguez', 'Vazquez', 'Ramos', 'Gil', 'Ramirez', 'Serrano', 'Blanco', 'Molina', 'Morales', 'Suarez', 'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marin', 'Sanz', 'Iglesias', 'Nunez', 'Medina'];

function getRandomName($nombres, $apellidos) {
    return $nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)];
}

$conn->begin_transaction();

try {
    // Asegurarse de que exista el rol Admin y Barbero
    $conn->query("INSERT IGNORE INTO rol (id_rol, nombre_rol, descripcion) VALUES (1, 'Admin', 'Administrador del sistema'), (2, 'Barbero', 'Barbero de la tienda')");

    // 1. Insertar 200 Usuarios
    echo "<p>Insertando 200 Usuarios...</p>";
    $id_usuarios = [];
    for ($i = 1; $i <= 200; $i++) {
        $nombre = getRandomName($nombres, $apellidos) . " " . rand(1, 999);
        $pass = password_hash('123456', PASSWORD_DEFAULT);
        $rol = ($i % 10 == 0) ? 1 : 2; // 1 de cada 10 es Admin, los demás Barberos
        $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_rol) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $pass, $rol);
        $stmt->execute();
        $id_usuarios[] = $conn->insert_id;
    }

    // 2. Insertar 200 Clientes
    echo "<p>Insertando 200 Clientes...</p>";
    $id_clientes = [];
    for ($i = 1; $i <= 200; $i++) {
        $nombre = getRandomName($nombres, $apellidos);
        $telefono = "55" . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $id_user = $id_usuarios[array_rand($id_usuarios)]; 
        $stmt = $conn->prepare("INSERT INTO cliente (nombre, telefono, id_usuario) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $telefono, $id_user);
        $stmt->execute();
        $id_clientes[] = $conn->insert_id;
    }

    // 3. Insertar 200 Servicios
    echo "<p>Insertando 200 Servicios...</p>";
    $id_servicios = [];
    $tipos_servicio = ['Corte', 'Barba', 'Tinte', 'Mascarilla', 'Peinado', 'Lavado', 'Corte Premium', 'Afeitado Clásico', 'Cejas', 'Facial'];
    for ($i = 1; $i <= 200; $i++) {
        $nombre = $tipos_servicio[array_rand($tipos_servicio)] . " " . rand(1, 9999);
        $duracion = rand(15, 120); 
        $precio = rand(50, 500) + (rand(0, 99) / 100);
        $stmt = $conn->prepare("INSERT INTO servicio (nombre, duracion_minutos, precio) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $nombre, $duracion, $precio);
        $stmt->execute();
        $id_servicios[] = $conn->insert_id;
    }

    // 4. Insertar 200 Productos
    echo "<p>Insertando 200 Productos...</p>";
    $id_productos = [];
    $marcas = ['Gillette', 'Nivea', 'Proraso', 'Suavecito', 'Reuzel', 'American Crew', 'Uppercut', 'Layrite'];
    $tipos_prod = ['Cera', 'Gel', 'Aceite para barba', 'Bálsamo', 'Loción', 'Shampoo', 'Acondicionador', 'Navajas'];
    for ($i = 1; $i <= 200; $i++) {
        $nombre = $tipos_prod[array_rand($tipos_prod)] . " " . $marcas[array_rand($marcas)] . " " . rand(1, 9999);
        $stock = rand(10, 500); // Dar suficiente stock para las ventas
        $precio = rand(80, 800) + (rand(0, 99) / 100);
        $stmt = $conn->prepare("INSERT INTO producto (nombre, stock, precio) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $nombre, $stock, $precio);
        $stmt->execute();
        $id_productos[] = $conn->insert_id;
    }

    // 5. Insertar 200 Citas y sus detalles
    echo "<p>Insertando 200 Citas...</p>";
    $estados = ['Pendiente', 'Completada', 'Cancelada'];
    $metodos = ['Efectivo', 'Tarjeta', 'Transferencia'];
    for ($i = 1; $i <= 200; $i++) {
        $id_cliente = $id_clientes[array_rand($id_clientes)];
        $id_barbero = $id_usuarios[array_rand($id_usuarios)];
        
        $timestamp = time() - rand(-2592000, 31536000); // Fechas entre 1 año en el pasado y 1 mes en el futuro
        $fecha = date("Y-m-d", $timestamp);
        $hora = date("H:i", $timestamp);
        
        $estado = $estados[array_rand($estados)];
        $metodo = $estado == 'Completada' ? $metodos[array_rand($metodos)] : null;
        $fecha_completado = $estado == 'Completada' ? date("Y-m-d H:i:s", $timestamp + rand(1800, 7200)) : null;
        
        $stmt = $conn->prepare("INSERT INTO cita (id_cliente, id_barbero, fecha, hora, estado, metodo_pago, fecha_completado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $id_cliente, $id_barbero, $fecha, $hora, $estado, $metodo, $fecha_completado);
        $stmt->execute();
        $id_cita = $conn->insert_id;
        
        // Agregar entre 1 y 3 servicios por cita
        $num_servicios = rand(1, 3);
        $total_servicios = 0;
        $servicios_usados = [];
        for ($j = 0; $j < $num_servicios; $j++) {
            $id_servicio = $id_servicios[array_rand($id_servicios)];
            if (in_array($id_servicio, $servicios_usados)) continue;
            $servicios_usados[] = $id_servicio;

            $res = $conn->query("SELECT precio, duracion_minutos FROM servicio WHERE id_servicio = $id_servicio");
            if($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                
                $stmt_det = $conn->prepare("INSERT INTO cita_servicio (id_cita, id_servicio, precio_servicio, duracion_minutos) VALUES (?, ?, ?, ?)");
                $stmt_det->bind_param("iidi", $id_cita, $id_servicio, $row['precio'], $row['duracion_minutos']);
                $stmt_det->execute();
                
                $total_servicios += $row['precio'];
            }
        }
        
        $conn->query("UPDATE cita SET total_servicios = $total_servicios, total_general = $total_servicios WHERE id_cita = $id_cita");
    }

    // 6. Insertar 200 Ventas y sus detalles
    echo "<p>Insertando 200 Ventas de productos...</p>";
    for ($i = 1; $i <= 200; $i++) {
        $id_cliente = $id_clientes[array_rand($id_clientes)];
        $id_usuario = $id_usuarios[array_rand($id_usuarios)];
        
        $timestamp = time() - rand(0, 31536000); // Último año
        $fecha_venta = date("Y-m-d H:i:s", $timestamp);
        $metodo = $metodos[array_rand($metodos)];
        
        $stmt = $conn->prepare("INSERT INTO venta (id_cliente, id_usuario, fecha_venta, metodo_pago) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $id_cliente, $id_usuario, $fecha_venta, $metodo);
        $stmt->execute();
        $id_venta = $conn->insert_id;
        
        $num_productos = rand(1, 4);
        $subtotal_venta = 0;
        $productos_usados = [];
        for ($j = 0; $j < $num_productos; $j++) {
            $id_producto = $id_productos[array_rand($id_productos)];
            if (in_array($id_producto, $productos_usados)) continue;
            $productos_usados[] = $id_producto;

            $cantidad = rand(1, 3);
            $res = $conn->query("SELECT precio FROM producto WHERE id_producto = $id_producto");
            if($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $precio_unitario = $row['precio'];
                $subtotal = $precio_unitario * $cantidad;
                
                $stmt_det = $conn->prepare("INSERT INTO venta_detalle (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt_det->bind_param("iiidd", $id_venta, $id_producto, $cantidad, $precio_unitario, $subtotal);
                $stmt_det->execute();
                
                $subtotal_venta += $subtotal;
            }
        }
        
        $descuento = rand(0, 20);
        $total = $subtotal_venta - $descuento;
        if ($total < 0) $total = 0;
        
        $conn->query("UPDATE venta SET subtotal = $subtotal_venta, descuento = $descuento, total = $total WHERE id_venta = $id_venta");
    }

    $conn->commit();
    echo "<h3 style='color: green;'>¡Éxito! Se han insertado 200 registros en cada módulo de manera correcta.</h3>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<h3 style='color: red;'>Ocurrió un error: " . $e->getMessage() . "</h3>";
}

echo "</div>";
?>
