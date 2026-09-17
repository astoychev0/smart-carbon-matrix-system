<?php
include 'header.php';
include 'db.php';

$success_msg = '';
$error_msg = '';

// 1. АВТОМАТИЧНО ДОБАВЯНЕ НА ЛИПСВАЩА КОЛОНА
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cells")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('serial_number', $cols)) {
        $pdo->exec("ALTER TABLE cells ADD COLUMN serial_number VARCHAR(100) DEFAULT NULL AFTER matrix_name");
    }
} catch (\PDOException $e) {}

// 2. ИЗТРИВАНЕ НА МАТРИЦА (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id > 0) {
        try {
            $sqlDelete = "UPDATE cells 
                          SET matrix_name = NULL, serial_number = NULL, barcode = NULL, status_type = 'available', is_empty = 1 
                          WHERE id = ?";
            $pdo->prepare($sqlDelete)->execute([$del_id]);
            $success_msg = "Матрицата беше изтрита и клетката е освободена!";
        } catch (\PDOException $e) {
            $error_msg = "Грешка при изтриване: " . $e->getMessage();
        }
    }
}

// 3. ОБРАБОТКА НА РЕДАКЦИЯТА (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_matrix'])) {
    $edit_id     = (int)$_POST['edit_id'];
    $matrix_name = trim($_POST['matrix_name'] ?? '');
    $serial      = trim($_POST['serial'] ?? '');
    $status_type = trim($_POST['status'] ?? 'occupied');
    $new_cell_id = $_POST['cell_id'] !== '' ? (int)$_POST['cell_id'] : null;

    if (!empty($matrix_name) && $edit_id > 0) {
        try {
            $stmtOld = $pdo->prepare("SELECT * FROM cells WHERE id = ?");
            $stmtOld->execute([$edit_id]);
            $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if ($new_cell_id !== null && $new_cell_id !== $edit_id) {
                // Прехвърляне в нова клетка
                $sqlTransfer = "UPDATE cells 
                                SET matrix_name = ?, serial_number = ?, barcode = ?, status_type = ?, is_empty = 0 
                                WHERE id = ?";
                $pdo->prepare($sqlTransfer)->execute([$matrix_name, $serial, $oldRow['barcode'], $status_type, $new_cell_id]);

                // Освобождаване на старата
                $sqlClearOld = "UPDATE cells 
                                SET matrix_name = NULL, serial_number = NULL, barcode = NULL, status_type = 'available', is_empty = 1 
                                WHERE id = ?";
                $pdo->prepare($sqlClearOld)->execute([$edit_id]);
            } else {
                // Редакция на място
                $sql = "UPDATE cells SET matrix_name = :mname, serial_number = :sn, status_type = :st WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':mname' => $matrix_name, ':sn' => $serial, ':st' => $status_type, ':id' => $edit_id]);
            }
            $success_msg = "Успешно редактирано!";
        } catch (\PDOException $e) {
            $error_msg = "Грешка при запис: " . $e->getMessage();
        }
    }
}

// 4. ОБРАБОТКА НА ДОБАВЯНЕТО (INSERT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_matrix'])) {
    $matrix_name = trim($_POST['matrix_name'] ?? '');
    $serial      = trim($_POST['serial'] ?? '');
    $selected_id = $_POST['cell_id'] ?? null;

    if (!empty($matrix_name)) {
        $generatedBarcode = 'MOLD-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        try {
            if (!empty($selected_id)) {
                $sql = "UPDATE cells SET matrix_name = ?, serial_number = ?, barcode = ?, status_type = 'occupied', is_empty = 0 WHERE id = ?";
                $pdo->prepare($sql)->execute([$matrix_name, $serial, $generatedBarcode, $selected_id]);
            } else {
                $sql = "INSERT INTO cells (barcode, matrix_name, serial_number, status_type, is_empty) VALUES (?, ?, ?, 'occupied', 0)";
                $pdo->prepare($sql)->execute([$generatedBarcode, $matrix_name, $serial]);
            }
            $success_msg = "Матрицата беше добавена успешно!";
        } catch (\PDOException $e) {
            $error_msg = "Грешка при добавяне: " . $e->getMessage();
        }
    }
}

// 5. ИЗВЛИЧАНЕ НА ДАННИ
$matrices = [];
try {
    $stmt = $pdo->query("SELECT * FROM cells WHERE matrix_name IS NOT NULL AND matrix_name != '' ORDER BY id DESC");
    $matrices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}

