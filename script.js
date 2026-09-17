let currentCellsData = [];
let currentUser = "Александър Стойчев";
let autoRefreshTimer = null;
let currentPage = 'rack';
let isSendingCommand = false; // Защита срещу презареждане по време на изпращане

// Секретен токен за достъп до защитените API ендпойнти
const API_TOKEN = "my_super_secret_key_123";

document.addEventListener("DOMContentLoaded", () => {
    const savedUser = localStorage.getItem("ottobock_user");
    if (savedUser) {
        currentUser = savedUser;
        const userTag = document.getElementById("current-user-tag");
        if (userTag) userTag.innerText = currentUser;

        const loginScreen = document.getElementById("login-screen");
        const appContent = document.getElementById("app-content");
        if (loginScreen) loginScreen.style.display = "none";
        if (appContent) appContent.style.display = "block";
        
        initApp();
    }
});

function initApp() {
    loadCellsFromDatabase();
    startAutoRefresh();
}

// 🟢 Автоматично опресняване през 3 секунди
function startAutoRefresh() {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(() => {
        // Не презареждаме мрежата, ако потребителят редактира в модала или изпраща команда
        const modal = document.getElementById("config-modal");
        const isConfigOpen = modal && modal.style.display === "flex";

        if (currentPage === 'rack' && !isConfigOpen && !isSendingCommand) {
            loadCellsFromDatabase(true);
        } else if (currentPage === 'archive') {
            loadLogsFromDatabase();
        }
    }, 3000);
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

function goToDashboard() {
    switchPage('rack');
}

// Зареждане на клетките от api.php?action=get_cells
async function loadCellsFromDatabase(isSilent = false) {
    try {
        const response = await fetch(`api.php?action=get_cells&token=${API_TOKEN}`);
        const data = await response.json();

        if (data.success) {
            currentCellsData = data.cells;
            
            const searchInput = document.getElementById("search-input");
            if (searchInput && searchInput.value.trim() !== "") {
                filterRackGrid();
            } else {
                renderRackGrid();
            }
            updateStats();
        }
    } catch (err) {
        console.error("Грешка при зареждане на клетките:", err);
    }
}

// Проверка за отключена/светеща клетка (по статус от DB)
function isLedOn(cell) {
    if (!cell || !cell.status) return false;
    const st = cell.status.toString().toLowerCase();
    return st === 'unlocked' || st === 'включен' || st === 'active' || st === 'on';
}

function hasMatrix(cell) {
    if (!cell.matrix_name) return false;
    const trimmed = cell.matrix_name.trim();
    return trimmed !== "" && trimmed !== "ПРАЗНА КЛЕТКА";
}

function renderRackGrid() {
    const container = document.getElementById("rack-matrix-container");
    if (!container) return;
    container.innerHTML = "";

    currentCellsData.forEach((cell) => {
        const cellCard = createCellCardElement(cell);
        container.appendChild(cellCard);
    });
}

// 📦 Генериране на карта за клетка
function createCellCardElement(cell) {
    const card = document.createElement("div");

    const ledActive = isLedOn(cell);
    const matrixExists = hasMatrix(cell);
    const isFullyActive = ledActive && matrixExists;

    card.className = `cell-card ${isFullyActive ? 'is-active-on' : ''}`;

    const nextCommand = ledActive ? 'OFF' : 'ON';
    const toggleButtonClass = ledActive ? 'power-on' : 'power-off';
    const toggleButtonText = ledActive ? '⚡ ИЗКЛЮЧИ' : '💡 ВКЛЮЧИ';
    const statusText = ledActive ? '🟢 ВКЛЮЧЕН' : '⚪ ИЗКЛЮЧЕН';

    const cellName = cell.cell_code || `S1R${cell.id}`;
    const snCode = cell.sn_code || cell.serial_number || '';
    const moldCode = cell.mold_code || '';
    
    const gpioPin = cell.gpio_pin;
    const gpioDisplay = (gpioPin !== undefined && gpioPin !== null && gpioPin !== '') 
        ? `LED ${gpioPin}` 
        : 'LED N/A';

    // Подготовка на допълнителната инфо линия (SN / Mold)
    let extraDetails = [];
    if (snCode) extraDetails.push(`SN: ${snCode}`);
    if (moldCode) extraDetails.push(`Mold: ${moldCode}`);
    const detailsSubtext = extraDetails.length > 0 ? extraDetails.join(' | ') : `Идентификатор: ${cellName}`;

    card.innerHTML = `
        <div class="cell-meta">
            <span class="cell-number-badge">${cellName}</span>
            <div class="cell-meta-right">
                <span class="gpio-badge">${gpioDisplay}</span>
                <span class="lock-pill ${ledActive ? 'unlocked' : ''}">
                    ${statusText}
                </span>
            </div>
        </div>
        <div class="cell-core-data">
            <span class="matrix-id">${cell.matrix_name || 'ПРАЗНА КЛЕТКА'}</span>
            <span class="cell-subtext">${detailsSubtext}</span>
        </div>
        <div class="cell-actions-row">
            <button type="button" class="btn-toggle-power ${toggleButtonClass}" onclick="toggleRelayState(event, '${gpioPin || ''}', '${cellName}', '${nextCommand}')">
                ${toggleButtonText}
            </button>
            <button type="button" class="btn-action-config" onclick="openConfig(${cell.id})">⚙️</button>
        </div>
    `;
    return card;
}

// 🔍 Търсачка по Име, Матрица, SN, Mold Code или LED Pin
function filterRackGrid() {
    const searchVal = document.getElementById("search-input").value.toLowerCase().trim();
    const container = document.getElementById("rack-matrix-container");
    if (!container) return;

    container.innerHTML = "";

    currentCellsData.forEach((cell) => {
        const cellName = (cell.cell_code || `клетка ${cell.id}`).toLowerCase();
        const matrixName = (cell.matrix_name || '').toLowerCase();
        const snCode = (cell.sn_code || cell.serial_number || '').toLowerCase();
        const moldCode = (cell.mold_code || '').toLowerCase();
        const barcode = (cell.barcode || '').toLowerCase();
        const gpioCode = `led ${cell.gpio_pin} gpio ${cell.gpio_pin}`.toLowerCase();

        if (cellName.includes(searchVal) || 
            matrixName.includes(searchVal) || 
            snCode.includes(searchVal) ||
            moldCode.includes(searchVal) ||
            barcode.includes(searchVal) ||
            gpioCode.includes(searchVal)) {
            
            const cellCard = createCellCardElement(cell);
            container.appendChild(cellCard);
        }
    });
}

// ⚡ Изпращане на команда ON/OFF към api.php?action=trigger_relay
async function toggleRelayState(evt, gpioPin, cellCode, command) {
    const btn = evt ? evt.currentTarget : null;
    if (btn) btn.disabled = true;

    isSendingCommand = true; // Спираме временно авто-опресняването

    const parsedPin = (gpioPin !== undefined && gpioPin !== null && gpioPin !== '' && gpioPin !== 'null') ? parseInt(gpioPin) : 0;

    try {
        const response = await fetch(`api.php?action=trigger_relay&token=${API_TOKEN}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                gpio_pin: parsedPin,
                cell_code: cellCode,
                command: command,
                user_name: currentUser
            })
        });

        const result = await response.json();
        if (result.success) {
            console.log(`🚀 Командата ${command} за ${cellCode} бе регистрирана!`, result);
            await loadCellsFromDatabase();
        } else {
            alert(result.message || "Грешка при изпращане на командата!");
        }
    } catch (err) {
        console.error("Грешка при превключване:", err);
    } finally {
        if (btn) btn.disabled = false;
        setTimeout(() => { isSendingCommand = false; }, 1000); // Възстановяваме авто-опресняването
    }
}

// 🚨 Изключване на всички светодиоди
async function turnOffAllLeds() {
    if (!confirm("Сигурни ли сте, че искате да изключите всички светодиоди?")) return;

    try {
        const response = await fetch(`api.php?action=turn_off_all&token=${API_TOKEN}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_name: currentUser })
        });

        const result = await response.json();
        if (result.success) {
            await loadCellsFromDatabase();
        }
    } catch (err) {
        console.error("Грешка при turn_off_all:", err);
    }
}

