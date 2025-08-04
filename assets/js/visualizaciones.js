class VisualizacionesHandler {
    constructor() {
        this.intervalo = 5000; // 5 segundos
        this.inicializar();
    }
    
    inicializar() {
        // Verificar visualizaciones al cargar la página
        this.verificarVisualizaciones();
        
        // Configurar verificación periódica
        setInterval(() => this.verificarVisualizaciones(), this.intervalo);
    }
    
    verificarVisualizaciones() {
        fetch('includes/obtener_visualizaciones.php')
            .then(response => response.json())
            .then(data => {
                if (data.exito) {
                    this.actualizarInterfaz(data.visualizaciones);
                }
            })
            .catch(error => console.error('Error al verificar visualizaciones:', error));
    }
    
    actualizarInterfaz(visualizaciones) {
        // Limpiar todos los indicadores
        document.querySelectorAll('.visualizacion-badge').forEach(badge => {
            badge.textContent = '';
            badge.classList.remove('active');
        });
        
        // Actualizar los indicadores de visualización
        visualizaciones.forEach(vis => {
            const badge = document.getElementById(`visualizacion-${vis.permiso_id}`);
            if (badge) {
                badge.textContent = `👁️ ${vis.total}`;
                badge.classList.add('active');
                
                // Mostrar tooltip con los nombres de los usuarios
                badge.setAttribute('title', `Usuarios visualizando: ${vis.usuarios}`);
                
                // Si hay más de un usuario viendo, mostrar alerta
                const btnDetalles = badge.closest('.btn-info');
                if (vis.total > 1 && btnDetalles) {
                    btnDetalles.classList.add('multiple-viewers');
                } else if (btnDetalles) {
                    btnDetalles.classList.remove('multiple-viewers');
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    new VisualizacionesHandler();
});