$free_cells = [];
try {
    $free_cells = $pdo->query("SELECT * FROM cells WHERE matrix_name IS NULL OR matrix_name = '' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}
?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
    /* Стилове за формата и таблицата */
    .page-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--primary-blue, #003896);
        margin-bottom: 22px;
    }

    .matrix-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    .matrix-card-title {
        margin: 0 0 16px 0;
        color: var(--primary-blue, #003896);
        font-size: 0.95rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 2fr 2fr 2fr 1.2fr;
        gap: 14px;
        align-items: flex-end;
    }

    @media (max-width: 900px) {
        .form-grid { grid-template-columns: 1fr; }
    }

    .custom-label {
        display: block;
        font-size: 0.70rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .custom-input, .custom-select {
        width: 100%;
        height: 42px;
        padding: 0 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.88rem;
        box-sizing: border-box;
        background: #f8fafc;
        color: #0f172a;
        transition: all 0.2s ease;
    }

    .custom-input:focus, .custom-select:focus {
        border-color: #003896;
        background: #ffffff;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 56, 150, 0.1);
    }

    .btn-save {
        height: 42px;
        width: 100%;
        background: linear-gradient(135deg, #003896 0%, #0284c7 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.88rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-shadow: 0 3px 8px rgba(0, 56, 150, 0.25);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(0, 56, 150, 0.35);
    }

    /* СТИЛОВЕ НА ТАБЛИЦАТА */
    .table-container {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .custom-table th {
        background: #f8fafc;
        padding: 14px 16px;
        font-size: 0.82rem;
        font-weight: 800;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
    }

    .custom-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.88rem;
        vertical-align: middle;
    }

    .custom-table tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Значки (Badges) */
    .badge-loc {
        background: #e0f2fe;
        color: #0369a1;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.78rem;
        border: 1px solid #bae6fd;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .badge-serial {
        background: #fef3c7;
        color: #b45309;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.78rem;
        border: 1px solid #fde68a;
    }

    .badge-status {
        font-weight: 800;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .status-occupied { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .status-available, .status-free { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .status-maintenance { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* Бутони за действия */
    .actions-cell {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .action-btn {
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .action-btn:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #003896;
    }

    .btn-delete {
        color: #dc2626;
        border-color: #fecaca;
        background: #fff5f5;
    }

    .btn-delete:hover {
        background: #fee2e2;
        border-color: #fca5a5;
        color: #991b1b;
    }

    /* Модален прозорец */
    .modal-backdrop {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .modal-content {
        background: white;
        padding: 24px;
        border-radius: 14px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        border-top: 5px solid #003896;
    }
</style>

<div class="page-title">
    <span>⚙️</span> Управление на Матрици
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background:#d1fae5; color:#065f46; padding:12px 16px; border-radius:8px; margin-bottom:15px; font-weight:700; border:1px solid #a7f3d0;">
        ✓ <?= $success_msg ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:15px; font-weight:700; border:1px solid #fca5a5;">
        ⚠️ <?= $error_msg ?>
    </div>
<?php endif; ?>

<!-- ФОРМА ЗА ДОБАВЯНЕ -->
<div class="matrix-card">
    <div class="matrix-card-title">➕ Добави Нова Матрица</div>
    <form method="POST" class="form-grid">
        <input type="hidden" name="add_matrix" value="1">
        <div>
            <label class="custom-label">Наименование *</label>
            <input type="text" name="matrix_name" class="custom-input" placeholder="напр. Колянна Черупка" required>
        </div>
        <div>
            <label class="custom-label">Сериен номер</label>
            <input type="text" name="serial" class="custom-input" placeholder="напр. SN-10023">
        </div>
        <div>
            <label class="custom-label">Локация</label>
            <select name="cell_id" class="custom-select">
                <option value="">-- Без локация --</option>
                <?php foreach ($free_cells as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['cell_code'] ?? 'ID: '.$c['id']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-save">➕ Запиши</button>
        </div>
    </form>
</div>

<!-- ТАБЛИЦА С МАТРИЦИ -->
<div class="table-container">
    <table class="custom-table">
        <thead>
            <tr>
                <th style="width: 220px;">Баркод</th>
                <th style="width: 110px;">Клетка</th>
                <th>Наименование</th>
                <th style="width: 160px;">Сериен №</th>
                <th style="width: 140px;">Статус</th>
                <th style="text-align:right; width: 220px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($matrices as $row): 
                $sn = $row['serial_number'] ?? '';
                $st = $row['status_type'] ?? 'occupied';
                $mName = htmlspecialchars($row['matrix_name'] ?? '-');
                $bc = $row['barcode'] ?? 'MOLD-'.$row['id'];
                $cellCode = $row['cell_code'] ?? '';

                $statusLabel = '✓ Заета';
                if ($st === 'available' || $st === 'free') { $statusLabel = '◯ Свободна'; }
                elseif ($st === 'maintenance') { $statusLabel = '🛠️ В Ремонт'; }
            ?>
            <tr>
                <td><svg class="barcode-element" data-barcode="<?= htmlspecialchars($bc) ?>"></svg></td>
                <td>
                    <?php if ($cellCode): ?>
                        <span class="badge-loc">📍 <?= htmlspecialchars($cellCode) ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-weight: 600;">—</span>
                    <?php endif; ?>
                </td>
                <td><b style="color: #0f172a;"><?= $mName ?></b></td>
                <td>
                    <?php if (!empty($sn)): ?>
                        <span class="badge-serial"><?= htmlspecialchars($sn) ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-weight: 600;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-status status-<?= $st ?>"><?= $statusLabel ?></span>
                </td>
                <td style="text-align:right;">
                    <div class="actions-cell">
                        <button type="button" class="action-btn" onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes($mName) ?>', '<?= addslashes($sn) ?>', '<?= $st ?>', '<?= htmlspecialchars($cellCode) ?>')">✏️ Edit</button>
                        <button type="button" class="action-btn" onclick="printBarcode('<?= htmlspecialchars($bc) ?>', '<?= addslashes($mName) ?>', '<?= addslashes($sn) ?>')">🖨️ Print</button>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Сигурни ли сте, че искате да изтриете матрицата «<?= addslashes($mName) ?>»?');">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="action-btn btn-delete">🗑️ Del</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА РЕДАКЦИЯ -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#003896; font-size:1.1rem; font-weight:800;">✏️ Редакция на Матрица</h3>
        <form method="POST">
            <input type="hidden" name="edit_matrix" value="1">
            <input type="hidden" name="edit_id" id="edit_id">
            
            <div style="margin-bottom:14px;">
                <label class="custom-label">Наименование *</label>
                <input type="text" name="matrix_name" id="edit_name" class="custom-input" required>
            </div>
            
            <div style="margin-bottom:14px;">
                <label class="custom-label">Сериен Номер</label>
                <input type="text" name="serial" id="edit_serial" class="custom-input" placeholder="Въведи сериен номер">
            </div>

            <div style="margin-bottom:14px;">
                <label class="custom-label">Локация (Клетка)</label>
                <select name="cell_id" id="edit_cell_id" class="custom-select">
                    <option id="current_location_option" value="">Текуща клетка</option>
                    <option value="">-- Премахни от клетка --</option>
                    <?php foreach ($free_cells as $c): ?>
                        <option value="<?= $c['id'] ?>">Премести в: <?= htmlspecialchars($c['cell_code'] ?? 'ID: '.$c['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:22px;">
                <label class="custom-label">Статус</label>
                <select name="status" id="edit_status" class="custom-select" style="font-weight:700;">
                    <option value="occupied">✓ Заета (Occupied)</option>
                    <option value="available">◯ Свободна (Available)</option>
                    <option value="maintenance">🛠️ В Ремонт (Maintenance)</option>
                </select>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeEditModal()" class="action-btn" style="width:50%; justify-content:center; padding:10px;">Отказ</button>
                <button type="submit" class="btn-save" style="width:50%;">Запази</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".barcode-element").forEach(function(el) {
        let code = el.getAttribute("data-barcode");
        if (code) {
            JsBarcode(el, code, { format: "CODE128", width: 1.2, height: 30, displayValue: true, fontSize: 10, margin: 0 });
        }
    });
});

function openEditModal(id, name, serial, status, cellCode) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_serial').value = serial;
    document.getElementById('edit_status').value = status;
    
    let currentOpt = document.getElementById('current_location_option');
    currentOpt.value = id;
    currentOpt.textContent = cellCode ? "📍 Текуща: " + cellCode : "Текуща: (Без клетка)";
    currentOpt.selected = true;

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function printBarcode(barcode, name, serial) {
    let printWin = window.open('', '_blank', 'width=420,height=320');
    printWin.document.write(`
        <html>
        <head>
            <title>Печат на етикет - ${barcode}</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
            <style>
                body { font-family: sans-serif; text-align: center; padding: 20px; margin: 0; }
                .title { font-weight: bold; font-size: 16px; margin-bottom: 4px; }
                .serial { font-size: 12px; color: #555; margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class="title">${name}</div>
            ${serial ? `<div class="serial">${serial}</div>` : ''}
            <svg id="print-barcode"></svg>
            <script>
                JsBarcode("#print-barcode", "${barcode}", { format: "CODE128", width: 2, height: 50, displayValue: true });
                setTimeout(() => { window.print(); window.close(); }, 500);
            <\/script>
        </body>
        </html>
    `);
    printWin.document.close();
}
</script>

<?php include 'footer.php'; ?>