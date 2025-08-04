/**
 * Sistema de actualización de estados en tiempo real
 * Este script consulta periódicamente al servidor para detectar cambios
 * en los estados de los permisos y actualiza la interfaz sin recargar la página.
 */
class EstadoTiempoReal {
    constructor(intervalo = 5000) {
        this.intervalo = intervalo;
        this.init();
        console.log("Sistema de actualización de estados en tiempo real iniciado");
    }

    init() {
        // Iniciar verificación periódica
        this.verificarCambiosEstado();
        setInterval(() => this.verificarCambiosEstado(), this.intervalo);
    }
    
    verificarCambiosEstado() {
        fetch('includes/verificar_cambios_estado.php')
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    console.log("Cambios de estado detectados:", data);
                    data.forEach(cambio => {
                        this.actualizarEstadoEnTabla(cambio);
                    });
                }
            })
            .catch(error => console.error('Error al verificar cambios de estado:', error));
    }
    
    actualizarEstadoEnTabla(data) {
        // Buscar la fila por el ID del vehículo
        const rows = document.querySelectorAll('tbody tr');
        let row = null;
        
        for (let i = 0; i < rows.length; i++) {
            const firstCell = rows[i].querySelector('td:first-child');
            if (firstCell && firstCell.textContent.trim() === data.vehiculo_id.toString()) {
                row = rows[i];
                break;
            }
        }
        
        if (!row) return;

        const statusCell = row.querySelector('.estado-cell');
        const oldBadge = statusCell.querySelector('.badge');
        
        if (!oldBadge) return;
        
        let newBadgeClass = '';
        let newText = '';
        
        switch(data.estado) {
            case 'aprobada':
                newBadgeClass = 'bg-success';
                newText = 'Aprobada';
                break;
            case 'rechazada':
                newBadgeClass = 'bg-danger';
                newText = 'Rechazada';
                break;
            default:
                newBadgeClass = 'bg-warning';
                newText = 'Pendiente';
        }

        // Actualizar el badge solo si el estado ha cambiado
        if (oldBadge.textContent.trim().toLowerCase() !== newText.toLowerCase()) {
            // Crear nuevo badge con animación
            const newBadge = document.createElement('span');
            newBadge.className = `badge ${newBadgeClass} badge-transition status-change`;
            newBadge.textContent = newText;
            
            // Reemplazar el badge antiguo
            statusCell.replaceChild(newBadge, oldBadge);
            
            // Añadir efecto visual para destacar el cambio en la fila
            row.classList.add('row-highlight');
            setTimeout(() => row.classList.remove('row-highlight'), 1000);
            
            console.log(`Estado actualizado: Permiso ${data.vehiculo_id} ahora está ${newText}`);
        }
    }
}

// Inicializar el sistema cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en una página con tabla de permisos
    if (document.querySelector('table tbody')) {
        window.estadoTiempoReal = new EstadoTiempoReal(5000); // Verificar cada 3 segundos
    }
});