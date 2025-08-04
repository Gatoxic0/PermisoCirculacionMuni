document.addEventListener('DOMContentLoaded', function() {
    // Verificar el estado de los permisos cada 10 segundos
    setInterval(verificarEstadoPermisos, 10000);
});

function verificarEstadoPermisos() {
    // Obtener todos los IDs de permisos en la tabla
    const rows = document.querySelectorAll('table tbody tr');
    
    rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (!idCell) return;
        
        const permisoId = idCell.textContent.trim();
        
        // Verificar el estado del permiso
        fetch(`includes/verificar-estado-permiso.php?permiso_id=${permisoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exito) {
                    // Crear un evento personalizado para actualizar el estado
                    const event = new CustomEvent('statusUpdate', {
                        detail: {
                            vehiculo_id: permisoId,
                            status: data.estado
                        }
                    });
                    document.dispatchEvent(event);
                }
            })
            .catch(error => console.error('Error al verificar estado:', error));
    });
}