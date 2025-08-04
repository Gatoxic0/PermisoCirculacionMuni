# Formulario - Sistema de Gestión de Permisos

## Descripción
Formulario es un sistema web para la gestión de permisos y solicitudes, desarrollado con PHP. El sistema permite a los usuarios crear, revisar y aprobar/rechazar solicitudes, con notificaciones en tiempo real y envío de correos electrónicos.

## Características
- Sistema de autenticación de usuarios
- Creación y gestión de solicitudes de permisos
- Panel de administración para aprobar/rechazar solicitudes
- Notificaciones en tiempo real
- Envío de correos electrónicos automáticos
- Exportación de datos a Excel
- Carga y gestión de documentos
- Interfaz responsiva

## Tecnologías utilizadas
- PHP 
- JavaScript
- MySQL
- PHPMailer para el envío de correos
- Bootstrap para la interfaz de usuario
- AJAX para peticiones asíncronas

## Estructura del proyecto
- **assets/**: Contiene archivos CSS, JavaScript e imágenes para la interfaz de usuario
  - **css/**: Hojas de estilo para la aplicación
  - **js/**: Scripts de JavaScript para funcionalidades dinámicas
  - **images/**: Imágenes utilizadas en la interfaz
- **config/**: Archivos de configuración
  - **config.php**: Configuración general de la aplicación
  - **mail_config.php**: Configuración para el envío de correos
- **controllers/**: Controladores de la aplicación (patrón MVC)
- **core/**: Clases base del framework
  - **Controller.php**: Clase base para controladores
  - **Model.php**: Clase base para modelos
  - **Router.php**: Sistema de enrutamiento
- **includes/**: Scripts PHP auxiliares
  - **mail_sender.php**: Funciones para envío de correos
  - **exportar_excel.php**: Funcionalidad para exportar datos a Excel
  - **notificaciones.php**: Gestión de notificaciones
- **public/**: Archivos públicos accesibles directamente
- **uploads/**: Directorio para almacenar documentos subidos por los usuarios
- **vendor/**: Librerías de terceros (gestionadas por Composer)
- **views/**: Plantillas y vistas de la aplicación

## Requisitos del sistema
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web Apache con mod_rewrite habilitado
- Extensiones PHP: mysqli, mbstring, fileinfo, gd
- Composer (para gestión de dependencias)

## Instalación

1. Clonar o descargar el repositorio en el directorio de su servidor web:
   ```
   git clone [URL_DEL_REPOSITORIO] formulario
   ```

2. Navegar al directorio del proyecto:
   ```
   cd formulario
   ```

3. Instalar dependencias con Composer:
   ```
   composer install
   ```

4. Crear una base de datos MySQL para el proyecto.

5. Importar el archivo de estructura de base de datos (si está disponible en el repositorio).

6. Configurar los parámetros de conexión a la base de datos en `config/config.php`.

7. Configurar los parámetros de correo electrónico en `config/mail_config.php`.

8. Asegurarse de que el directorio `uploads/` tenga permisos de escritura:
   ```
   chmod 755 uploads/
   ```

## Configuración

### Configuración de la base de datos
Editar el archivo `config/config.php` y modificar los siguientes parámetros:

```php
// Ejemplo de configuración
define('DB_HOST', 'localhost');
define('DB_USER', 'usuario_db');
define('DB_PASS', 'contraseña_db');
define('DB_NAME', 'nombre_db');
```

### Configuración de correo electrónico
Editar el archivo `config/mail_config.php` y modificar los siguientes parámetros:

```php
// Ejemplo de configuración para Gmail
$mail_config = [
    'host' => 'smtp.gmail.com',
    'username' => 'tu_correo@gmail.com',
    'password' => 'tu_contraseña_o_clave_de_aplicacion',
    'port' => 587,
    'encryption' => 'tls',
    'from_email' => 'tu_correo@gmail.com',
    'from_name' => 'Sistema de Permisos'
];
```

## Uso

### Para usuarios
1. Acceder al sistema mediante la URL del servidor donde está instalado.
2. Iniciar sesión con las credenciales proporcionadas.
3. En la página principal, seleccionar "Crear nueva solicitud" para generar un nuevo permiso.
4. Completar el formulario con los datos requeridos y adjuntar los documentos necesarios.
5. Enviar la solicitud y esperar la notificación de aprobación o rechazo.

### Para administradores
1. Acceder al sistema e iniciar sesión con credenciales de administrador.
2. En el panel de administración, revisar las solicitudes pendientes.
3. Seleccionar una solicitud para ver sus detalles.
4. Aprobar o rechazar la solicitud según corresponda.
5. Opcionalmente, agregar comentarios o solicitar información adicional.
6. Utilizar la función de exportación a Excel para generar informes.

## Seguridad
- El sistema utiliza hash de contraseñas para almacenar credenciales de forma segura.
- Implementa validación de formularios tanto en el cliente como en el servidor.
- Controla el acceso a rutas y funcionalidades mediante un sistema de roles y permisos.
- Protege contra ataques XSS y CSRF.

## Mantenimiento

### Respaldo de la base de datos
Se recomienda realizar respaldos periódicos de la base de datos:

```
mysqldump -u usuario -p nombre_db > backup_fecha.sql
```

### Actualización
Para actualizar el sistema a una nueva versión:

1. Realizar un respaldo completo del sistema y la base de datos.
2. Descargar la nueva versión.
3. Reemplazar los archivos, manteniendo los archivos de configuración personalizados.
4. Ejecutar `composer update` para actualizar dependencias.
5. Aplicar las migraciones de base de datos si existen.

## Solución de problemas comunes

- **Error de conexión a la base de datos**: Verificar los parámetros en `config/config.php`.
- **Problemas con el envío de correos**: Comprobar la configuración en `config/mail_config.php` y asegurarse de que el servidor permite conexiones SMTP salientes.
- **Error al subir archivos**: Verificar los permisos del directorio `uploads/` y los límites de tamaño de archivo en la configuración de PHP.

## Licencia
Este proyecto está licenciado bajo [especificar licencia]. Consulte el archivo LICENSE para más detalles.

## Contacto
Para soporte técnico o consultas, contacte a [correo_de_contacto].