class StatusHandler {
    constructor(wsHandler) {
        this.wsHandler = wsHandler;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('statusUpdate', (e) => this.handleStatusUpdate(e.detail));
    }

    handleStatusUpdate(data) {
        // Buscar la fila por el ID del vehículo
        const rows = document.querySelectorAll('tr');
        let row = null;
        
        for (let i = 0; i < rows.length; i++) {
            const firstCell = rows[i].querySelector('td:first-child');
            if (firstCell && firstCell.textContent.trim() === data.vehiculo_id.toString()) {
                row = rows[i];
                break;
            }
        }
        
        if (!row) return;

        const statusCell = row.querySelector('td:nth-child(5)');
        const oldBadge = statusCell.querySelector('.badge');
        
        let newBadgeClass = '';
        let newText = '';
        
        switch(data.status) {
            case 'aprobada':
                newBadgeClass = 'bg-success';
                newText = 'Aprobada';
                break;
            case 'rechazada':
                newBadgeClass = 'bg-danger';
                newText = 'Rechazada';
                break;
            default:
                newBadgeClass = 'bg-warning text-dark';
                newText = 'Pendiente';
        }

        oldBadge.className = `badge ${newBadgeClass} badge-transition`;
        oldBadge.textContent = newText;
        
        row.classList.add('row-highlight');
        setTimeout(() => row.classList.remove('row-highlight'), 1000);
    }
}