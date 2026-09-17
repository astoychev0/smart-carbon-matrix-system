<?php
include 'header.php';
include 'db.php';

// Извличане на всички 16 клетки
$cells = [];
try {
    $stmt = $pdo->query("SELECT * FROM cells ORDER BY id ASC LIMIT 16");
    $cells = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $error_msg = $e->getMessage();
}

// Преброяване на заетите клетки
$occupied_count = 0;
foreach ($cells as $cell) {
    if (!empty($cell['matrix_name'])) {
        $occupied_count++;
    }
}
$total_cells = count($cells) > 0 ? count($cells) : 16;
?>

<style>
    /* ЗАГЛАВНА ЧАСТ И ТЪРСАЧКА */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--primary-blue, #003896);
        margin: 0;
    }

    .search-and-stats {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .search-box {
        position: relative;
        width: 260px;
    }

    .search-box input {
        width: 100%;
        height: 40px;
        padding: 0 14px 0 38px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.88rem;
        background: #ffffff;
        outline: none;
        transition: all 0.2s ease;
    }

    .search-box input:focus {
        border-color: #003896;
        box-shadow: 0 0 0 3px rgba(0, 56, 150, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .stats-badge {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 800;
        color: #334155;
        white-space: nowrap;
    }

    /* РЕШЕТКА ОТ 16 КЛЕТКИ (GRID) */
    .cells-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }

    @media (max-width: 1200px) { .cells-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .cells-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px)  { .cells-grid { grid-template-columns: 1fr; } }

    /* КАРТА НА КЛЕТКА */
    .cell-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 200px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .cell-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 56, 150, 0.08);
    }

    .cell-card.occupied {
        border-top: 4px solid #003896;
    }

    .cell-card.empty {
        border-top: 4px solid #94a3b8;
        background: #fafafa;
    }

    .cell-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .cell-code-badge {
        background: #e0f2fe;
        color: #0369a1;
        font-weight: 800;
        font-size: 0.85rem;
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid #bae6fd;
    }

    .cell-status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }

    .dot-occupied { background: #10b981; box-shadow: 0 0 6px rgba(16, 185, 129, 0.6); }
    .dot-empty { background: #cbd5e1; }

    .matrix-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
        line-height: 1.3;
    }

    .matrix-sn {
        font-size: 0.75rem;
        color: #b45309;
        background: #fef3c7;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .matrix-barcode {
        font-size: 0.72rem;
        font-family: monospace;
        color: #64748b;
    }

    .empty-text {
        color: #94a3b8;
        font-size: 0.88rem;
        font-weight: 600;
        text-align: center;
        margin: 10px 0;
    }

    /* БУТОНИ ЗА СВЕТЛИНЕН СИГНАЛ (LED CONTROL) */
    .cell-actions {
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 8px;
    }

    .btn-led {
        flex: 1;
        padding: 8px 0;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.2s ease;
        background: #ffffff;
    }

    .btn-led-on {
        color: #0284c7;
        border-color: #bae6fd;
        background: #f0f9ff;
    }

    .btn-led-on:hover {
        background: #0284c7;
        color: #ffffff;
    }

    .btn-led-off {
        color: #64748b;
        border-color: #e2e8f0;
    }

    .btn-led-off:hover {
        background: #ef4444;
        border-color: #ef4444;
        color: #ffffff;
    }

    .btn-led:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<!-- ЗАГЛАВИЕ И ФИЛТЪР -->
<div class="dashboard-header">
    <h2 class="page-title">
        ⚡ Управление на Рафтовете (Клетка 1 - Клетка 16)
    </h2>
    <div class="search-and-stats">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Търсене на матрица..." onkeyup="filterCells()">
        </div>
        <div class="stats-badge">
            АКТИВНИ: <span id="activeCount" style="color:#003896;"><?= $occupied_count ?></span> / <?= $total_cells ?>
        </div>
    </div>
</div>

<!-- РЕШЕТКА С КЛЕТКИ -->
<div class="cells-grid" id="cellsGrid">
    <?php if (empty($cells)): ?>
        <!-- Резервен изглед, ако няма клетки в базата -->
        <?php for ($i = 1; $i <= 16; $i++): ?>
            <div class="cell-card empty">
                <div class="cell-head">
                    <span class="cell-code-badge">📍 Клетка <?= $i ?></span>
                    <span class="cell-status-dot dot-empty"></span>
                </div>
                <div class="empty-text">Няма намерена матрица</div>
            </div>
        <?php endfor; ?>
    <?php else: ?>
        <?php foreach ($cells as $index => $c): 
            $is_occupied = !empty($c['matrix_name']);
            $cell_code   = htmlspecialchars($c['cell_code'] ?? 'S1R'.($index + 1));
            $matrix_name = htmlspecialchars($c['matrix_name'] ?? '');
            $serial_num  = htmlspecialchars($c['serial_number'] ?? '');
            $barcode     = htmlspecialchars($c['barcode'] ?? '');
            $gpio_pin    = (int)($c['gpio_pin'] ?? 0);
        ?>
            <div class="cell-card <?= $is_occupied ? 'occupied' : 'empty' ?>" 
                 data-search="<?= strtolower($cell_code . ' ' . $matrix_name . ' ' . $serial_num . ' ' . $barcode) ?>">
                
                <div class="cell-head">
                    <span class="cell-code-badge">📍 <?= $cell_code ?></span>
                    <span class="cell-status-dot <?= $is_occupied ? 'dot-occupied' : 'dot-empty' ?>" 
                          title="<?= $is_occupied ? 'Заета' : 'Свободна' ?>"></span>
                </div>

                <?php if ($is_occupied): ?>
                    <div>
                        <div class="matrix-title"><?= $matrix_name ?></div>
                        <?php if ($serial_num): ?>
                            <span class="matrix-sn">SN: <?= $serial_num ?></span>
                        <?php endif; ?>
                        <?php if ($barcode): ?>
                            <div class="matrix-barcode">📊 <?= $barcode ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-text">Свободна клетка</div>
                <?php endif; ?>

                <!-- Бутони за контрол на диода (Налични за всички клетки) -->
                <div class="cell-actions">
                    <button type="button" class="btn-led btn-led-on" onclick="triggerLed(event, <?= $gpio_pin ?>, '<?= $cell_code ?>', 'ON')">
                        💡 Светни
                    </button>
                    <button type="button" class="btn-led btn-led-off" onclick="triggerLed(event, <?= $gpio_pin ?>, '<?= $cell_code ?>', 'OFF')">
                        ❌ Изгаси
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Филтър за търсене в реално време
function filterCells() {
    let input = document.getElementById('searchInput').value.toLowerCase().trim();
    let cards = document.querySelectorAll('.cell-card');

    cards.forEach(card => {
        let searchData = card.getAttribute('data-search') || '';
        if (searchData.includes(input)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Коригирана функция за изпращане на команда към api.php
async function triggerLed(event, gpioPin, cellCode, command) {
    console.log(`🚀 Изпращане на команда: ${command} за ${cellCode} (GPIO: ${gpioPin})`);

    const btn = event ? event.currentTarget : null;
    if (btn) btn.disabled = true;

    try {
        const response = await fetch('api.php?action=trigger_relay', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json' 
            },
            body: JSON.stringify({
                gpio_pin: parseInt(gpioPin) || 0,
                cell_code: cellCode,
                command: command,
                user_name: 'Оператор'
            })
        });

        const result = await response.json();
        console.log("📥 Отговор от сървъра:", result);

        if (result.success) {
            console.log(`✅ Командата ${command} е регистрирана успешно!`);
        } else {
            alert("Грешка: " + result.message);
        }
    } catch (err) {
        console.error('❌ Грешка при изпращане на командата:', err);
        alert("Възникна грешка при връзката със сървъра.");
    } finally {
        if (btn) btn.disabled = false;
    }
}
</script>

<?php include 'footer.php'; ?>