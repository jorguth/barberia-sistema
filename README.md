# Barbería PO - Sistema de Gestión

Este es un sistema de gestión para una barbería, desarrollado en PHP y MySQL.

## Requisitos Previos

-   **PHP 7.3+** (ya incluido en AppServ o XAMPP).
-   **MySQL/MariaDB**.

## Guía de Configuración Rápida

### 1. Preparar la Base de Datos
-   Abre **phpMyAdmin** (normalmente en `http://localhost/phpMyAdmin`).
-   Crea una base de datos llamada `barberiadb`.
-   Importa el archivo SQL ubicado en `database/base de datos barberia.sql`.

### 2. Configurar la Conexión
-   Abre el archivo `config.php`.
-   Asegúrate de que los valores de `DB_USERNAME` y `DB_PASSWORD` coincidan con tu configuración de MySQL (en AppServ el usuario suele ser `root` y la contraseña la que elegiste al instalar).

### 3. Ejecutar el Proyecto (Más Fácil)
-   Haz doble clic en el archivo `run.bat`.
-   Esto iniciará un servidor local automáticamente.
-   Abre tu navegador y ve a: **[http://localhost:8000](http://localhost:8000)**.

---
**Nota:** El script `run.bat` usa el servidor integrado de PHP, lo cual es ideal para desarrollo rápido sin tener que configurar Apache manualmente.
