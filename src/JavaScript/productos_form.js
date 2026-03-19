document.addEventListener('DOMContentLoaded', function() {
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('acciones.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        MaiModal.alert({
                            title: '¡Producto Creado!',
                            message: data.message,
                            type: 'success',
                            onConfirm: () => {
                                window.location.href = 'productos.php';
                            }
                        });
                    } else {
                        MaiModal.alert({
                            title: 'Error',
                            message: data.message,
                            type: 'danger'
                        });
                    }
                })
                .catch(err => {
                    MaiModal.alert({
                        title: 'Error Técnico',
                        message: err.message,
                        type: 'danger'
                    });
                });
        });
    }
});
