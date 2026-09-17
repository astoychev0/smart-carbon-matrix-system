<?php
// Свързване с базата данни
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "carbon_matrix";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Грешка в базата данни: " . $conn->connect_error);
}

// ДИНАМИЧНО ЗАСИЧАНЕ НА СТРУКТУРАТА НА ТАБЛИЦАТА 'cells'
$codeCol = 'code';
$hasRack = false;
$hasRow = false;
$hasNotes = false;

$colCheck = $conn->query("SHOW COLUMNS FROM cells");
if ($colCheck) {
    while ($col = $colCheck->fetch_assoc()) {
        $fieldName = $col['Field'];
        if ($fieldName === 'cell_code') {
            $codeCol = 'cell_code';
        }
        if ($fieldName === 'rack') $hasRack = true;
        if ($fieldName === 'row_num' || $fieldName === 'row') $hasRow = $fieldName;
        if ($fieldName === 'notes') $hasNotes = true;
    }
}

// 1. ОБРАБОТКА НА ДОБАВЯНЕ НА ЕДИНИЧНА ЛОКАЦИЯ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_single') {
    $rack = intval($_POST['rack']);
    $row = intval($_POST['row']);
    $code = "S" . $rack . "R" . $row;
    $notes = $conn->real_escape_string($_POST['notes']);

    $check = $conn->query("SELECT id FROM cells WHERE `$codeCol` = '$code'");
    if ($check && $check->num_rows == 0) {
        $fields = ["`$codeCol`"];
        $values = ["'$code'"];

        if ($hasRack) { $fields[] = "`rack`"; $values[] = $rack; }
        if ($hasRow) { $fields[] = "`$hasRow`"; $values[] = $row; }
        if ($hasNotes) { $fields[] = "`notes`"; $values[] = "'$notes'"; }

        $sql = "INSERT INTO cells (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        $conn->query($sql);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 2. ОБРАБОТКА НА МАСОВО ГЕНЕРИРАНЕ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_generate') {
    $racks = intval($_POST['racks']);
    $rows = intval($_POST['rows']);

    for ($r = 1; $r <= $racks; $r++) {
        for ($rw = 1; $rw <= $rows; $rw++) {
            $code = "S" . $r . "R" . $rw;
            $check = $conn->query("SELECT id FROM cells WHERE `$codeCol` = '$code'");
            if ($check && $check->num_rows == 0) {
                $fields = ["`$codeCol`"];
                $values = ["'$code'"];

                if ($hasRack) { $fields[] = "`rack`"; $values[] = $r; }
                if ($hasRow) { $fields[] = "`$hasRow`"; $values[] = $rw; }

                $sql = "INSERT INTO cells (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                $conn->query($sql);
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 3. ОБРАБОТКА НА ИЗТРИВАНЕ
if (isset($_GET['delete_code'])) {
    $del_code = $conn->real_escape_string($_GET['delete_code']);
    $conn->query("DELETE FROM cells WHERE `$codeCol` = '$del_code'");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ИЗВЛИЧАНЕ НА КЛЕТКИТЕ С ГРУПИРАНЕ ПО УНИКАЛЕН КОД (ПРЕМАХВА ДУБЛИКАТИТЕ)
$sql = "SELECT * FROM cells ORDER BY id ASC";
$cellsQuery = $conn->query($sql);

$racksData = [];
if ($cellsQuery) {
    while ($row = $cellsQuery->fetch_assoc()) {
        $code = !empty($row[$codeCol]) ? $row[$codeCol] : "S1R1";
        
        preg_match('/S(\d+)R(\d+)/i', $code, $matches);
        $rackNum = isset($matches[1]) ? intval($matches[1]) : (!empty($row['rack']) ? $row['rack'] : 1);
        $rowNum = isset($matches[2]) ? intval($matches[2]) : 1;
        
        $row['formatted_code'] = $code;
        $row['row_sort'] = $rowNum;
        
        // Записваме в асоциативен масив с ключ кода, за да няма дублиране
        $racksData[$rackNum][$code] = $row;
    }
}

// Сортиране на редовете във всеки стелаж по номер (S1R1, S1R2...)
foreach ($racksData as $rackNum => &$rowsArray) {
    uasort($rowsArray, function($a, $b) {
        return $a['row_sort'] - $b['row_sort'];
    });
}
unset($rowsArray);

include 'header.php';
?>

<style>
    :root {
        --bg-main: #f8fafc;
        --panel-bg: #ffffff;
        --card-bg: #ffffff;
        --input-bg: #f1f5f9;
        --border-color: #cbd5e1;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --ottobock-blue: #00529b;
        --ottobock-blue-hover: #003d75;
        --accent-red: #ef4444;
        --accent-green: #10b981;
        --card-occupied-bg: #f0fdf4;
    }

    body { 
        background-color: var(--bg-main) !important; 
        color: var(--text-main) !important; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .loc-container { display: flex; gap: 24px; padding: 20px; }
    
    .loc-sidebar { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; gap: 20px; }

    .loc-panel {
        background-color: var(--panel-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .panel-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--ottobock-blue);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-group { margin-bottom: 14px; }
    .form-group label { 
        display: block; 
        font-size: 11px; 
        font-weight: 700; 
        color: var(--text-muted); 
        text-transform: uppercase; 
        margin-bottom: 6px; 
    }
    .form-group input {
        width: 100%;
        background-color: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
    }
    .form-group input:focus { border-color: var(--ottobock-blue); }
    .panel-subtitle { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }

    .btn-submit {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        border: none;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-top: 10px;
        transition: background-color 0.2s;
    }
    .btn-blue { background-color: var(--ottobock-blue); color: #ffffff; }
    .btn-blue:hover { background-color: var(--ottobock-blue-hover); }
    
    .btn-outline { 
        background-color: transparent; 
        border: 1px solid var(--ottobock-blue); 
        color: var(--ottobock-blue); 
    }
    .btn-outline:hover { background-color: #f0f7ff; }

    .loc-content { flex: 1; }
    .page-header { font-size: 26px; font-weight: 800; color: var(--ottobock-blue); margin-bottom: 20px; }

    .rack-section {
        background-color: var(--panel-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .rack-header { 
        font-size: 18px; 
        font-weight: 700; 
        color: var(--text-main); 
        margin-bottom: 18px; 
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 8px;
    }
    .rack-header span { color: var(--text-muted); font-size: 14px; font-weight: normal; }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 15px;
    }

    .card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 15px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 130px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .card.occupied {
        border: 1px solid var(--accent-green);
        background-color: var(--card-occupied-bg);
    }
    .card-top { display: flex; justify-content: space-between; align-items: center; }
    .badge {
        border: 1px solid var(--ottobock-blue);
        color: var(--ottobock-blue);
        background-color: #f0f7ff;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 13px;
    }
    .card-body { margin: 12px 0; }
    .status-empty { color: var(--text-muted); font-size: 13px; font-weight: 500; }
    .status-occupied { color: #047857; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 2px; }
    .serial-no { color: #ea580c; font-size: 11px; font-weight: normal; }

    .btn-delete {
        background-color: #fee2e2;
        color: var(--accent-red);
        border: 1px solid #fca5a5;
        padding: 8px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        width: 100%;
        text-align: center;
        text-decoration: none;
        display: block;
        transition: background-color 0.2s;
    }
    .btn-delete:hover { background-color: var(--accent-red); color: white; }
</style>

<div class="loc-container">
    <!-- ЛЯВА ЧАСТ: ФОРМИ -->
    <div class="loc-sidebar">
        
        <!-- 1. Добави Единична Локация -->
        <div class="loc-panel">
            <div class="panel-title">➕ Добави Локация</div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_single">
                <div class="form-group">
                    <label>Стелаж №</label>
                    <input type="number" name="rack" value="1" required>
                </div>
                <div class="form-group">
                    <label>Ред №</label>
                    <input type="number" name="row" value="1" required>
                </div>
                <div class="form-group">
                    <label>Бележки</label>
                    <input type="text" name="notes" placeholder="по желание...">
                </div>
                <button type="submit" class="btn-submit btn-blue">➕ Добави</button>
            </form>
        </div>

        <!-- 2. Масово Генериране -->
        <div class="loc-panel">
            <div class="panel-title">⚡ Масово Генериране</div>
            <div class="panel-subtitle">Генерира S1R1 до SxRy (прескача съществуващите)</div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="bulk_generate">
                <div class="form-group">
                    <label>Стелажи</label>
                    <input type="number" name="racks" value="5" required>
                </div>
                <div class="form-group">
                    <label>Редове</label>
                    <input type="number" name="rows" value="10" required>
                </div>
                <button type="submit" class="btn-submit btn-blue">⚡ Генерирай</button>
            </form>
        </div>

        <!-- 3. Принтиране -->
        <div class="loc-panel">
            <div class="panel-title">🖨️ Печат</div>
            <div class="panel-subtitle">Баркод етикет за всяка локация</div>
            <button onclick="window.print()" class="btn-submit btn-outline">🖨️ Принтирай Всички Етикети</button>
        </div>
    </div>

    <!-- ДЕСНА ЧАСТ: СТЕЛАЖИ И КЛЕТКИ -->
    <div class="loc-content">
        <div class="page-header">🗺️ Управление на Локации</div>

        <?php if (empty($racksData)): ?>
            <div class="rack-section" style="text-align: center; color: var(--text-muted);">
                Няма намерени клетки в таблицата <b>cells</b>. Използвайте панела вляво за добавяне или масово генериране.
            </div>
        <?php else: ?>
            <?php foreach ($racksData as $rackNum => $rows): ?>
                <div class="rack-section">
                    <div class="rack-header">📦 Стелаж <?= $rackNum ?> <span>(<?= count($rows) ?> реда)</span></div>
                    <div class="grid">
                        <?php foreach ($rows as $cell): ?>
                            <?php 
                                $isOccupied = !empty($cell['mold_id']) || (!empty($cell['status']) && $cell['status'] == 'occupied') || !empty($cell['matrix_name']); 
                                $matrixName = !empty($cell['matrix_name']) ? $cell['matrix_name'] : (!empty($cell['mold_id']) ? $cell['mold_id'] : '');
                                $serialNo = !empty($cell['serial_no']) ? $cell['serial_no'] : '';
                                
                                // Задаване на уникален ID за всяка клетка (напр. id="s1r1")
                                $cellIdAttr = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cell['formatted_code']));
                            ?>
                            <div class="card <?= $isOccupied ? 'occupied' : '' ?>" id="<?= $cellIdAttr ?>">
                                <div class="card-top">
                                    <span class="badge"><?= htmlspecialchars($cell['formatted_code']) ?></span>
                                    <span style="color: var(--text-muted); cursor: pointer;" title="Принтирай етикет">💾</span>
                                </div>
                                <div class="card-body">
                                    <?php if ($isOccupied): ?>
                                        <div class="status-occupied">
                                            ✓ <?= htmlspecialchars($matrixName) ?>
                                            <?php if (!empty($serialNo)): ?>
                                                <span class="serial-no">С/Н: <?= htmlspecialchars($serialNo) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-empty">Свободно</div>
                                    <?php endif; ?>
                                </div>
                                <a href="?delete_code=<?= urlencode($cell['formatted_code']) ?>" 
                                   class="btn-delete" 
                                   onclick="return confirm('Сигурни ли сте, че искате да изтриете <?= $cell['formatted_code'] ?>?');">
                                   ✕ Изтрий
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include 'footer.php';
?>