<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ===== FORMULARVERARBEITUNG =====
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
        $_SESSION['quiz_error'] = "Bitte füllen Sie alle Pflichtfelder aus.";
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
            $username = $_SESSION['username'] ?? 'Gast';

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
            error_log("DB-Fehler: " . $e->getMessage());
            $_SESSION['quiz_error'] = "Beim Speichern Ihres Profils ist ein Fehler aufgetreten.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sprachquiz – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "IBM Plex Sans", sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #e5e7eb;
            padding: 30px 16px;
            position: relative;
            overflow-x: hidden;
        }

        /* ── Hintergrund-Dekoration ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 10% 20%, rgba(0,0,0,0.0) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 80%, rgba(255,206,0,0.08) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 50% 50%, rgba(220,0,0,0.04) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Streifen oben (Deutschlandfahne) ── */
        .flag-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            display: flex;
            z-index: 999;
        }
        .flag-bar span { flex: 1; }
        .flag-bar .s1 { background: #1a1a1a; }
        .flag-bar .s2 { background: #DD0000; }
        .flag-bar .s3 { background: #FFCE00; }

        .quiz-container {
            position: relative;
            z-index: 1;
            background: #111111;
            border: 1px solid #1f1f1f;
            border-radius: 20px;
            padding: 50px 46px;
            width: 100%;
            max-width: 710px;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04),
                0 24px 64px rgba(0,0,0,0.7),
                0 0 80px rgba(221,0,0,0.05);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── HEADER ── */
        .quiz-header { text-align: center; margin-bottom: 36px; }

        .de-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 30px;
            padding: 6px 18px;
            margin-bottom: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .de-badge .flag-mini {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .de-badge .flag-mini span {
            display: block;
            width: 22px;
            height: 3px;
            border-radius: 1px;
        }
        .de-badge .flag-mini .b { background: #1a1a1a; border: 1px solid #333; }
        .de-badge .flag-mini .r { background: #DD0000; }
        .de-badge .flag-mini .y { background: #FFCE00; }

        .quiz-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .quiz-header h1 span { color: #FFCE00; }
        .quiz-header p {
            font-size: 0.84rem;
            color: #4b5563;
            margin-top: 8px;
            letter-spacing: 0.3px;
        }

        /* ── FORTSCHRITTSBALKEN ── */
        .progress-wrap { margin-bottom: 32px; }
        .progress-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .progress-meta span {
            font-size: 0.72rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .progress-meta .pct { color: #FFCE00; }
        .progress-track {
            width: 100%;
            height: 4px;
            background: #1f1f1f;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #DD0000, #FFCE00);
            border-radius: 2px;
            transition: width 0.4s ease;
        }

        /* ── VERONICA MSG ── */
        .veronica-message {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            background: #161616;
            border: 1px solid #1f1f1f;
            border-left: 3px solid #DD0000;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 32px;
            font-size: 0.91rem;
            line-height: 1.75;
            color: #9ca3af;
        }
        .veronica-message .avatar {
            width: 40px;
            height: 40px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .veronica-message strong { color: #fff; }

        /* ── FEHLER ── */
        .alert-error {
            background: rgba(220,0,0,0.08);
            color: #f87171;
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 600;
            font-size: 0.87rem;
            border-left: 3px solid #DD0000;
        }

        /* ── ABSCHNITT ── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 28px 0 16px;
        }
        .section-label .icon {
            width: 28px;
            height: 28px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .section-label .text {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #4b5563;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #1f1f1f;
        }

        /* ── FELDER ── */
        .field { margin-bottom: 16px; }

        label.field-label {
            font-weight: 600;
            font-size: 0.84rem;
            display: block;
            margin-bottom: 8px;
            color: #d1d5db;
        }
        label.field-label .req  { color: #DD0000; margin-left: 3px; }
        label.field-label .opt  { color: #374151; font-size: 0.72rem; font-weight: 400; margin-left: 6px; }

        select, textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #1f1f1f;
            background: #161616;
            font-family: "IBM Plex Sans", sans-serif;
            font-size: 0.9rem;
            color: #e5e7eb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%23FFCE00' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-color: #161616;
            padding-right: 40px;
            cursor: pointer;
            color: #e5e7eb;
        }
        select option { background: #1a1a1a; color: #e5e7eb; }
        select:focus, textarea:focus {
            border-color: #DD0000;
            box-shadow: 0 0 0 3px rgba(221,0,0,0.12);
        }
        textarea {
            height: 95px;
            resize: vertical;
            line-height: 1.7;
        }

        /* ── ÜBUNGSPLAN ── */
        .practice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .num-wrap { position: relative; }
        .num-wrap input[type="number"] {
            width: 100%;
            padding: 12px 62px 12px 16px;
            border-radius: 10px;
            border: 1px solid #1f1f1f;
            background: #161616;
            font-family: "IBM Plex Sans", sans-serif;
            font-size: 0.9rem;
            color: #e5e7eb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            -moz-appearance: textfield;
        }
        .num-wrap input[type="number"]::-webkit-inner-spin-button,
        .num-wrap input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
        .num-wrap input[type="number"]:focus {
            border-color: #DD0000;
            box-shadow: 0 0 0 3px rgba(221,0,0,0.12);
        }
        .num-wrap .unit-tag {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.68rem;
            font-weight: 700;
            color: #FFCE00;
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 0.3px;
        }

        /* ── DIAGRAMM ── */
        .chart-box {
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            padding: 18px 20px;
            margin-top: 16px;
        }
        .chart-box-title {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #374151;
            margin-bottom: 14px;
        }

        /* ── KOMPETENZEN ── */
        .skills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .skill-tile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #161616;
            border: 1px solid #1f1f1f;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: border-color 0.18s, background 0.18s;
            font-size: 0.83rem;
            font-weight: 500;
            color: #9ca3af;
            user-select: none;
        }
        .skill-tile:hover {
            background: #1a1a1a;
            border-color: #DD0000;
            color: #e5e7eb;
        }
        .skill-tile input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #FFCE00;
            flex-shrink: 0;
        }

        /* ── ZEICHENZÄHLER ── */
        .char-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
        }
        .char-hint { font-size: 0.71rem; color: #374151; }
        .char-count { font-size: 0.71rem; font-weight: 600; color: #374151; transition: color 0.2s; }
        .char-count.ok   { color: #FFCE00; }
        .char-count.warn { color: #DD0000; }

        /* ── ABSENDEN ── */
        .submit-wrap { margin-top: 14px; }
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #DD0000;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: "IBM Plex Sans", sans-serif;
            font-size: 0.97rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(221,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .submit-btn:hover {
            background: #bb0000;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(221,0,0,0.35);
        }
        .submit-btn:active { transform: translateY(0); }

        .submit-note {
            text-align: center;
            margin-top: 12px;
            font-size: 0.74rem;
            color: #374151;
        }
        .submit-note a { color: #FFCE00; text-decoration: none; }

        /* ── FOOTER ── */
        .quiz-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #1a1a1a;
            font-size: 0.76rem;
            color: #374151;
        }

        @media (max-width: 580px) {
            .quiz-container  { padding: 30px 20px; }
            .skills-grid     { grid-template-columns: 1fr; }
            .practice-grid   { grid-template-columns: 1fr; }
            .quiz-header h1  { font-size: 1.55rem; }
        }
    </style>
</head>
<body>

<!-- Deutschlandfahne oben -->
<div class="flag-bar">
    <span class="s1"></span>
    <span class="s2"></span>
    <span class="s3"></span>
</div>

<div class="quiz-container">

    <!-- Header -->
    <div class="quiz-header">
        <div class="de-badge">
            <div class="flag-mini">
                <span class="b"></span>
                <span class="r"></span>
                <span class="y"></span>
            </div>
            Veronica AI · Deutsch
        </div>
        <h1>Dein <span>Sprachprofil</span></h1>
        <p>Personalisiere deinen Französisch-Lernweg in wenigen Schritten</p>
    </div>

    <!-- Fortschrittsbalken -->
    <div class="progress-wrap">
        <div class="progress-meta">
            <span id="progressLabel">Fortschritt: 0 von 6</span>
            <span class="pct" id="progressPct">0%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <!-- Veronica Nachricht -->
    <div class="veronica-message">
        <div class="avatar">🤖</div>
        <div id="veronicaMsg">
            Hallo! Ich bin <strong>Veronica AI</strong>, dein persönlicher Sprachcoach. 🎯<br>
            Beantworte diese Fragen, damit ich deinen Französischkurs maßgeschneidert auf dich abstimmen kann 🇫🇷
        </div>
    </div>

    <!-- Fehlermeldung -->
    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <!-- Formular -->
    <form method="post" id="quizForm">

        <!-- Abschnitt 1 -->
        <div class="section-label">
            <div class="icon">📍</div>
            <span class="text">Herkunft</span>
        </div>

        <div class="field">
            <label class="field-label" for="how_found">
                Wie hast du Veronica AI gefunden? <span class="req">*</span>
            </label>
            <select name="how_found" id="how_found" required onchange="trackProgress()">
                <option value="">-- Bitte wählen --</option>
                <option>Über einen Freund / eine Freundin</option>
                <option>In sozialen Medien (Instagram, TikTok …)</option>
                <option>Über eine Werbeanzeige</option>
                <option>Internetsuche</option>
                <option>Sonstiges</option>
            </select>
        </div>

        <!-- Abschnitt 2 -->
        <div class="section-label">
            <div class="icon">📊</div>
            <span class="text">Aktuelles Niveau</span>
        </div>

        <div class="field">
            <label class="field-label" for="level">
                Was ist dein aktuelles Französischniveau? <span class="req">*</span>
            </label>
            <select name="level" id="level" required onchange="trackProgress()">
                <option value="">-- Niveau auswählen --</option>
                <option>Anfänger (A1–A2)</option>
                <option>Mittelstufe (B1–B2)</option>
                <option>Fortgeschritten (C1–C2)</option>
                <option>Ich bin nicht sicher</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="goal">
                Warum möchtest du Französisch lernen? <span class="req">*</span>
            </label>
            <select name="goal" id="goal" required onchange="trackProgress()">
                <option value="">-- Hauptziel auswählen --</option>
                <option>Reisen in ein französischsprachiges Land</option>
                <option>Studium in Frankreich / Kanada</option>
                <option>Berufliche Gründe</option>
                <option>Kultur und Leidenschaft für die Sprache</option>
                <option>Sonstiges</option>
            </select>
        </div>

        <!-- Abschnitt 3 -->
        <div class="section-label">
            <div class="icon">🎯</div>
            <span class="text">Lernstil</span>
        </div>

        <div class="field">
            <label class="field-label" for="accent">
                Welchen Akzent möchtest du lernen? <span class="req">*</span>
            </label>
            <select name="accent" id="accent" required onchange="trackProgress()">
                <option value="">-- Akzent auswählen --</option>
                <option>Pariser Französisch 🇫🇷</option>
                <option>Kanadisches Französisch 🇨🇦</option>
                <option>Belgisches Französisch 🇧🇪</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="duration">
                In welchem Zeitraum möchtest du dein Ziel erreichen? <span class="req">*</span>
            </label>
            <select name="duration" id="duration" required onchange="trackProgress()">
                <option value="">-- Zeitraum auswählen --</option>
                <option>Weniger als 3 Monate</option>
                <option>3 bis 6 Monate</option>
                <option>6 bis 12 Monate</option>
                <option>Mehr als ein Jahr</option>
            </select>
        </div>

        <!-- Abschnitt 4 -->
        <div class="section-label">
            <div class="icon">⏱️</div>
            <span class="text">Übungsplan</span>
        </div>

        <div class="field">
            <label class="field-label">
                Wie viel möchtest du üben?
                <span class="opt">(optional)</span>
            </label>
            <div class="practice-grid">
                <div class="num-wrap">
                    <input type="number" name="days" id="days" min="1" max="7" placeholder="Tage">
                    <span class="unit-tag">Tage/Woche</span>
                </div>
                <div class="num-wrap">
                    <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minuten">
                    <span class="unit-tag">Min./Tag</span>
                </div>
            </div>
            <div class="chart-box">
                <div class="chart-box-title">📈 Geschätzte Übungszeit</div>
                <canvas id="practiceChart" height="150"></canvas>
            </div>
        </div>

        <!-- Abschnitt 5 -->
        <div class="section-label">
            <div class="icon">💡</div>
            <span class="text">Kompetenzen</span>
        </div>

        <div class="field">
            <label class="field-label">
                Welche Fähigkeiten möchtest du entwickeln?
                <span class="opt">(Mehrfachauswahl)</span>
            </label>
            <div class="skills-grid">
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Vorstellungsgespräch">
                    <span>💼 Vorstellungsgespräch</span>
                </label>
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Präsentation">
                    <span>🎤 Präsentation</span>
                </label>
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Verhandlung">
                    <span>🤝 Verhandlung</span>
                </label>
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Meetings und Konferenzen">
                    <span>📋 Meetings & Konferenzen</span>
                </label>
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Alltagsgespräche">
                    <span>💬 Alltagsgespräche</span>
                </label>
                <label class="skill-tile">
                    <input type="checkbox" name="skills[]" value="Reise-Französisch">
                    <span>✈️ Reise-Französisch</span>
                </label>
            </div>
        </div>

        <!-- Abschnitt 6 -->
        <div class="section-label">
            <div class="icon">✍️</div>
            <span class="text">Motivation</span>
        </div>

        <div class="field">
            <label class="field-label" for="motivation">
                Erzähl uns von deiner Motivation, Französisch zu lernen <span class="req">*</span>
            </label>
            <textarea
                name="motivation"
                id="motivation"
                required
                maxlength="400"
                placeholder="Z.B.: Ich liebe die französische Kultur, möchte in Paris leben, brauche Französisch für die Arbeit …"
                oninput="updateCharCount(); trackProgress()"></textarea>
            <div class="char-row">
                <span class="char-hint">Beschreibe deine Motivation in eigenen Worten</span>
                <span class="char-count" id="charCount">0 / 400</span>
            </div>
        </div>

        <!-- Absenden -->
        <div class="submit-wrap">
            <button type="submit" class="submit-btn">
                🇩🇪 Profil absenden
            </button>
            <p class="submit-note">
                Deine Daten werden sicher gespeichert · <a href="#">Datenschutz</a>
            </p>
        </div>

    </form>

    <div class="quiz-footer">
        🤖 Veronica AI – Dein intelligenter Sprachcoach · © 2026
    </div>

</div>

<script>
// ── Fortschrittsbalken ──
const TRACKED = ['how_found', 'level', 'goal', 'accent', 'duration', 'motivation'];

function trackProgress() {
    const filled = TRACKED.filter(id => {
        const el = document.getElementById(id);
        return el && el.value.trim() !== '';
    }).length;
    const pct = Math.round((filled / TRACKED.length) * 100);
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressPct').textContent  = pct + '%';
    document.getElementById('progressLabel').textContent = `Fortschritt: ${filled} von ${TRACKED.length}`;
}

// ── Zeichenzähler ──
function updateCharCount() {
    const ta  = document.getElementById('motivation');
    const el  = document.getElementById('charCount');
    const len = ta.value.length;
    el.textContent = len + ' / 400';
    el.className = 'char-count' + (len >= 350 ? ' warn' : len >= 200 ? ' ok' : '');
}

// ── Diagramm ──
const chartCtx = document.getElementById('practiceChart').getContext('2d');
let chart;

function updateChart() {
    const days    = parseInt(document.getElementById('days').value)    || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const weekly  = days * minutes;
    const monthly = weekly * 4;

    const data = {
        labels: ['Minuten / Woche', 'Minuten / Monat'],
        datasets: [{
            label: 'Geschätzte Übungszeit',
            data: [weekly, monthly],
            backgroundColor: ['rgba(221,0,0,0.75)', 'rgba(255,206,0,0.75)'],
            borderColor:     ['#DD0000', '#FFCE00'],
            borderWidth: 1.5,
            borderRadius: 6,
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
                    backgroundColor: '#1a1a1a',
                    titleColor: '#e5e7eb',
                    bodyColor: '#9ca3af',
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    callbacks: { label: ctx => ' ' + ctx.parsed.y + ' Min.' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid:  { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#4b5563', font: { family: 'IBM Plex Sans', size: 11 } }
                },
                x: {
                    grid:  { display: false },
                    ticks: { color: '#4b5563', font: { family: 'IBM Plex Sans', size: 11 } }
                }
            }
        }
    });
}

document.getElementById('days').addEventListener('input', updateChart);
document.getElementById('minutes').addEventListener('input', updateChart);

// ── Sprachausgabe ──
window.onload = () => {
    const intro = "Hallo! Ich bin Veronica AI, dein persönlicher Sprachcoach. Beantworte diese Fragen, damit ich deinen Französischkurs individuell auf dich abstimmen kann.";

    if ('speechSynthesis' in window) {
        let voiced = false;
        function speak() {
            if (voiced) return;
            const voices  = window.speechSynthesis.getVoices();
            const deVoice = voices.find(v => v.lang.startsWith('de')) || null;
            const utter   = new SpeechSynthesisUtterance(intro);
            utter.lang    = 'de-DE';
            utter.rate    = 0.93;
            if (deVoice) utter.voice = deVoice;
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