// 👤 Изключване на LED за конкретен оператор
async function turnOffByUser(targetOperatorName) {
    const target = targetOperatorName || currentUser;
    if (!confirm(`Искате ли да изключите всички LED, пуснати от ${target}?`)) return;

    try {
        const response = await fetch(`api.php?action=turn_off_by_user&token=${API_TOKEN}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                target_user: target,
                user_name: currentUser 
            })
        });

        const result = await response.json();
        if (result.success) {
            await loadCellsFromDatabase();
        } else {
            alert(result.message || "Няма активни клетки за този оператор.");
        }
    } catch (err) {
        console.error("Грешка при turn_off_by_user:", err);
    }
}

// Зареждане на логове
async function loadLogsFromDatabase() {
    try {
        const response = await fetch(`api.php?action=get_logs&token=${API_TOKEN}`);
        const data = await response.json();

        if (data.success) {
            renderLogsTable(data.logs);
        }
    } catch (err) {
        console.error("Грешка при зареждане на логовете:", err);
    }
}

function renderLogsTable(logs) {
    const tableBody = document.getElementById("log-table-body");
    if (!tableBody) return;

    tableBody.innerHTML = logs.map(log => `
        <tr>
            <td><code>#${log.id}</code></td>
            <td>${log.timestamp || log.created_at || ''}</td>
            <td><strong>${log.user_name}</strong></td>
            <td><span style="background: #e2e8f0; padding: 4px 8px; border-radius: 6px; font-weight: 800; color: #003896;">${log.cell_code}</span></td>
            <td style="color: ${log.action === 'LED_ON' ? '#10b981' : '#ef4444'}; font-weight: 800;">${log.action}</td>
        </tr>
    `).join('');
}

function switchPage(page) {
    currentPage = page;
    const rackPage = document.getElementById("page-rack");
    const archivePage = document.getElementById("page-archive");
    const btnRack = document.getElementById("tab-btn-rack");
    const btnArchive = document.getElementById("tab-btn-archive");

    if (page === 'rack') {
        if (rackPage) rackPage.style.display = "block";
        if (archivePage) archivePage.style.display = "none";
        if (btnRack) btnRack.classList.add("active");
        if (btnArchive) btnArchive.classList.remove("active");
        loadCellsFromDatabase();
    } else {
        if (rackPage) rackPage.style.display = "none";
        if (archivePage) archivePage.style.display = "block";
        if (btnRack) btnRack.classList.remove("active");
        if (btnArchive) btnArchive.classList.add("active");
        loadLogsFromDatabase();
    }
}

function updateStats() {
    const activeCount = currentCellsData.filter(c => isLedOn(c)).length;
    const totalCount = currentCellsData.length || 0;
    const statElem = document.getElementById("stat-available");
    if (statElem) {
        statElem.innerText = `${activeCount}/${totalCount}`;
    }
}

// Вход в системата
async function attemptLogin() {
    const userInput = document.getElementById("login-username");
    const passwordInput = document.getElementById("login-password");
    const rememberCheckbox = document.getElementById("remember-me");
    const errorDiv = document.getElementById("login-error");

    if (errorDiv) {
        errorDiv.style.display = "none";
        errorDiv.innerText = "";
    }

    const username = userInput ? userInput.value.trim() : "";
    const password = passwordInput ? passwordInput.value.trim() : "";

    try {
        const response = await fetch(`api.php?action=login&token=${API_TOKEN}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username, password: password })
        });

        const result = await response.json();

        if (result.success) {
            currentUser = result.user;

            if (rememberCheckbox && rememberCheckbox.checked) {
                localStorage.setItem("ottobock_user", currentUser);
            } else {
                localStorage.removeItem("ottobock_user");
            }

            const userTag = document.getElementById("current-user-tag");
            if (userTag) userTag.innerText = currentUser;

            document.getElementById("login-screen").style.display = "none";
            document.getElementById("app-content").style.display = "block";
            initApp();
        } else {
            if (errorDiv) {
                errorDiv.innerText = result.message || "Грешно потребителско име или парола!";
                errorDiv.style.display = "block";
            }
        }
    } catch (err) {
        console.error("Грешка при вход:", err);
        if (errorDiv) {
            errorDiv.innerText = "Грешка при връзка със сървъра!";
            errorDiv.style.display = "block";
        }
    }
}

