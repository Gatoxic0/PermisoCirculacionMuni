class SistemaNotificaciones {
    constructor(options = {}) {
        this.options = {
            intervaloActualizacion: options.intervaloActualizacion || 30000, // 30 segundos
            usuarioId: options.usuarioId || 1, // En un sistema real, esto vendría de la sesión
            contenedorNotificaciones: options.contenedorNotificaciones || '#notificaciones-dropdown',
            contadorNotificaciones: options.contadorNotificaciones || '#contador-notificaciones',
            maxNotificaciones: options.maxNotificaciones || 5
        };
        
        this.inicializar();
    }
    
    inicializar() {
        // Cargar notificaciones iniciales
        this.cargarNotificaciones();
        
        // Configurar actualización periódica
        setInterval(() => this.cargarNotificaciones(), this.options.intervaloActualizacion);
        
        // Configurar eventos para marcar como leída
        document.addEventListener('click', (e) => {
            if (e.target.matches('.marcar-leida')) {
                e.preventDefault();
                const notificacionId = e.target.dataset.id;
                this.marcarComoLeida(notificacionId);
            }
        });
        
        // Agregar manejo del clic en el icono de notificaciones
        const notificationBell = document.querySelector('.notification-bell');
        const notificationsDropdown = document.querySelector(this.options.contenedorNotificaciones);
        
        if (notificationBell && notificationsDropdown) {
            notificationBell.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                notificationsDropdown.classList.toggle('show');
            });
            
            // Cerrar el dropdown cuando se hace clic fuera de él
            document.addEventListener('click', (e) => {
                if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                    notificationsDropdown.classList.remove('show');
                }
            });
        }
    }
    
    cargarNotificaciones() {
        fetch(`includes/obtener_notificaciones.php?usuario_id=${this.options.usuarioId}&limite=${this.options.maxNotificaciones}`)
            .then(response => response.json())
            .then(data => {
                if (data.exito) {
                    this.actualizarInterfaz(data.notificaciones, data.total_no_leidas);
                }
            })
            .catch(error => console.error('Error al cargar notificaciones:', error));
    }
    
    marcarComoLeida(notificacionId) {
        fetch('includes/marcar_notificacion_leida.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notificacion_id: notificacionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                // Recargar notificaciones para actualizar la interfaz
                this.cargarNotificaciones();
            }
        })
        .catch(error => console.error('Error al marcar notificación como leída:', error));
    }
    
    actualizarInterfaz(notificaciones, totalNoLeidas) {
        // Actualizar contador
        const contador = document.querySelector(this.options.contadorNotificaciones);
        if (contador) {
            contador.textContent = totalNoLeidas;
            contador.style.display = totalNoLeidas > 0 ? 'inline-block' : 'none';
        }
        
        // Actualizar lista de notificaciones
        const contenedor = document.querySelector(this.options.contenedorNotificaciones);
        if (contenedor) {
            // Limpiar contenedor
            contenedor.innerHTML = '';
            
            if (notificaciones.length === 0) {
                contenedor.innerHTML = '<div class="dropdown-item text-center">No hay notificaciones</div>';
                return;
            }
            
            // Agregar notificaciones
            notificaciones.forEach(notificacion => {
                const item = document.createElement('div');
                item.className = `dropdown-item notification-item ${notificacion.leida ? '' : 'unread'}`;
                
                const fecha = new Date(notificacion.fecha_creacion);
                const fechaFormateada = fecha.toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                item.innerHTML = `
                    <div class="notification-content">
                        <div class="notification-type ${notificacion.tipo}">
                            <i class="bi bi-${this.getIconForType(notificacion.tipo)}"></i>
                        </div>
                        <div class="notification-details">
                            <div class="notification-message">${notificacion.mensaje}</div>
                            <div class="notification-meta">
                                <span class="notification-time">${fechaFormateada}</span>
                                ${!notificacion.leida ? `<a href="#" class="marcar-leida" data-id="${notificacion.id}">Marcar como leída</a>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                
                contenedor.appendChild(item);
            });
            // Agregar enlace para ver todas
            const verTodas = document.createElement('div');
            verTodas.className = 'dropdown-item text-center';
            verTodas.innerHTML = '<a href="notificaciones.php">Ver todas las notificaciones</a>';
            contenedor.appendChild(verTodas);
        }
    }
    
    getIconForType(tipo) {
        switch (tipo) {
            case 'success':
                return 'check-circle-fill';
            case 'danger':
                return 'exclamation-circle-fill';
            case 'warning':
                return 'exclamation-triangle-fill';
            case 'info':
            default:
                return 'info-circle-fill';
        }
    }
    
    // Método para marcar todas las notificaciones como leídas
    marcarTodasComoLeidas() {
        fetch('includes/marcar_todas_leidas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ usuario_id: this.options.usuarioId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                this.cargarNotificaciones();
            }
        })
        .catch(error => console.error('Error al marcar todas las notificaciones como leídas:', error));
    }
}

// Inicializar el sistema de notificaciones cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el ID de usuario del atributo data-user-id del body
    const usuarioId = document.body.dataset.userId || 1;
    
    const sistemaNotificaciones = new SistemaNotificaciones({
        usuarioId: usuarioId,
        intervaloActualizacion: 30000 // Actualizar cada 30 segundos
    });
    
    // Agregar evento para el botón de marcar todas como leídas si existe
    const btnMarcarTodas = document.getElementById('marcar-todas-leidas');
    if (btnMarcarTodas) {
        btnMarcarTodas.addEventListener('click', (e) => {
            e.preventDefault();
            sistemaNotificaciones.marcarTodasComoLeidas();
        });
    }
});