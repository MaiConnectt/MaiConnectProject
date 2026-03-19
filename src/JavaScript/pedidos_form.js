document.addEventListener('DOMContentLoaded', function() {
    // Se espera que window.ProductsData contenga el productIndex inicial pre-cargado desde PHP
    let productIndex = window.ProductsData ? window.ProductsData.initialIndex : 0;
    
    const addProductBtn = document.getElementById('addProductBtn');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function () {
            const tbody = document.getElementById('productsBody');
            const row = document.createElement('tr');
            row.className = 'product-row';
            row.innerHTML = `
                <td><input type="text" name="products[${productIndex}][name]" class="product-name" placeholder="Nombre del producto" required></td>
                <td><input type="number" name="products[${productIndex}][quantity]" class="product-quantity" min="1" value="1" required></td>
                <td><input type="number" name="products[${productIndex}][price]" class="product-price" min="0" step="1000" placeholder="0" required></td>
                <td><input type="text" class="product-subtotal" readonly value="$0"></td>
                <td><button type="button" class="btn-remove-product"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
            productIndex++;
            attachProductListeners(row);
        });
    }

    function attachProductListeners(row) {
        const quantityInput = row.querySelector('.product-quantity');
        const priceInput = row.querySelector('.product-price');
        const subtotalInput = row.querySelector('.product-subtotal');
        const removeBtn = row.querySelector('.btn-remove-product');

        function updateSubtotal() {
            const quantity = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const subtotal = quantity * price;
            subtotalInput.value = '$' + subtotal.toLocaleString('es-CO');
            updateTotal();
        }

        if(quantityInput) quantityInput.addEventListener('input', updateSubtotal);
        if(priceInput) priceInput.addEventListener('input', updateSubtotal);

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                row.remove();
                updateTotal();
            });
        }
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.product-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('.product-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.product-price').value) || 0;
            total += quantity * price;
        });
        const totalAmount = document.getElementById('totalAmount');
        if (totalAmount) {
            totalAmount.textContent = '$' + total.toLocaleString('es-CO');
        }
    }

    // Attach listeners to existing rows if there are any
    document.querySelectorAll('.product-row').forEach(attachProductListeners);
});
