<?php include 'header.php'; ?>

<div class="container">
    <h2>Сканиране на продукти</h2>
    <!-- Поле за скенера -->
    <input type="text" id="barcodeInput" autofocus placeholder="Сканирай баркод тук..." class="form-control">
    
    <div id="scanResult" style="margin-top: 20px;"></div>
</div>

<script>
document.getElementById('barcodeInput').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        let barcode = this.value;
        // Изпращаме заявка към твоето api.php
        fetch('api.php?action=scan_barcode', {
            method: 'POST',
            body: new URLSearchParams({'barcode': barcode})
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('scanResult').innerHTML = '<div class="alert alert-success">Намерен: ' + data.product.name + '</div>';
            } else {
                document.getElementById('scanResult').innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
            this.value = ''; // Изчистваме полето
        });
    }
});
</script>

<?php include 'footer.php'; ?>