function logout() {
    stopAutoRefresh();
    localStorage.removeItem("ottobock_user");
    document.getElementById("login-screen").style.display = "flex";
    document.getElementById("app-content").style.display = "none";
}

// Конфигурация на клетка (Отваряне на модала)
function openConfig(cellId) {
    const cell = currentCellsData.find(c => parseInt(c.id) === parseInt(cellId));
    if (!cell) return;

    const cellName = cell.cell_code || `Клетка ${cell.id}`;

    // Зареждане на основните полета
    if (document.getElementById("cfg-cell-id")) document.getElementById("cfg-cell-id").value = cell.id;
    if (document.getElementById("cfg-cell-title")) document.getElementById("cfg-cell-title").innerText = cellName.toUpperCase();
    if (document.getElementById("cfg-matrix-name")) document.getElementById("cfg-matrix-name").value = cell.matrix_name || '';
    if (document.getElementById("cfg-lock-pin")) document.getElementById("cfg-lock-pin").value = cell.gpio_pin !== null ? cell.gpio_pin : '';
    
    // Поддръжка за новите полета
    if (document.getElementById("cfg-cell-code")) document.getElementById("cfg-cell-code").value = cell.cell_code || '';
    if (document.getElementById("cfg-sn-code")) document.getElementById("cfg-sn-code").value = cell.sn_code || cell.serial_number || '';
    if (document.getElementById("cfg-mold-code")) document.getElementById("cfg-mold-code").value = cell.mold_code || '';
    if (document.getElementById("cfg-barcode")) document.getElementById("cfg-barcode").value = cell.barcode || '';

    const modal = document.getElementById("config-modal");
    if (modal) modal.style.display = "flex";
}

