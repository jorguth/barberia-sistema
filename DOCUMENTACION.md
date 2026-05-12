# Documentación del Sistema: Barbería PO

Este documento proporciona una visión detallada de la arquitectura, funcionalidades y configuración del sistema de gestión para la barbería.

## 📌 Descripción General
El sistema **Barbería PO** es una aplicación web diseñada para centralizar la operación de una barbería. Permite la gestión de citas, ventas, servicios, productos, clientes y usuarios, integrando reportes automáticos y validaciones de seguridad avanzadas.

---

## 🚀 Funcionalidades Principales

### 📅 Gestión de Citas (`citas.php`)
*   **Calendario Semanal:** Visualización interactiva de citas por día y hora.
*   **Agendamiento:** Formulario para registro de nuevas citas vinculando clientes y múltiples servicios.
*   **Validaciones de Negocio:**
    *   Control de horarios (8:00 AM - 8:00 PM).
    *   Prevención de citas duplicadas o empalmadas.
    *   Restricción de fechas pasadas (con excepción para administradores).
*   **Flujo de Estados:** Transición entre estados (*Pendiente*, *Completada*, *Cancelada*). Al completar, se solicita el método de pago y se procesa el cobro.

### 💰 Ventas y Facturación (`ventas.php`)
*   **Registro Automático:** Las citas completadas generan una venta automáticamente.
*   **Ventas Directas:** Posibilidad de registrar ventas de productos o servicios sin cita previa.
*   **Métodos de Pago:** Soporte para Efectivo, Tarjeta y Transferencia.

### 📊 Reportes y Dashboard (`reportes.php`, `dashboard.php`)
*   **Métricas en Tiempo Real:** Visualización de ingresos diarios, semanales y mensuales.
*   **Gráficos Interactivos:** Distribución de ventas por método de pago y servicios más solicitados.

### 👥 Administración de Usuarios y Clientes (`usuarios.php`, `clientes.php`)
*   **Roles y Permisos:**
    *   **Administrador:** Acceso total, gestión financiera y permisos para registrar citas pasadas.
    *   **Barbero:** Gestión de sus propias citas y atención a clientes.
    *   **Cliente:** Vista limitada para agendar sus propias citas y consultar su historial.

---

## 🛠️ Stack Técnico
*   **Backend:** PHP 7.3+ (Uso de MySQLi con Prepared Statements para seguridad).
*   **Base de Datos:** MySQL / MariaDB (Incluye Triggers para automatización de inventario y ventas).
*   **Frontend:** HTML5, CSS3 (Diseño personalizado con estética moderna), JavaScript (Vanilla JS para modales y dinamismo).

---

## 🔒 Validaciones y Seguridad
El sistema implementa múltiples capas de validación:
1.  **Protección SQLi:** Todas las consultas a la base de datos utilizan sentencias preparadas.
2.  **Validación de Lógica en Servidor:**
    *   No se permiten citas fuera de horario comercial.
    *   Solo administradores pueden "olvidar" y registrar citas pasadas.
    *   Validación de estados para evitar duplicidad de cobros.
3.  **Control de Acceso:** Uso de sesiones (`auth.php`) para restringir el acceso según el rol del usuario.

---

## ⚙️ Configuración e Instalación

### 1. Base de Datos
1.  Crear una base de datos en MySQL llamada `barberiadb`.
2.  Importar el archivo: `database/base de datos barberia.sql`.
3.  (Opcional) Importar `database/triggers_vistas.sql` para funcionalidades avanzadas.

### 2. Conexión
Editar el archivo `config.php` o `conexion.php` con las credenciales de tu servidor local:
```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'tu_contraseña');
define('DB_NAME', 'barberiadb');
```

### 3. Ejecución
Para un desarrollo rápido, puedes ejecutar el archivo `run.bat` o configurar un servidor Apache (XAMPP/AppServ) apuntando al directorio raíz.

---

## 📂 Estructura de Archivos
*   `/css`: Hojas de estilo personalizadas.
*   `/js`: Scripts para interacciones de interfaz.
*   `/database`: Scripts SQL de migración y estructura.
*   `/inc`: Componentes reutilizables (Sidebar, Header, Helpers).
*   `citas.php`: Módulo central de agenda.
*   `ventas.php`: Módulo de caja y facturación.
*   `dashboard.php`: Panel de control principal.
