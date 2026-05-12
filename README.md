# 💈 Sistema de Gestión: Barbería PO

¡Bienvenido al sistema de gestión integral para barberías! Esta aplicación está diseñada para facilitar la administración diaria, desde la agenda de citas hasta el control financiero y reportes.

## 📋 Resumen del Proyecto
El sistema permite a los administradores y barberos gestionar sus agendas de manera eficiente, registrar ventas automáticamente al completar servicios y obtener métricas valiosas sobre el rendimiento del negocio.

---

## 📚 Documentación Detallada
Para una guía técnica sobre el funcionamiento de los módulos, roles y validaciones, consulta:
👉 **[DOCUMENTACION.md](./DOCUMENTACION.md)**

Para aprender a usar el sistema paso a paso (Manual de Usuario), consulta:
👉 **[GUIA_USUARIO.md](./GUIA_USUARIO.md)**

---

## 🛠️ Instalación Rápida

### 1. Base de Datos
- Crea una base de datos llamada `barberiadb` en tu servidor MySQL.
- Importa el archivo SQL: `database/base de datos barberia.sql`.

### 2. Configuración
- Configura tus credenciales en `config.php` o `conexion.php`.

### 3. Ejecución
Si tienes PHP instalado en tu PATH, simplemente ejecuta:
```bash
run.bat
```
Luego accede a: **[http://localhost:8000](http://localhost:8000)**.

---

## ✨ Características Destacadas
- **Agenda Interactiva:** Calendario semanal para gestión de citas.
- **Ventas Automatizadas:** Generación de facturas al finalizar servicios.
- **Seguridad:** Validaciones de servidor contra inyecciones y errores de lógica.
- **Reportes:** Dashboard con gráficos de ingresos y servicios.

