<?php
include 'header.php';
include 'db.php';

// Извличане на филтрите от GET заявката
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$selected_user = $_GET['user'] ?? '';

// Изграждане на динамична заявка за филтриране
$where_clauses = [];
$params = [];

if (!empty($date_from)) {
    $where_clauses[] = "l.created_at >= ?";
    $params[] = $date_from . " 00:00:00";
}

if (!empty($date_to)) {
    $where_clauses[] = "l.created_at <= ?";
    $params[] = $date_to . " 23:59:59";
}

if (!empty($selected_user)) {
    $where_clauses[] = "l.user_name LIKE ?";
    $params[] = "%" . $selected_user . "%";
}

$sql = "SELECT l.*, c.cell_code FROM logs l LEFT JOIN cells c ON l.cell_id = c.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY l.id DESC";

$logs = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $error_msg = $e->getMessage();
}

$total_logs = count($logs);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="color: #003896; font-weight: 800; font-size: 1.35rem; margin: 0; display: flex; align-items: center; gap: 10px;">
        📊 Справки и История на Операциите
    </h2>
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 8px; font-weight: 800; color: #334155; font-size: 0.82rem;">
        Общ брой записи: <span style="color: #003896;"><?= $total_logs ?></span>
    </div>
</div>

<!-- Филтри за справки -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
    <h3 style="font-size: 0.95rem; color: #003896; font-weight: 800; margin: 0 0 16px 0; display: flex; align-items: center; gap: 6px;">
        🔍 Филтриране на Дневник (Logs)
    </h3>
    
    <form action="" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 1.5fr; gap: 14px; align-items: flex-end;">
        <div>
            <label style="display: block; font-size: 0.70rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">От дата</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #f8fafc; color: #0f172a; outline: none;">
        </div>

        <div>
            <label style="display: block; font-size: 0.70rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">До дата</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #f8fafc; color: #0f172a; outline: none;">
        </div>

        <div>
            <label style="display: block; font-size: 0.70rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Оператор / Потребител</label>
            <input type="text" name="user" value="<?= htmlspecialchars($selected_user) ?>" placeholder="Въведи име..." style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #f8fafc; color: #0f172a; outline: none;">
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" style="height: 42px; flex: 1; background: linear-gradient(135deg, #003896 0%, #0284c7 100%); color: #fff; font-weight: 800; border: none; border-radius: 8px; cursor: pointer; font-size: 0.88rem; box-shadow: 0 3px 8px rgba(0, 56, 150, 0.25);">
                🔄 Търси
            </button>
            <a href="reports.php" style="height: 42px; padding: 0 14px; display: flex; align-items: center; justify-content: center; background: #ffffff; border: 1px solid #cbd5e1; color: #334155; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.88rem;">
                Нулирай
            </a>
        </div>
    </form>
</div>

<!-- Таблица с история -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <h3 style="font-size: 1.05rem; color: #0f172a; font-weight: 800; margin: 0;">Резултати от журнала</h3>
    <button type="button" onclick="exportTableToCSV('warehouse_logs.csv')" style="padding: 8px 14px; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        📥 Експорт в Excel (CSV)
    </button>
</div>

<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
    <table id="logsTable" style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.82rem; font-weight: 800;">
                <th style="padding: 14px 16px; width: 100px;">ID</th>
                <th style="padding: 14px 16px; width: 170px;">Дата и час</th>
                <th style="padding: 14px 16px; width: 180px;">Клетка / Адрес</th>
                <th style="padding: 14px 16px;">Действие / Команда</th>
                <th style="padding: 14px 16px; width: 200px;">Оператор</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8; font-weight: 600;">
                        Няма намерени записи в журнала по заданите критерии.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $action = htmlspecialchars($log['action'] ?? '-');
                    $is_on = (strpos(strtoupper($action), 'ON') !== false);
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                    <td style="padding: 12px 16px; font-weight: 800; color: #003896;">#<?= $log['id'] ?></td>
                    <td style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 0.85rem;"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td style="padding: 12px 16px;">
                        <?php if (!empty($log['cell_code'])): ?>
                            <span style="background: #e0f2fe; color: #0369a1; font-weight: 800; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; border: 1px solid #bae6fd;">
                                📍 <?= htmlspecialchars($log['cell_code']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-weight: 600;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 16px;">
                        <span style="background: <?= $is_on ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $is_on ? '#15803d' : '#475569' ?>; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 0.78rem; border: 1px solid <?= $is_on ? '#bbf7d0' : '#e2e8f0' ?>;">
                            <?= $action ?> (GPIO: <?= (int)$log['gpio_pin'] ?>)
                        </span>
                    </td>
                    <td style="padding: 12px 16px; font-weight: 700; color: #0f172a; font-size: 0.88rem;">
                        <?= htmlspecialchars($log['user_name'] ?? 'Оператор') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Функция за сваляне на таблицата в CSV формат (отваря се директно в Excel)
function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#logsTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            // Премахваме излишните интервали и кавички
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s*)/g, " ");
            row.push('"' + data + '"');
        }
        csv.join(",");
        csv.push(row.join(","));
    }

    // Създаване на линк за сваляне
    let csvFile = new Blob(["\uFEFF" + csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.download = filename;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php
include 'footer.php';
?>