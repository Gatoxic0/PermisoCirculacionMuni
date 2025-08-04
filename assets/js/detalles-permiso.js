document.addEventListener('DOMContentLoaded', function() {
    // Manejar el botón de confirmación de rechazo
    const confirmRejectBtn = document.getElementById('confirmReject');
    if (confirmRejectBtn) {
        confirmRejectBtn.addEventListener('click', function() {
            const rejectForm = document.getElementById('rejectForm');
            if (rejectForm) {
                // Validar el formulario
                if (rejectForm.checkValidity()) {
                    // Enviar el formulario
                    rejectForm.submit();
                } else {
                    // Mostrar errores de validación
                    rejectForm.reportValidity();
                }
            }
        });
    }

    // Función para actualizar el mensaje de rechazo con un motivo específico
    window.updateRejectionMessage = function(motivo) {
        const emailMessage = document.getElementById('email_message');
        if (emailMessage) {
            const currentText = emailMessage.value;
            emailMessage.value = currentText.replace('[Por favor, indique aquí el motivo específico del rechazo]', motivo);
        }
    };
});