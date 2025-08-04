class ActualizadorRegistros {
    constructor(intervalo = 5000) {
        this.intervalo = intervalo;
        this.ultimoId = this.obtenerUltimoId();
        this.iniciar();
    }
    
    obtenerUltimoId() {
        // Buscar el último ID en la tabla
        const filas = document.querySelectorAll('table tbody tr');
        if (filas.length === 0) return 0;
        
        let ultimoId = 0;
        filas.forEach(fila => {
            // Obtener el ID de la primera celda en lugar de data-permit-id
            const idCelda = fila.querySelector('td:first-child');
            const id = idCelda ? parseInt(idCelda.textContent || 0) : 0;
            if (id > ultimoId) ultimoId = id;
        });
        
        return ultimoId;
    }
    
    iniciar() {
        // Iniciar la verificación periódica
        this.verificarNuevosRegistros();
        setInterval(() => this.verificarNuevosRegistros(), this.intervalo);
    }
    
    verificarNuevosRegistros() {
        fetch(`includes/obtener_nuevos_registros.php?ultimo_id=${this.ultimoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exito && data.registros.length > 0) {
                    this.agregarNuevosRegistros(data.registros);
                }
            })
            .catch(error => console.error('Error al verificar nuevos registros:', error));
    }
    
    agregarNuevosRegistros(registros) {
        const tbody = document.querySelector('table tbody');
        if (!tbody) return;
        
        registros.forEach(registro => {
            // Actualizar el último ID conocido
            if (registro.vehiculo_id > this.ultimoId) {
                this.ultimoId = registro.vehiculo_id;
            }
            
            // Crear una nueva fila (eliminado data-permit-id)
            const fila = document.createElement('tr');
            fila.className = 'fila-nueva';
            
            // Formatear la fecha
            const fecha = new Date(registro.fecha_solicitud);
            const fechaFormateada = fecha.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).replace(/\//g, '-');
            
            // Determinar la clase del badge según el estado
            let badgeClass = '';
            let estadoTexto = registro.estado ? registro.estado.toLowerCase() : 'pendiente';
            
            switch(estadoTexto) {
                case 'aprobada':
                    badgeClass = 'bg-success';
                    break;
                case 'rechazada':
                    badgeClass = 'bg-danger';
                    break;
                default:
                    badgeClass = 'bg-warning text-dark';
                    estadoTexto = 'pendiente';
            }
            
            // Construir el HTML de la fila
            fila.innerHTML = `
                <td>${registro.vehiculo_id}</td>
                <td>${registro.placa_patente}</td>
                <td>${registro.rut || ''}</td>
                <td>${registro.nombre} ${registro.apellido_paterno} ${registro.apellido_materno || ''}</td>
                <td class="estado-cell">
                    <span class="badge ${badgeClass}">${estadoTexto.charAt(0).toUpperCase() + estadoTexto.slice(1)}</span>
                </td>
                <td>${fechaFormateada}</td>
                <td class="action-cell">
                    <a href="ver_detalles.php?id=${registro.vehiculo_id}" class="btn btn-info btn-sm">Ver Detalles</a>
                </td>
            `;
            
            // Agregar la fila al inicio de la tabla
            tbody.insertBefore(fila, tbody.firstChild);
            
            // Aplicar animación de entrada
            setTimeout(() => {
                fila.classList.remove('fila-nueva');
                fila.classList.add('fila-destacada');
                setTimeout(() => fila.classList.remove('fila-destacada'), 2000);
            }, 100);
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new ActualizadorRegistros();
});