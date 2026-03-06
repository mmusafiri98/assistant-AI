<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ===== ELABORAZIONE DEL MODULO =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $how_found  = htmlspecialchars(trim($_POST['how_found']  ?? ''));
    $level      = htmlspecialchars(trim($_POST['level']      ?? ''));
    $goal       = htmlspecialchars(trim($_POST['goal']       ?? ''));
    $duration   = htmlspecialchars(trim($_POST['duration']   ?? ''));
    $motivation = htmlspecialchars(trim($_POST['motivation'] ?? ''));
    $skills     = isset($_POST['skills']) && is_array($_POST['skills'])
                    ? implode(", ", array_map('trim', $_POST['skills'])) : '';
    $accent     = htmlspecialchars(trim($_POST['accent']     ?? ''));
    $days       = intval($_POST['days']    ?? 0);
    $minutes    = intval($_POST['minutes'] ?? 0);

    if ($how_found === '' || $level === '' || $goal === '' || $duration === '' || $motivation === '' || $accent === '') {
        $_SESSION['quiz_error'] = "Per favore compila tutti i campi obbligatori.";
    } else {
        try {
            $dsn         = "pgsql:host=ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech;port=5432;dbname=veronica_db_login;sslmode=require";
            $username_db = "neondb_owner";
            $password_db = "npg_QolPDv5L9gVj";

            $conn = new PDO($dsn, $username_db, $password_db, [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $sql = "INSERT INTO user_quiz
                        (username, how_found, level, goal, duration, motivation, skills, accent, days, minutes)
                    VALUES
                        (:username, :how_found, :level, :goal, :duration, :motivation, :skills, :accent, :days, :minutes)";

            $stmt     = $conn->prepare($sql);
            $username = $_SESSION['username'] ?? 'ospite';

            $stmt->execute([
                ':username'   => $username,
                ':how_found'  => $how_found,
                ':level'      => $level,
                ':goal'       => $goal,
                ':duration'   => $duration,
                ':motivation' => $motivation,
                ':skills'     => $skills,
                ':accent'     => $accent,
                ':days'       => $days,
                ':minutes'    => $minutes,
            ]);

            $conn = null;
            header("Location: thankyou.php");
            exit;

        } catch (PDOException $e) {
            error_log("Errore DB: " . $e->getMessage());
            $_SESSION['quiz_error'] = "Si è verificato un errore durante il salvataggio del profilo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Quiz Linguistico – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #009246 0%, #ffffff 50%, #ce2b37 100%);
            background-size: 300% 300%;
            animation: gradientShift 12s ease infinite;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1e293b;
            padding: 30px 16px;
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .quiz-container {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 28px;
            padding: 48px 44px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 16px 56px rgba(0, 0, 0, 0.14);
            animation: fadeIn 0.7s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── HEADER ── */
        .quiz-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .quiz-header .flag-banner {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .flag-stripe {
            height: 6px;
            border-radius: 3px;
            flex: 1;
            max-width: 60px;
        }
        .flag-stripe.green  { background: #009246; }
        .flag-stripe.white  { background: #ddd; }
        .flag-stripe.red    { background: #ce2b37; }
        .quiz-header .logo  { font-size: 2.6rem; }
        .quiz-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.35;
            margin-top: 8px;
        }
        .quiz-header p {
            font-size: 0.83rem;
            color: #6b7280;
            margin-top: 5px;
        }

        /* ── PROGRESS BAR ── */
        .progress-wrap {
            margin-bottom: 26px;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.74rem;
            color: #9ca3af;
            margin-bottom: 7px;
            font-weight: 500;
        }
        .progress-track {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #009246, #ce2b37);
            border-radius: 4px;
            transition: width 0.4s ease;
        }

        /* ── VERONICA MSG ── */
        .veronica-message {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: linear-gradient(135deg, rgba(0,146,70,0.07), rgba(206,43,55,0.05));
            border-left: 4px solid #009246;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 30px;
            font-size: 0.93rem;
            line-height: 1.7;
            color: #374151;
        }
        .veronica-message .avatar {
            font-size: 2rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ── ALERT ── */
        .alert-error {
            background: rgba(220, 38, 38, 0.09);
            color: #b91c1c;
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-weight: 600;
            font-size: 0.88rem;
            border-left: 4px solid #ef4444;
        }

        /* ── SECTION TITLE ── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #9ca3af;
            margin: 26px 0 18px;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* ── FIELD ── */
        .field { margin-bottom: 18px; }

        label {
            font-weight: 600;
            font-size: 0.875rem;
            display: block;
            margin-bottom: 7px;
            color: #111827;
        }
        label .required {
            color: #ce2b37;
            margin-left: 3px;
        }
        label .hint {
            font-weight: 400;
            font-size: 0.76rem;
            color: #9ca3af;
            margin-left: 6px;
        }

        select, textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            background: #f9fafb;
            font-family: "Inter", sans-serif;
            font-size: 0.92rem;
            color: #111827;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23009246' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-color: #f9fafb;
            padding-right: 40px;
            cursor: pointer;
        }
        select:focus, textarea:focus {
            border-color: #009246;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,146,70,0.12);
        }
        textarea {
            height: 95px;
            resize: vertical;
            line-height: 1.7;
        }

        /* ── PRACTICE ── */
        .practice-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .input-unit-wrap {
            position: relative;
        }
        .input-unit-wrap input[type="number"] {
            width: 100%;
            padding: 12px 58px 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            background: #f9fafb;
            font-family: "Inter", sans-serif;
            font-size: 0.92rem;
            color: #111827;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            -moz-appearance: textfield;
        }
        .input-unit-wrap input[type="number"]::-webkit-inner-spin-button,
        .input-unit-wrap input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
        .input-unit-wrap input[type="number"]:focus {
            border-color: #009246;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,146,70,0.12);
        }
        .input-unit-wrap .unit-label {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.72rem;
            font-weight: 600;
            color: #009246;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ── CHART ── */
        .chart-wrap {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 20px;
            margin-top: 16px;
        }
        .chart-wrap .chart-title {
            font-size: 0.74rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }

        /* ── SKILLS ── */
        .skills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .skill-card {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.18s;
            font-size: 0.84rem;
            font-weight: 500;
            color: #374151;
            user-select: none;
        }
        .skill-card:hover {
            background: rgba(0,146,70,0.06);
            border-color: #009246;
            color: #111827;
        }
        .skill-card input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #009246;
            flex-shrink: 0;
        }
        .skill-card input[type="checkbox"]:checked + span {
            color: #111827;
            font-weight: 600;
        }

        /* ── SUBMIT ── */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #009246, #007a3a);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: "Inter", sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 18px rgba(0,146,70,0.3);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }
        .submit-btn:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,146,70,0.4);
        }
        .submit-btn:active { transform: translateY(0); }

        /* ── FOOTER ── */
        .quiz-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.79rem;
            color: #9ca3af;
        }

        /* ── CHAR COUNTER ── */
        .char-counter {
            text-align: right;
            font-size: 0.72rem;
            color: #d1d5db;
            margin-top: 5px;
            transition: color 0.2s;
        }
        .char-counter.warn { color: #f59e0b; }
        .char-counter.ok   { color: #009246; }

        @media (max-width: 560px) {
            .quiz-container { padding: 28px 20px; }
            .skills-grid    { grid-template-columns: 1fr; }
            .practice-row   { grid-template-columns: 1fr; }
            .quiz-header h2 { font-size: 1.35rem; }
        }
    </style>
</head>
<body>

<div class="quiz-container">

    <!-- Header -->
    <div class="quiz-header">
        <div class="flag-banner">
            <div class="flag-stripe green"></div>
            <span class="logo">🇫🇷</span>
            <div class="flag-stripe white"></div>
            <span style="font-size:1.4rem;">🇮🇹</span>
            <div class="flag-stripe red"></div>
        </div>
        <h2>Il tuo Profilo Linguistico<br>Veronica AI</h2>
        <p>Personalizza il tuo percorso di apprendimento del francese</p>
    </div>

    <!-- Progress bar -->
    <div class="progress-wrap">
        <div class="progress-label">
            <span id="progressText">Progresso: 0 / 6</span>
            <span id="progressPct">0%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <!-- Veronica message -->
    <div class="veronica-message">
        <span class="avatar">🤖</span>
        <span id="veronicaMsg">
            Ciao! Sono <strong>Veronica AI</strong>, la tua coach linguistica personale! 🌟<br>
            Rispondi a queste domande per creare il tuo piano di apprendimento del francese su misura 🇫🇷
        </span>
    </div>

    <!-- Error -->
    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <!-- Form -->
    <form method="post" id="quizForm">

        <!-- Sezione 1 -->
        <div class="section-title">📍 Come ci hai trovato</div>

        <div class="field">
            <label for="how_found">Come hai conosciuto Veronica AI? <span class="required">*</span></label>
            <select name="how_found" id="how_found" required onchange="trackProgress()">
                <option value="">-- Seleziona una risposta --</option>
                <option>Tramite un amico</option>
                <option>Sui social media (Instagram, TikTok…)</option>
                <option>Tramite una pubblicità</option>
                <option>Ricerca su Internet</option>
                <option>Altro</option>
            </select>
        </div>

        <!-- Sezione 2 -->
        <div class="section-title">📊 Il tuo livello attuale</div>

        <div class="field">
            <label for="level">Qual è il tuo livello attuale di francese? <span class="required">*</span></label>
            <select name="level" id="level" required onchange="trackProgress()">
                <option value="">-- Scegli il tuo livello --</option>
                <option>Principiante (A1–A2)</option>
                <option>Intermedio (B1–B2)</option>
                <option>Avanzato (C1–C2)</option>
                <option>Non so valutarmi</option>
            </select>
        </div>

        <div class="field">
            <label for="goal">Perché vuoi imparare il francese? <span class="required">*</span></label>
            <select name="goal" id="goal" required onchange="trackProgress()">
                <option value="">-- Il tuo obiettivo principale --</option>
                <option>Viaggiare in un paese francofono</option>
                <option>Studiare in Francia / Canada</option>
                <option>Motivi professionali</option>
                <option>Cultura e passione per la lingua</option>
                <option>Altro</option>
            </select>
        </div>

        <!-- Sezione 3 -->
        <div class="section-title">🎯 Stile di apprendimento</div>

        <div class="field">
            <label for="accent">Quale accento preferisci imparare? <span class="required">*</span></label>
            <select name="accent" id="accent" required onchange="trackProgress()">
                <option value="">-- Scegli un accento --</option>
                <option>Francese di Parigi 🇫🇷</option>
                <option>Francese del Canada 🇨🇦</option>
                <option>Francese del Belgio 🇧🇪</option>
            </select>
        </div>

        <div class="field">
            <label for="duration">In quanto tempo vuoi raggiungere il tuo obiettivo? <span class="required">*</span></label>
            <select name="duration" id="duration" required onchange="trackProgress()">
                <option value="">-- Scegli una durata --</option>
                <option>Meno di 3 mesi</option>
                <option>Da 3 a 6 mesi</option>
                <option>Da 6 a 12 mesi</option>
                <option>Più di un anno</option>
            </select>
        </div>

        <!-- Sezione 4 -->
        <div class="section-title">⏱️ Piano di pratica</div>

        <div class="field">
            <label>Quanto vuoi esercitarti? <span class="hint">(facoltativo)</span></label>
            <div class="practice-row">
                <div class="input-unit-wrap">
                    <input type="number" name="days" id="days" min="1" max="7" placeholder="Giorni">
                    <span class="unit-label">giorni/sett.</span>
                </div>
                <div class="input-unit-wrap">
                    <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minuti">
                    <span class="unit-label">min/giorno</span>
                </div>
            </div>
            <div class="chart-wrap">
                <div class="chart-title">📈 Stima del tempo di pratica</div>
                <canvas id="practiceChart" height="150"></canvas>
            </div>
        </div>

        <!-- Sezione 5 -->
        <div class="section-title">💡 Competenze da sviluppare</div>

        <div class="field">
            <label>Quali competenze vuoi migliorare? <span class="hint">(selezione multipla)</span></label>
            <div class="skills-grid">
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Colloquio di lavoro">
                    <span>💼 Colloquio di lavoro</span>
                </label>
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Presentazione orale">
                    <span>🎤 Presentazione orale</span>
                </label>
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Negoziazione">
                    <span>🤝 Negoziazione</span>
                </label>
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Riunioni e conferenze">
                    <span>📋 Riunioni e conferenze</span>
                </label>
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Conversazione quotidiana">
                    <span>💬 Conversazione quotidiana</span>
                </label>
                <label class="skill-card">
                    <input type="checkbox" name="skills[]" value="Francese da viaggio">
                    <span>✈️ Francese da viaggio</span>
                </label>
            </div>
        </div>

        <!-- Sezione 6 -->
        <div class="section-title">✍️ La tua motivazione</div>

        <div class="field">
            <label for="motivation">Raccontaci la tua motivazione per imparare il francese <span class="required">*</span></label>
            <textarea
                name="motivation"
                id="motivation"
                required
                maxlength="400"
                placeholder="Es.: Amo la cultura francese, voglio vivere a Parigi, ho bisogno del francese per lavoro…"
                oninput="updateCharCounter(); trackProgress()"></textarea>
            <div class="char-counter" id="charCounter">0 / 400 caratteri</div>
        </div>

        <button type="submit" class="submit-btn">
            🇮🇹 Invia il mio profilo
        </button>

    </form>

    <div class="quiz-footer">
        🤖 Veronica AI – La tua coach linguistica intelligente
    </div>

</div>

<script>
// ── Progress bar ──
const TOTAL_FIELDS = 6;
const trackedIds   = ['how_found', 'level', 'goal', 'accent', 'duration', 'motivation'];

function trackProgress() {
    const filled = trackedIds.filter(id => {
        const el = document.getElementById(id);
        return el && el.value.trim() !== '';
    }).length;

    const pct  = Math.round((filled / TOTAL_FIELDS) * 100);
    document.getElementById('progressFill').style.width  = pct + '%';
    document.getElementById('progressPct').textContent   = pct + '%';
    document.getElementById('progressText').textContent  = `Progresso: ${filled} / ${TOTAL_FIELDS}`;
}

// ── Character counter ──
function updateCharCounter() {
    const ta  = document.getElementById('motivation');
    const cnt = document.getElementById('charCounter');
    const len = ta.value.length;
    cnt.textContent = len + ' / 400 caratteri';
    cnt.className   = 'char-counter' + (len >= 300 ? (len >= 380 ? ' warn' : ' ok') : '');
}

// ── Chart ──
const chartCtx = document.getElementById('practiceChart');
let chart;

function updateChart() {
    const days    = parseInt(document.getElementById('days').value)    || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const weekly  = days * minutes;
    const monthly = weekly * 4;

    const isEmpty = weekly === 0 && monthly === 0;

    const data = {
        labels: ['Minuti / settimana', 'Minuti / mese'],
        datasets: [{
            label: 'Tempo di pratica stimato',
            data: isEmpty ? [0, 0] : [weekly, monthly],
            backgroundColor: ['rgba(0,146,70,0.7)', 'rgba(206,43,55,0.65)'],
            borderColor:     ['#009246', '#ce2b37'],
            borderWidth: 2,
            borderRadius: 8,
        }]
    };

    if (chart) chart.destroy();
    chart = new Chart(chartCtx, {
        type: 'bar',
        data,
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y + ' min'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid:  { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { family: 'Inter', size: 11 }, color: '#6b7280' }
                },
                x: {
                    grid:  { display: false },
                    ticks: { font: { family: 'Inter', size: 11 }, color: '#6b7280' }
                }
            }
        }
    });
}

document.getElementById('days').addEventListener('input', updateChart);
document.getElementById('minutes').addEventListener('input', updateChart);

// ── Speech synthesis ──
window.onload = () => {
    const intro = "Ciao! Sono Veronica AI, la tua coach linguistica personale. Rispondi a queste domande per personalizzare il tuo apprendimento del francese.";

    if ('speechSynthesis' in window) {
        let voiced = false;
        function speak() {
            if (voiced) return;
            const voices  = window.speechSynthesis.getVoices();
            const itVoice = voices.find(v => v.lang.startsWith('it')) || null;
            const utter   = new SpeechSynthesisUtterance(intro);
            utter.lang    = 'it-IT';
            utter.rate    = 0.95;
            if (itVoice) utter.voice = itVoice;
            window.speechSynthesis.speak(utter);
            voiced = true;
        }
        window.speechSynthesis.getVoices().length
            ? speak()
            : (window.speechSynthesis.onvoiceschanged = speak);
    }

    updateChart();
    trackProgress();
};
</script>

</body>
</html>
<?php ob_end_flush(); ?>
