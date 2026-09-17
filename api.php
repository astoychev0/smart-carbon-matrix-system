<?php
// api.php - Backend API for ToolStore Storage System

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// =========================================================================
// Секретна защита (API Key) за устройствата (ESP32)
// =========================================================================
$secret_token = "my_super_secret_key_123"; // Можеш да го смениш с какъвто пожелаеш

// Защитаваме екшъните, които се ползват от хардуера
if (in_array($action, ['get_commands', 'confirm_command'])) {
    $provided_token = $_GET['token'] ?? $_POST['token'] ?? '';
    
    if ($provided_token !== $secret_token) {
        echo json_encode(['success' => false, 'has_command' => false, 'message' => 'Unauthorized: Invalid token']);
        exit;
    }
}

try {
    // -------------------------------------------------------------------------
    // 1. Вход на потребител (Login)
    // -------------------------------------------------------------------------
    if ($action === 'login') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { $input = $_REQUEST; }

        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        $fallbackUser = 'admin';
        $fallbackPass = 'admin';

        $userFound = false;
        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ? AND password = ? LIMIT 1");
            $stmt->execute([$username, $password]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbUser) {
                $userFound = true;
                $username = $dbUser['username'];
            }
        } catch (Exception $e) {
            // Игнорираме грешка при липса на users таблица
        }

        if ($userFound || ($username === $fallbackUser && $password === $fallbackPass)) {
            echo json_encode([
                'success' => true,
                'user' => $username,
                'message' => 'Успешен вход!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Невалиден оператор или парола!'
            ]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. Вземане на клетките
    // -------------------------------------------------------------------------
    if ($action === 'get_cells') {
        $stmt = $pdo->query("
            SELECT 
                id, 
                cell_code, 
                matrix_name, 
                serial_number AS sn_code, 
                mold_code, 
                barcode,
                status, 
                status_type,
                gpio_pin, 
                is_empty,
                usage_count,
                notes
            FROM cells 
            ORDER BY id ASC
        ");
        $cells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cells' => $cells]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. Включване / Изключване на LED от Уеб Сайта
    // -------------------------------------------------------------------------
    if ($action === 'trigger_relay') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { $input = $_REQUEST; }

        $gpioPin = isset($input['gpio_pin']) ? (int)$input['gpio_pin'] : 0;
        $cellCode = trim($input['cell_code'] ?? '');
        $cmd = strtoupper(trim($input['command'] ?? 'ON'));
        $userName = trim($input['user_name'] ?? 'Operator');

        if ($gpioPin <= 0 && !empty($cellCode)) {
            $checkStmt = $pdo->prepare("SELECT gpio_pin FROM cells WHERE LOWER(cell_code) = LOWER(?) OR cell_code LIKE ? LIMIT 1");
            $checkStmt->execute([$cellCode, "%$cellCode%"]);
            $foundCell = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($foundCell && isset($foundCell['gpio_pin'])) {
                $gpioPin = (int)$foundCell['gpio_pin'];
            }
        }

        if ($gpioPin > 0) {
            $stmt = $pdo->prepare("INSERT INTO relay_queue (gpio_pin, command, duration_ms, status) VALUES (?, ?, 0, 'PENDING')");
            $stmt->execute([$gpioPin, $cmd]);

            $newStatus = ($cmd === 'ON') ? 'unlocked' : 'locked';
            if (!empty($cellCode)) {
                $updateStmt = $pdo->prepare("UPDATE cells SET status = ? WHERE cell_code = ? OR gpio_pin = ?");
                $updateStmt->execute([$newStatus, $cellCode, $gpioPin]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE cells SET status = ? WHERE gpio_pin = ?");
                $updateStmt->execute([$newStatus, $gpioPin]);
            }

            $logAction = ($cmd === 'ON') ? 'LED_ON' : 'LED_OFF';
            $logStmt = $pdo->prepare("INSERT INTO logs (user_name, cell_code, action) VALUES (?, ?, ?)");
            $logStmt->execute([$userName, $cellCode, $logAction]);

            echo json_encode([
                'success' => true, 
                'message' => "Командата {$cmd} за пин {$gpioPin} е изпратена"
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "Невалиден GPIO / Pin номер за клетка '{$cellCode}'"
            ]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. ESP32 проверява за нови команди (?action=get_commands)
    // -------------------------------------------------------------------------
    if ($action === 'get_commands') {
        $stmt = $pdo->query("SELECT id, gpio_pin, command FROM relay_queue WHERE LOWER(status) = 'pending' ORDER BY id ASC LIMIT 1");
        $command = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($command) {
            echo json_encode([
                'has_command' => true,
                'id'          => (int)$command['id'],
                'pin'         => (int)$command['gpio_pin'],
                'cmd'         => $command['command']
            ]);
        } else {
            echo json_encode(['has_command' => false]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // 5. ESP32 потвърждава изпълнена команда (?action=confirm_command)
    // -------------------------------------------------------------------------
    if ($action === 'confirm_command') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { $input = $_REQUEST; }

        $cmdId = (int)($input['id'] ?? 0);

        if ($cmdId > 0) {
            $stmt = $pdo->prepare("UPDATE relay_queue SET status = 'EXECUTED', executed_at = NOW() WHERE id = ?");
            $stmt->execute([$cmdId]);

            $pdo->query("DELETE FROM relay_queue WHERE LOWER(status) = 'executed' AND executed_at < NOW() - INTERVAL 1 HOUR");

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Невалидно Command ID']);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // 6. Запис / Редакция или Създаване на клетка (Save / Insert Cell)
    // -------------------------------------------------------------------------
    if ($action === 'save_cell') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { $input = $_REQUEST; }

        $id = (int)($input['id'] ?? 0);
        $cellCode = trim($input['cell_code'] ?? '');
        $matrixName = trim($input['matrix_name'] ?? '');
        $serialNumber = trim($input['sn_code'] ?? $input['serial_number'] ?? '');
        $moldCode = trim($input['mold_code'] ?? '');
        $barcode = trim($input['barcode'] ?? '');
        $gpioPin = isset($input['gpio_pin']) && $input['gpio_pin'] !== '' ? (int)$input['gpio_pin'] : 0;
        $notes = trim($input['notes'] ?? '');

        $isEmpty = (empty($matrixName) && empty($serialNumber) && empty($moldCode)) ? 1 : 0;

        if ($id > 0) {
            // Редакция на съществуваща клетка
            $stmt = $pdo->prepare("
                UPDATE cells 
                SET cell_code = ?, matrix_name = ?, serial_number = ?, mold_code = ?, barcode = ?, gpio_pin = ?, is_empty = ?, notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$cellCode, $matrixName, $serialNumber, $moldCode, $barcode, $gpioPin, $isEmpty, $notes, $id]);
            echo json_encode(['success' => true, 'message' => 'Клетката е обновена успешно']);
        } else {
            // Създаване на нова клетка (ако ID е 0 или липсва)
            if (empty($cellCode)) {
                echo json_encode(['success' => false, 'message' => 'Кодът на клетката (cell_code) е задължителен!']);
                exit;
            }
            $stmt = $pdo->prepare("
                INSERT INTO cells (cell_code, matrix_name, serial_number, mold_code, barcode, gpio_pin, is_empty, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'locked')
            ");
            $stmt->execute([$cellCode, $matrixName, $serialNumber, $moldCode, $barcode, $gpioPin, $isEmpty, $notes]);
            echo json_encode(['success' => true, 'message' => 'Новата клетка е създадена успешно']);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // 7. Извличане на Логове
    // -------------------------------------------------------------------------
    if ($action === 'get_logs') {
        $stmt = $pdo->query("SELECT id, timestamp, user_name, cell_code, action FROM logs ORDER BY id DESC LIMIT 100");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 8. Бутон "ВСИЧКИ OFF"
    // -------------------------------------------------------------------------
    if ($action === 'turn_off_all') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { $input = $_REQUEST; }

        $userName = trim($input['user_name'] ?? 'Operator');

        $stmt = $pdo->query("SELECT id, cell_code, gpio_pin FROM cells WHERE status = 'unlocked'");
        $activeCells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activeCells as $cell) {
            $pin = (int)$cell['gpio_pin'];
            $cellCode = $cell['cell_code'];
            $cellId = $cell['id'];

            if ($pin > 0) {
                $queueStmt = $pdo->prepare("INSERT INTO relay_queue (gpio_pin, command, duration_ms, status) VALUES (?, 'OFF', 0, 'PENDING')");
                $queueStmt->execute([$pin]);
            }

            $updStmt = $pdo->prepare("UPDATE cells SET status = 'locked' WHERE id = ?");
            $updStmt->execute([$cellId]);

            $logStmt = $pdo->prepare("INSERT INTO logs (user_name, cell_code, action) VALUES (?, ?, 'LED_OFF')");
            $logStmt->execute([$userName, $cellCode]);
        }

        echo json_encode(['success' => true, 'count' => count($activeCells)]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Невалидна акция']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Грешка в базата данни: ' . $e->getMessage()]);
    exit;
}