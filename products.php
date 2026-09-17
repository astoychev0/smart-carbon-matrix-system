<?php
include 'header.php';
?>

<div class="panel-header-row">
    <h2>📦 Управление на Продукти</h2>
    <div class="hud-counter" id="productCount">Общо продукти: 0</div>
</div>

<!-- Формуляр за добавяне на продукт -->
<div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
    <h3 style="font-size: 1rem; color: var(--primary-blue); font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        ➕ Добави Нов Продукт
    </h3>
    
    <form id="addProductForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end;">
        <div class="input-group" style="margin-bottom: 0;">
            <label>Баркод / Код *</label>
            <input type="text" id="prodBarcode" placeholder="напр. PRD-9921" required>
        </div>

        <div class="input-group" style="margin-bottom: 0;">
            <label>Наименование *</label>
            <input type="text" id="prodName" placeholder="напр. Протетичен компонент" required>
        </div>

        <div class="input-group" style="margin-bottom: 0;">
            <label>Наличност</label>
            <input type="number" id="prodStock" value="1" min="0" placeholder="напр. 10">
        </div>

        <div>
            <button type="submit" class="btn-primary-action" style="height: 46px; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%;">
                ➕ Запиши Продукт
            </button>
        </div>
    </form>
</div>

<!-- Таблица с продукти -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
    <h3 style="font-size: 1.05rem; color: var(--text-main); font-weight: 800;" id="tableTitle">Списък с Продукти</h3>
    <div style="width: 250px;">
        <input type="text" id="searchProduct" placeholder="🔍 Търси продукт..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; outline: none;">
    </div>
</div>

<div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
        <thead>
            <tr style="background: #f1f5f9; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">
                <th style="padding: 12px 15px;">ID</th>
                <th style="padding: 12px 15px;">Баркод / Код</th>
                <th style="padding: 12px 15px;">Наименование</th>
                <th style="padding: 12px 15px;">Наличност</th>
                <th style="padding: 12px 15px; text-align: right;">Действия</th>
            </tr>
        </thead>
        <tbody id="productsTableBody">
            <!-- Динамично зареждане от JavaScript -->
        </tbody>
    </table>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    loadProducts();

    // Добавяне на нов продукт през формуляра
    document.getElementById("addProductForm").addEventListener("submit", function(e) {
        e.preventDefault();
        
        const barcode = document.getElementById("prodBarcode").value;
        const name = document.getElementById("prodName").value;
        const stock_quantity = document.getElementById("prodStock").value;

        fetch("api.php?action=save_product", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ barcode, name, stock_quantity })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById("addProductForm").reset();
                loadProducts();
            } else {
                alert(data.message);
            }
        });
    });

    // Търсене в таблицата
    document.getElementById("searchProduct").addEventListener("input", function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("#productsTableBody tr");
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? "" : "none";
        });
    });
});

// Функция за зареждане на продуктите от api.php
function loadProducts() {
    fetch("api.php?action=get_products")
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const tbody = document.getElementById("productsTableBody");
            tbody.innerHTML = "";
            document.getElementById("productCount").innerText = "Общо продукти: " + data.products.length;
            document.getElementById("tableTitle").innerText = "Списък с Продукти (" + data.products.length + ")";

            if (data.products.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Няма записани продукти.</td></tr>`;
                return;
            }

            data.products.forEach(p => {
                tbody.innerHTML += `
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.1s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 12px 15px; color: var(--text-muted);">#${p.id}</td>
                        <td style="padding: 12px 15px; font-weight: 800; color: var(--primary-blue);">${p.barcode}</td>
                        <td style="padding: 12px 15px; font-weight: 700;">${p.name}</td>
                        <td style="padding: 12px 15px;">${p.stock_quantity} бр.</td>
                        <td style="padding: 12px 15px; text-align: right;">
                            <button onclick="deleteProduct(${p.id})" class="btn-action-config" style="padding: 6px 10px; font-size: 0.78rem; color: var(--status-red); border-color: #fecaca; background: #fef2f2;">✕ Изтрий</button>
                        </td>
                    </tr>
                `;
            });
        }
    });
}

// Функция за изтриване (може да добавиш action в api.php при нужда или да го оставиш)
function deleteProduct(id) {
    if(confirm("Сигурни ли сте, че искате да изтриете този продукт?")) {
        // Тук може да се прати заявка към api.php за изтриване
    }
}
</script>

<?php
include 'footer.php';
?>