<?php
// ==============================================================================
// DOCTOR CONNECT - LIVE CLINIC DISPLAY BOARD (WAITING ROOM MONITOR)
// Designed for clinic display screens / TVs with sub-3s auto refresh & bell chime
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getConnection();
$today = date('Y-m-d');

$stmt = $db->query("
    SELECT d.id, d.specialization, u.name as doctor_name
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    ORDER BY d.id ASC
");
$doctors = $stmt->fetchAll();

$clinicName = getClinicSetting($db, 'clinic_name', 'DoctorConnect Clinic');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($clinicName) ?> - Live Queue Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>
        body {
            background-color: #0b1120;
            color: #ffffff;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .monitor-header {
            background: #0f172a;
            border-bottom: 2px solid #1e293b;
            padding: 1.25rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .monitor-clock {
            font-size: 1.85rem;
            font-weight: 800;
            font-family: monospace;
            color: #38bdf8;
            letter-spacing: 0.05em;
        }
        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 1.75rem;
            padding: 2rem 2.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }
        .doctor-board-card {
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 16px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            transition: border-color 0.2s;
        }
        .doctor-board-card.has-serving {
            border-color: #10b981;
        }
        .serving-giant-token {
            background: linear-gradient(135deg, #0284c7, #0f766e);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin: 1.25rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .serving-giant-token .number {
            font-size: 4.5rem;
            font-weight: 900;
            line-height: 1;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>

<header class="monitor-header">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div class="brand-icon" style="width:44px; height:44px; font-size:1.5rem;">⚕</div>
        <div>
            <h1 style="font-size:1.6rem; font-weight:800; margin:0; color:#ffffff;"><?= e($clinicName) ?></h1>
            <div style="font-size:0.85rem; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                <span class="pulse-dot"></span> Live Queue Station &bull; Auto-syncing every 3s
            </div>
        </div>
    </div>

    <div style="display:flex; align-items:center; gap:2rem;">
        <div id="live-clock" class="monitor-clock">--:--:--</div>
        <button type="button" class="btn btn-outline btn-sm" onclick="toggleFullScreen()" style="color:#ffffff; border-color:#475569;">
            ⛶ Fullscreen
        </button>
    </div>
</header>

<main class="monitor-grid" id="monitor-cards-container">
    <?php foreach ($doctors as $doc): 
        $q = getDoctorLiveQueue($db, (int)$doc['id'], $today);
    ?>
        <div class="doctor-board-card <?= $q['current_serving_token'] ? 'has-serving' : '' ?>" id="doc-card-<?= $doc['id'] ?>">
            <div>
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <span class="badge" style="background:#0369a1; color:#ffffff; margin-bottom:0.4rem; font-size:0.8rem;">
                            <?= e($doc['specialization']) ?>
                        </span>
                        <h2 style="font-size:1.4rem; font-weight:800; margin:0; color:#f8fafc;">
                            <?= e($doc['doctor_name']) ?>
                        </h2>
                    </div>
                    <?php if ($q['current_serving_token']): ?>
                        <span class="badge badge-serving" style="font-size:0.8rem;">
                            <span class="pulse-dot"></span> NOW SERVING
                        </span>
                    <?php endif; ?>
                </div>

                <!-- SERVING TOKEN -->
                <div class="serving-giant-token">
                    <div style="font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:#bae6fd; margin-bottom:4px;">
                        TOKEN BEING CALLED
                    </div>
                    <div class="number" id="serving-<?= $doc['id'] ?>">
                        <?= $q['current_serving_token'] ? '#' . $q['current_serving_token'] : '---' ?>
                    </div>
                    <div style="font-size:0.9rem; color:#e0f2fe; margin-top:4px;" id="patient-<?= $doc['id'] ?>">
                        <?= $q['current_serving'] ? e($q['current_serving']['patient_name']) : 'Doctor is waiting for next patient' ?>
                    </div>
                </div>
            </div>

            <!-- QUEUE FOOTER STATS -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; padding:0.85rem 1.25rem; border-radius:8px; font-size:0.95rem; border:1px solid #334155;">
                <div>
                    <span style="color:#94a3b8; font-size:0.8rem; display:block;">NEXT IN LINE</span>
                    <strong style="color:#f59e0b; font-size:1.15rem;" id="next-<?= $doc['id'] ?>">
                        <?= $q['next_waiting_token'] ? '#' . $q['next_waiting_token'] : 'None' ?>
                    </strong>
                </div>
                <div style="text-align:right;">
                    <span style="color:#94a3b8; font-size:0.8rem; display:block;">WAITING IN ROOM</span>
                    <strong style="color:#38bdf8; font-size:1.15rem;" id="waiting-<?= $doc['id'] ?>">
                        <?= $q['waiting_count'] ?> patients
                    </strong>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    document.getElementById('live-clock').textContent = now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

function toggleFullScreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
    }
}

// Audio synthesizer chime
let audioCtx = null;
function playChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!audioCtx) audioCtx = new AudioContext();
        if (audioCtx.state === 'suspended') audioCtx.resume();

        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now); // D5
        osc.frequency.setValueAtTime(880.00, now + 0.2); // A5
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + 0.8);
    } catch(e) {}
}

const doctorIds = <?= json_encode(array_column($doctors, 'id')) ?>;
const lastTokens = {};

async function pollAllDoctors() {
    for (const docId of doctorIds) {
        try {
            const res = await fetch(`<?= url("api/live_queue.php") ?>?doctor_id=${docId}`);
            const data = await res.json();
            if (data.success) {
                const servingText = data.current_serving_token ? '#' + data.current_serving_token : '---';
                const sElem = document.getElementById(`serving-${docId}`);
                if (sElem) sElem.textContent = servingText;

                const pElem = document.getElementById(`patient-${docId}`);
                if (pElem) {
                    pElem.textContent = data.current_serving ? data.current_serving.patient_name : 'Doctor is waiting for next patient';
                }

                const nElem = document.getElementById(`next-${docId}`);
                if (nElem) nElem.textContent = data.next_waiting_token ? '#' + data.next_waiting_token : 'None';

                const wElem = document.getElementById(`waiting-${docId}`);
                if (wElem) wElem.textContent = data.waiting_count + ' patients';

                // If token changed, chime
                if (lastTokens[docId] !== undefined && lastTokens[docId] !== data.current_serving_token && data.current_serving_token) {
                    playChime();
                }
                lastTokens[docId] = data.current_serving_token;
            }
        } catch(e) {}
    }
}

setInterval(pollAllDoctors, 3000);
document.body.addEventListener('click', () => {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
}, { once: true });
</script>

</body>
</html>
