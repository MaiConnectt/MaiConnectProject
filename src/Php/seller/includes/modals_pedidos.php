<!-- Modal Subir Comprobante -->
<div id="uploadModal" class="modal"
    style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div
        style="background:white; margin:10% auto; padding:2rem; width:90%; max-width:500px; border-radius:16px; position:relative;">
        <span onclick="closeModal()"
            style="position:absolute; right:1.5rem; top:1rem; cursor:pointer; font-size:1.5rem;">&times;</span>
        <h2 style="margin-bottom:1rem; font-family: 'Playfair Display', serif;">Subir Comprobante</h2>
        <form action="acciones.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="subir_pago">
            <input type="hidden" name="id_pedido" id="modal_id_pedido">
            <div style="margin-bottom:1rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Total del Pedido:</label>
                <input type="text" id="modal_total_display" class="form-input" readonly value="$0">
                <input type="hidden" name="monto" id="modal_total_val">
            </div>
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Seleccionar Imagen:</label>
                <input type="file" name="comprobante" class="form-input" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
                <i class="fas fa-cloud-upload-alt"></i> Confirmar Envío
            </button>
        </form>
    </div>
</div>

<!-- Modal: Ver nota de cancelación -->
<div id="notaCancelModal"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div
        style="background:#fff; border-radius:16px; padding:2rem; max-width:440px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.2); position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 id="notaCancelTitle"
                style="font-family:'Playfair Display',serif; color:#c44569; margin:0; font-size:1.1rem;"></h3>
            <button onclick="cerrarNotaModal()"
                style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999; line-height:1;">&times;</button>
        </div>
        <div id="notaCancelText"
            style="background:#fff5f8; border-left:4px solid #c44569; border-radius:8px; padding:1rem; color:#555; font-size:0.95rem; line-height:1.6;">
        </div>
        <div style="margin-top:1.25rem; text-align:right;">
            <button onclick="cerrarNotaModal()"
                style="padding:0.6rem 1.4rem; border:none; border-radius:8px; background:linear-gradient(135deg,#ff6b9d,#c44569); color:#fff; cursor:pointer; font-weight:600;">Cerrar</button>
        </div>
    </div>
</div>

<script>
    const uploadForm = document.querySelector('#uploadModal form');
    const fileInput = uploadForm?.querySelector('input[name="comprobante"]');
    const MAX_SIZE_MB = 2;

    function openUploadModal(id, total) {
        document.getElementById('modal_id_pedido').value = id;
        document.getElementById('modal_total_display').value = '$' + total.toLocaleString('es-CO');
        document.getElementById('modal_total_val').value = total;
        document.getElementById('uploadModal').style.display = 'block';
        if(fileInput) fileInput.value = ''; // Reset file input
    }

    if(uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            if (fileInput && fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > MAX_SIZE_MB) {
                    e.preventDefault();
                    MaiModal.alert({
                        title: 'Archivo Demasiado Grande',
                        message: `El comprobante no debe pesar más de ${MAX_SIZE_MB}MB. Por favor, reduce el tamaño de la imagen o toma una captura de pantalla más liviana.`,
                        type: 'danger'
                    });
                }
            }
        });
    }

    function closeModal() {
        document.getElementById('uploadModal').style.display = 'none';
    }

    function markAsCompleted(id) {
        MaiModal.confirm({
            title: 'Finalizar Pedido',
            message: '¿Estás seguro de marcar el pedido #' + id + ' como completado? Esta acción finalizará la comisión.',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'acciones.php';

                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'completar_pedido';
                form.appendChild(inputAction);

                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_pedido';
                inputId.value = id;
                form.appendChild(inputId);

                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        if (event.target == document.getElementById('uploadModal')) {
            closeModal();
        }
    }

    // ── Motivo de cancelación ──────────────────────────────────────────
    function verNotaCancelacion(id, nota) {
        document.getElementById('notaCancelTitle').textContent = '\uD83D\uDCC4 Motivo de Cancelaci\u00f3n \u2014 Pedido #' + String(id).padStart(4, '0');
        document.getElementById('notaCancelText').textContent = nota;
        const modal = document.getElementById('notaCancelModal');
        modal.style.display = 'flex';
    }

    function cerrarNotaModal() {
        document.getElementById('notaCancelModal').style.display = 'none';
    }

    // Cerrar al hacer clic fuera
    const cancelModal = document.getElementById('notaCancelModal');
    if(cancelModal) {
        cancelModal.addEventListener('click', function (e) {
            if (e.target === this) cerrarNotaModal();
        });
    }
</script>
