function handleAction(action, requiresNote = false) {
    const form = document.getElementById('actionForm');
    const formAction = document.getElementById('formAction');
    const formNotas = document.getElementById('formNotas');

    const executeAction = (note = '') => {
        formAction.value = action;
        formNotas.value = note;
        form.submit();
    };

    if (requiresNote) {
        let title = 'Motivo Requerido';
        let message = 'Por favor, ingresa el motivo para realizar esta acción:';

        if (action === 'cancelar_pedido') title = 'Cancelar Pedido';
        if (action === 'rechazar_pago') title = 'Rechazar Pago';

        MaiModal.prompt({
            title: title,
            message: message,
            label: 'Motivo:',
            placeholder: 'Escribe el motivo aquí...',
            onConfirm: (note) => {
                if (!note || note.trim() === '') {
                    MaiModal.alert({
                        title: 'Campo Requerido',
                        message: 'El motivo es obligatorio para continuar.',
                        type: 'danger'
                    });
                    return;
                }
                executeAction(note);
            }
        });
    } else {
        let title = 'Confirmar Acción';
        let message = '¿Estás seguro de realizar esta acción?';

        if (action === 'mandar_produccion') {
            title = 'Mandar a Producción';
            message = '¿Confirmas que deseas enviar este pedido a producción?';
        }
        if (action === 'aprobar_pago') {
            title = 'Aprobar Pago';
            message = '¿Confirmas que el pago es correcto y deseas aprobarlo?';
        }

        MaiModal.confirm({
            title: title,
            message: message,
            onConfirm: () => executeAction()
        });
    }
}