function closeConfig() {
    const modal = document.getElementById("config-modal");
    if (modal) modal.style.display = "none";
}

// Запазване на данните за клетката
async function saveCellConfig() {
    const id = parseInt(document.getElementById(    "cfg-cell-id").value);
    const matrixName = document.getElementById("cfg-matrix-name")?.value || '';
    const gpioPinInput = document.getElementById("cfg-lock-pin")?.value;
    const gpioPin = (gpioPinInput !== undefined && gpioPinInput !== '') ? parseInt(gpioPinInput) : null;

    const cellCodeInput = document.getElementById("cfg-cell-code")?.value;
    const snCodeInput = document.getElementById("cfg-sn-code")?.value;
    const moldCodeInput = document.getElementById("cfg-mold-code")?.value;
    const barcodeInput = document.getElementById("cfg-barcode")?.value;

    const payload = { 
        id, 
        matrix_name: matrixName, 
        gpio_pin: gpioPin 
    };

    if (cellCodeInput !== undefined) payload.cell_code = cellCodeInput;
    if (snCodeInput !== undefined) payload.sn_code = snCodeInput;
    if (moldCodeInput !== undefined) payload.mold_code = moldCodeInput;
    if (barcodeInput !== undefined) payload.barcode = barcodeInput;

    try {
        const response = await fetch(`api.php?action=save_cell&token=${API_TOKEN}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            closeConfig();
            loadCellsFromDatabase();
        } else {
            alert(result.message || "Грешка при запазване на клетката!");
        }
    } catch (err) {
        console.error("Грешка при запазване:", err);
    }
}