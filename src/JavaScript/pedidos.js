/**
 * Mai Shop - Orders Module JavaScript
 * Handles order management interactions
 */

document.addEventListener('DOMContentLoaded', function () {

    // ===== STATUS CHANGE — intercept Cancelado (3) =====
    const statusSelects = document.querySelectorAll('.status-select');

    statusSelects.forEach(select => {
        // Store the original value so we can revert if cancelled
        select.dataset.originalValue = select.value;

        select.addEventListener('change', function () {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;
            const sel = this;

            if (newStatus === '3') {
                // Cancellation — require a note
                openCancelModal(orderId, sel);
            } else {
                // Other status changes — simple confirm
                MaiModal.confirm({
                    title: 'Cambiar Estado',
                    message: '¿Confirmas cambiar el estado del pedido?',
                    onConfirm: () => updateOrderStatus(orderId, newStatus, sel, ''),
                    onCancel: () => { sel.value = sel.dataset.originalValue; }
                });
            }
        });
    });

    // ===== CANCEL MODAL LOGIC =====
    const cancelModal = document.getElementById('cancelModal');
    const cancelNote = document.getElementById('cancelNote');
    const cancelError = document.getElementById('cancelNoteError');
    const confirmBtn = document.getElementById('cancelModalConfirm');
    const abortBtn = document.getElementById('cancelModalAbort');
    const closeBtn = document.getElementById('cancelModalClose');

    let _cancelOrderId = null;
    let _cancelSelect = null;

    window.openCancelModal = function (orderId, selectEl) {
        _cancelOrderId = orderId;
        _cancelSelect = selectEl;
        cancelNote.value = '';
        cancelError.style.display = 'none';
        cancelModal.style.display = 'flex';
        cancelNote.focus();
    };

    function closeCancelModal(revert) {
        cancelModal.style.display = 'none';
        if (revert && _cancelSelect) {
            _cancelSelect.value = _cancelSelect.dataset.originalValue;
        }
    }

    confirmBtn.addEventListener('click', function () {
        const note = cancelNote.value.trim();
        if (!note) {
            cancelError.style.display = 'block';
            cancelNote.style.borderColor = '#e53e3e';
            return;
        }
        closeCancelModal(false);
        updateOrderStatus(_cancelOrderId, '3', _cancelSelect, note);
    });

    abortBtn.addEventListener('click', () => closeCancelModal(true));
    closeBtn.addEventListener('click', () => closeCancelModal(true));
    cancelModal.addEventListener('click', e => { if (e.target === cancelModal) closeCancelModal(true); });
    cancelNote.addEventListener('input', () => {
        cancelError.style.display = 'none';
        cancelNote.style.borderColor = '#e2e8f0';
    });

    // ===== VER NOTA DE CANCELACIÓN =====
    const notaModal = document.getElementById('notaModal');
    const notaModalText = document.getElementById('notaModalText');
    const notaModalClose = document.getElementById('notaModalClose');
    const notaModalOk = document.getElementById('notaModalOk');

    document.querySelectorAll('.btn-ver-nota').forEach(btn => {
        btn.addEventListener('click', function () {
            const nota = this.dataset.nota || 'Sin motivo registrado.';
            notaModalText.textContent = nota;
            notaModal.style.display = 'flex';
        });
    });

    if (notaModalClose) notaModalClose.addEventListener('click', () => { notaModal.style.display = 'none'; });
    if (notaModalOk) notaModalOk.addEventListener('click', () => { notaModal.style.display = 'none'; });
    if (notaModal) notaModal.addEventListener('click', e => { if (e.target === notaModal) notaModal.style.display = 'none'; });

    // ===== DELETE BUTTONS =====
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const orderId = this.dataset.orderId;
            const orderNumber = this.dataset.orderNumber;
            MaiModal.confirm({
                title: 'Eliminar Pedido',
                message: `¿Estás seguro de eliminar el pedido ${orderNumber}? Esta acción no se puede deshacer.`,
                onConfirm: () => deleteOrder(orderId)
            });
        });
    });

    // ===== FILTERS =====
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', () => window.location.href = 'pedidos.php');

    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    if (searchInput && filterForm) {
        let timeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) filterForm.submit();
            }, 500);
        });
    }
});


// ===== UPDATE ORDER STATUS =====
function updateOrderStatus(orderId, newStatus, selectElement, notaCancelacion) {
    fetch('cambiar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_id: orderId,
            status: newStatus,
            nota_cancelacion: notaCancelacion || ''
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Error al actualizar el estado', 'error');
                if (selectElement) selectElement.value = selectElement.dataset.originalValue || '0';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
            if (selectElement) selectElement.value = selectElement.dataset.originalValue || '0';
        });
}

// ===== DELETE ORDER =====
function deleteOrder(orderId) {
    fetch('acciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'delete',
            order_id: orderId
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                // Reload page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Error al eliminar el pedido', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// ===== NOTIFICATION SYSTEM =====
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#20ba5a' : '#ff6b9d'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
