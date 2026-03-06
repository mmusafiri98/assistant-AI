<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ===== FORM PROCESSING =====
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
        $_SESSION['quiz_error'] = "Please fill in all required fields.";
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
            $username = $_SESSION['username'] ?? 'guest';

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
            error_log("DB Error: " . $e->getMessage());
            $_SESSION['quiz_error'] = "An error occurred while saving your profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chinese Learning Profile – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Inter", sans-serif;
            background: #0c0c0c;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            color: #e5e7eb;
            padding: 40px 16px 60px;
            position: relative;
            overflow-x: hidden;
        }

        /* ── Background glow ── */
        body::before {
            content: '';
            position: fixed;
            top: -20%;
            left: -10%;
            width: 60%;
            height: 60%;
            background: radial-gradient(ellipse, rgba(220,38,38,0.07) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -10%;
            right: -10%;
            width: 55%;
            height: 55%;
            background: radial-gradient(ellipse, rgba(234,179,8,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Top flag stripe ── */
        .flag-top {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #DE2910 0%, #DE2910 70%, #FFDE00 70%, #FFDE00 100%);
            z-index: 999;
        }

        /* ── Floating hanzi decorations ── */
        .hanzi-bg {
            position: fixed;
            z-index: 0;
            font-weight: 900;
            color: rgba(255,255,255,0.025);
            user-select: none;
            pointer-events: none;
            line-height: 1;
        }
        .hanzi-bg.c1 { top: 5%;  left: 2%;  font-size: 12rem; }
        .hanzi-bg.c2 { top: 15%; right: 1%; font-size: 9rem; }
        .hanzi-bg.c3 { bottom: 20%; left: 1%; font-size: 10rem; }
        .hanzi-bg.c4 { bottom: 5%; right: 2%; font-size: 13rem; }
        .hanzi-bg.c5 { top: 45%; left: 45%; font-size: 7rem; transform: translateX(-50%); }

        /* ── Main card ── */
        .quiz-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 720px;
            background: #111111;
            border: 1px solid #1e1e1e;
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.03),
                0 32px 80px rgba(0,0,0,0.6),
                0 0 100px rgba(220,38,38,0.04);
            animation: slideUp 0.65s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Card header ── */
        .card-header {
            background: linear-gradient(135deg, #1a0505 0%, #1a0a00 50%, #130d00 100%);
            border-bottom: 1px solid #1e1e1e;
            padding: 36px 40px 32px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .card-header::before {
            content: '';
            position: absolute;
            top: -40%;
            left: 50%;
            transform: translateX(-50%);
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(220,38,38,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(220,38,38,0.12);
            border: 1px solid rgba(220,38,38,0.25);
            border-radius: 30px;
            padding: 6px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #fca5a5;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .header-badge .star { color: #FFDE00; }

        .card-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.2;
            margin-bottom: 6px;
        }
        .card-header h1 .highlight { color: #DE2910; }

        .card-header .hanzi-title {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 8px;
            margin-top: 10px;
            font-weight: 700;
        }

        .card-header p {
            font-size: 0.84rem;
            color: #6b7280;
            margin-top: 8px;
        }

        /* ── Progress bar ── */
        .progress-section {
            padding: 20px 40px 0;
            background: #111;
        }
        .progress-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .progress-meta span {
            font-size: 0.72rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .progress-meta .pct { color: #FFDE00; }
        .progress-track {
            width: 100%;
            height: 4px;
            background: #1e1e1e;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #DE2910, #FFDE00);
            border-radius: 2px;
            transition: width 0.4s ease;
        }

        /* ── Veronica message ── */
        .veronica-bar {
            margin: 0 40px 0;
            background: #161616;
            border: 1px solid #1e1e1e;
            border-left: 3px solid #DE2910;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 28px;
        }
        .veronica-bar .av {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #DE2910, #f97316);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 3px 12px rgba(220,38,38,0.3);
        }
        .veronica-bar .vtext {
            font-size: 0.88rem;
            line-height: 1.7;
            color: #9ca3af;
        }
        .veronica-bar .vtext strong { color: #fff; }
        .veronica-bar .vtext .pinyin {
            display: inline-block;
            background: rgba(255,222,0,0.08);
            border: 1px solid rgba(255,222,0,0.15);
            border-radius: 6px;
            padding: 1px 8px;
            font-size: 0.78rem;
            color: #FFDE00;
            margin-left: 4px;
        }

        /* ── Form body ── */
        .form-body { padding: 0 40px 40px; }

        /* ── Alert ── */
        .alert-error {
            background: rgba(220,38,38,0.08);
            border: 1px solid rgba(220,38,38,0.2);
            border-left: 3px solid #DE2910;
            color: #fca5a5;
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        /* ── Section label ── */
        .sec {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 28px 0 16px;
        }
        .sec-icon {
            width: 30px; height: 30px;
            background: #161616;
            border: 1px solid #1e1e1e;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .sec-text {
            font-size: 0.69rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: #374151;
        }
        .sec::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #1e1e1e;
        }

        /* ── Fields ── */
        .field { margin-bottom: 16px; }

        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.84rem;
            font-weight: 600;
            color: #d1d5db;
            margin-bottom: 8px;
        }
        .field-label .req { color: #DE2910; }
        .field-label .opt {
            font-size: 0.71rem;
            font-weight: 400;
            color: #374151;
        }
        .field-label .hanzi-hint {
            font-size: 0.78rem;
            color: rgba(255,222,0,0.4);
            margin-left: auto;
            font-weight: 500;
        }

        select, textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #1e1e1e;
            background: #161616;
            font-family: "Inter", sans-serif;
            font-size: 0.9rem;
            color: #e5e7eb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%23FFDE00' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-color: #161616;
            padding-right: 40px;
            cursor: pointer;
        }
        select option { background: #1a1a1a; color: #e5e7eb; }
        select:focus, textarea:focus {
            border-color: #DE2910;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        textarea {
            height: 100px;
            resize: vertical;
            line-height: 1.75;
        }

        /* ── Two-col grid ── */
        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* ── Number inputs ── */
        .num-wrap { position: relative; }
        .num-wrap input[type="number"] {
            width: 100%;
            padding: 12px 60px 12px 16px;
            border-radius: 10px;
            border: 1px solid #1e1e1e;
            background: #161616;
            font-family: "Inter", sans-serif;
            font-size: 0.9rem;
            color: #e5e7eb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            -moz-appearance: textfield;
        }
        .num-wrap input[type="number"]::-webkit-inner-spin-button,
        .num-wrap input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
        .num-wrap input[type="number"]:focus {
            border-color: #DE2910;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .num-wrap .unit {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.69rem;
            font-weight: 700;
            color: #FFDE00;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ── Chart ── */
        .chart-box {
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            padding: 18px 20px;
            margin-top: 16px;
        }
        .chart-box-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #374151;
            margin-bottom: 14px;
        }

        /* ── Skills ── */
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
            border: 1px solid #1e1e1e;
            border-radius: 10px;
            padding: 13px 14px;
            cursor: pointer;
            transition: all 0.18s;
            font-size: 0.83rem;
            font-weight: 500;
            color: #9ca3af;
            user-select: none;
        }
        .skill-tile:hover {
            background: rgba(220,38,38,0.07);
            border-color: rgba(220,38,38,0.35);
            color: #e5e7eb;
        }
        .skill-tile input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #FFDE00;
            flex-shrink: 0;
        }
        .skill-tile .sk-hanzi {
            margin-left: auto;
            font-size: 0.9rem;
            opacity: 0.25;
        }

        /* ── Char counter ── */
        .char-row {
            display: flex;
            justify-content: space-between;
            margin-top: 7px;
        }
        .char-hint { font-size: 0.7rem; color: #374151; }
        .char-val  { font-size: 0.7rem; font-weight: 700; color: #374151; transition: color 0.2s; }
        .char-val.ok   { color: #FFDE00; }
        .char-val.warn { color: #DE2910; }

        /* ── HSK info strip ── */
        .hsk-strip {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .hsk-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #161616;
            border: 1px solid #1e1e1e;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 0.76rem;
            color: #6b7280;
            transition: all 0.18s;
            cursor: default;
        }
        .hsk-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
        }
        .hsk-pill.hsk1 .dot { background: #22c55e; }
        .hsk-pill.hsk2 .dot { background: #84cc16; }
        .hsk-pill.hsk3 .dot { background: #eab308; }
        .hsk-pill.hsk4 .dot { background: #f97316; }
        .hsk-pill.hsk5 .dot { background: #ef4444; }
        .hsk-pill.hsk6 .dot { background: #a855f7; }

        /* ── Submit ── */
        .submit-section { margin-top: 16px; }
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #DE2910, #b91c1c);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: "Inter", sans-serif;
            font-size: 0.97rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(220,38,38,0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.3px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(220,38,38,0.4);
            background: linear-gradient(135deg, #ef4444, #DE2910);
        }
        .submit-btn:active { transform: translateY(0); }

        .submit-note {
            text-align: center;
            margin-top: 12px;
            font-size: 0.73rem;
            color: #374151;
        }
        .submit-note span { color: #FFDE00; }

        /* ── Card footer ── */
        .card-footer {
            border-top: 1px solid #1a1a1a;
            padding: 18px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-footer p {
            font-size: 0.74rem;
            color: #374151;
        }
        .card-footer .hanzi-footer {
            font-size: 1.1rem;
            letter-spacing: 4px;
            color: rgba(255,255,255,0.07);
            font-weight: 700;
        }

        @media (max-width: 580px) {
            body              { padding: 20px 12px 50px; }
            .card-header      { padding: 28px 22px 24px; }
            .progress-section { padding: 16px 22px 0; }
            .veronica-bar     { margin: 0 22px 22px; }
            .form-body        { padding: 0 22px 32px; }
            .card-footer      { padding: 16px 22px; }
            .skills-grid      { grid-template-columns: 1fr; }
            .field-grid       { grid-template-columns: 1fr; }
            .card-header h1   { font-size: 1.5rem; }
            .hsk-strip        { display: none; }
            .hanzi-bg         { display: none; }
        }
    </style>
</head>
<body>

    <!-- Top red stripe -->
    <div class="flag-top"></div>

    <!-- Background hanzi -->
    <div class="hanzi-bg c1">学</div>
    <div class="hanzi-bg c2">中</div>
    <div class="hanzi-bg c3">文</div>
    <div class="hanzi-bg c4">汉</div>
    <div class="hanzi-bg c5">语</div>

    <div class="quiz-card">

        <!-- Header -->
        <div class="card-header">
            <div class="header-badge">
                <span class="star">★</span> Veronica AI · Chinese Learning Profile
            </div>
            <h1>Build Your <span class="highlight">Mandarin</span> Journey</h1>
            <p>Answer a few questions so Veronica can personalise your Chinese learning plan</p>
            <div class="hanzi-title">学中文 · 你好 · 加油</div>
        </div>

        <!-- Progress -->
        <div class="progress-section">
            <div class="progress-meta">
                <span id="progLabel">Progress: 0 of 6</span>
                <span class="pct" id="progPct">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progFill"></div>
            </div>
        </div>

        <!-- Veronica intro -->
        <div style="padding: 20px 40px 0;">
            <div class="veronica-bar">
                <div class="av">🌸</div>
                <div class="vtext">
                    Hi! I'm <strong>Veronica AI</strong>, your personal Mandarin Chinese coach. 🐉<br>
                    Take a moment to fill in this profile — it helps me create the perfect learning plan just for you.
                    <span class="pinyin">nǐ hǎo!</span> Let's go! 加油！
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="form-body">

            <?php if (!empty($_SESSION['quiz_error'])): ?>
                <div class="alert-error">⚠️ <?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
                <?php unset($_SESSION['quiz_error']); ?>
            <?php endif; ?>

            <form method="post" id="quizForm">

                <!-- Section 1 -->
                <div class="sec">
                    <div class="sec-icon">📍</div>
                    <span class="sec-text">Discovery</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        How did you hear about Veronica AI?
                        <span class="req">*</span>
                        <span class="hanzi-hint">来源</span>
                    </div>
                    <select name="how_found" id="how_found" required onchange="trackProg()">
                        <option value="">-- Select an answer --</option>
                        <option>Through a friend</option>
                        <option>On social media (Instagram, TikTok…)</option>
                        <option>Via an advertisement</option>
                        <option>Internet search</option>
                        <option>Other</option>
                    </select>
                </div>

                <!-- Section 2 -->
                <div class="sec">
                    <div class="sec-icon">📊</div>
                    <span class="sec-text">Your Chinese Level</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        What is your current Mandarin level?
                        <span class="req">*</span>
                        <span class="hanzi-hint">水平</span>
                    </div>
                    <select name="level" id="level" required onchange="trackProg()">
                        <option value="">-- Choose your level --</option>
                        <option>Complete Beginner — never studied Chinese</option>
                        <option>Beginner (HSK 1–2) — basic words & phrases</option>
                        <option>Elementary (HSK 3) — simple conversations</option>
                        <option>Intermediate (HSK 4–5) — fluent on familiar topics</option>
                        <option>Advanced (HSK 6) — near-native proficiency</option>
                        <option>I'm not sure of my level</option>
                    </select>

                    <!-- HSK reference pills -->
                    <div class="hsk-strip">
                        <div class="hsk-pill hsk1"><span class="dot"></span>HSK 1</div>
                        <div class="hsk-pill hsk2"><span class="dot"></span>HSK 2</div>
                        <div class="hsk-pill hsk3"><span class="dot"></span>HSK 3</div>
                        <div class="hsk-pill hsk4"><span class="dot"></span>HSK 4</div>
                        <div class="hsk-pill hsk5"><span class="dot"></span>HSK 5</div>
                        <div class="hsk-pill hsk6"><span class="dot"></span>HSK 6</div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">
                        Why do you want to learn Mandarin Chinese?
                        <span class="req">*</span>
                        <span class="hanzi-hint">目标</span>
                    </div>
                    <select name="goal" id="goal" required onchange="trackProg()">
                        <option value="">-- Your main goal --</option>
                        <option>Travel to China, Taiwan or other Chinese-speaking regions</option>
                        <option>Study or work in a Chinese-speaking environment</option>
                        <option>Business and professional reasons</option>
                        <option>Connect with Chinese-speaking family or friends</option>
                        <option>Culture, cinema, music and passion for the language</option>
                        <option>Academic or research purposes</option>
                        <option>Other</option>
                    </select>
                </div>

                <!-- Section 3 -->
                <div class="sec">
                    <div class="sec-icon">🎯</div>
                    <span class="sec-text">Learning Style</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        Which Chinese dialect / accent would you like to focus on?
                        <span class="req">*</span>
                        <span class="hanzi-hint">方言</span>
                    </div>
                    <select name="accent" id="accent" required onchange="trackProg()">
                        <option value="">-- Choose a dialect --</option>
                        <option>Mandarin — Standard Chinese (Pǔtōnghuà) 🇨🇳</option>
                        <option>Taiwanese Mandarin (Guóyǔ) 🇹🇼</option>
                        <option>Cantonese (Guangzhou / Hong Kong) 🇭🇰</option>
                        <option>No preference — general Mandarin</option>
                    </select>
                </div>

                <div class="field">
                    <div class="field-label">
                        In how long do you want to reach your goal?
                        <span class="req">*</span>
                        <span class="hanzi-hint">时间</span>
                    </div>
                    <select name="duration" id="duration" required onchange="trackProg()">
                        <option value="">-- Choose a timeframe --</option>
                        <option>Less than 3 months</option>
                        <option>3 to 6 months</option>
                        <option>6 to 12 months</option>
                        <option>More than a year</option>
                    </select>
                </div>

                <!-- Section 4 -->
                <div class="sec">
                    <div class="sec-icon">⏱️</div>
                    <span class="sec-text">Practice Plan</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        How much time can you dedicate to practice?
                        <span class="opt">(optional)</span>
                    </div>
                    <div class="field-grid">
                        <div class="num-wrap">
                            <input type="number" name="days" id="days" min="1" max="7" placeholder="Days">
                            <span class="unit">days / week</span>
                        </div>
                        <div class="num-wrap">
                            <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minutes">
                            <span class="unit">min / day</span>
                        </div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-box-label">📈 Estimated weekly & monthly practice time</div>
                        <canvas id="practiceChart" height="140"></canvas>
                    </div>
                </div>

                <!-- Section 5 -->
                <div class="sec">
                    <div class="sec-icon">💡</div>
                    <span class="sec-text">Skills to Develop</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        Which Chinese skills do you want to improve?
                        <span class="opt">(multiple choice)</span>
                    </div>
                    <div class="skills-grid">
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Spoken conversation">
                            <span>🗣️ Spoken conversation</span>
                            <span class="sk-hanzi">说话</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Reading Chinese characters">
                            <span>📖 Reading characters</span>
                            <span class="sk-hanzi">阅读</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Writing Hanzi">
                            <span>✍️ Writing Hanzi</span>
                            <span class="sk-hanzi">书写</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Tones and pronunciation">
                            <span>🎵 Tones & pronunciation</span>
                            <span class="sk-hanzi">声调</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Business Chinese">
                            <span>💼 Business Chinese</span>
                            <span class="sk-hanzi">商务</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Travel Chinese">
                            <span>✈️ Travel Chinese</span>
                            <span class="sk-hanzi">旅游</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="HSK exam preparation">
                            <span>🏆 HSK exam prep</span>
                            <span class="sk-hanzi">考试</span>
                        </label>
                        <label class="skill-tile">
                            <input type="checkbox" name="skills[]" value="Chinese culture and media">
                            <span>🎬 Culture & media</span>
                            <span class="sk-hanzi">文化</span>
                        </label>
                    </div>
                </div>

                <!-- Section 6 -->
                <div class="sec">
                    <div class="sec-icon">✍️</div>
                    <span class="sec-text">Motivation</span>
                </div>

                <div class="field">
                    <div class="field-label">
                        Tell us why you want to learn Chinese
                        <span class="req">*</span>
                        <span class="hanzi-hint">动力</span>
                    </div>
                    <textarea
                        name="motivation"
                        id="motivation"
                        required
                        maxlength="400"
                        placeholder="E.g.: I love Chinese culture and food, I want to visit Beijing, I need Chinese for business meetings…"
                        oninput="updateChar(); trackProg()"></textarea>
                    <div class="char-row">
                        <span class="char-hint">Share what drives you to learn Mandarin</span>
                        <span class="char-val" id="charVal">0 / 400</span>
                    </div>
                </div>

                <!-- Submit -->
                <div class="submit-section">
                    <button type="submit" class="submit-btn">
                        🐉 Start My Chinese Journey
                    </button>
                    <p class="submit-note">
                        Your data is securely saved · <span>Veronica AI</span> will personalise your plan
                    </p>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="card-footer">
            <p>🤖 Veronica AI – Your personal Chinese language coach · © 2026</p>
            <div class="hanzi-footer">学 中 文</div>
        </div>

    </div><!-- end .quiz-card -->

    <script>
    // ── Progress tracking ──
    const FIELDS = ['how_found', 'level', 'goal', 'accent', 'duration', 'motivation'];

    function trackProg() {
        const filled = FIELDS.filter(id => {
            const el = document.getElementById(id);
            return el && el.value.trim() !== '';
        }).length;
        const pct = Math.round((filled / FIELDS.length) * 100);
        document.getElementById('progFill').style.width = pct + '%';
        document.getElementById('progPct').textContent  = pct + '%';
        document.getElementById('progLabel').textContent = `Progress: ${filled} of ${FIELDS.length}`;
    }

    // ── Character counter ──
    function updateChar() {
        const ta  = document.getElementById('motivation');
        const el  = document.getElementById('charVal');
        const len = ta.value.length;
        el.textContent = len + ' / 400';
        el.className = 'char-val' + (len >= 350 ? ' warn' : len >= 150 ? ' ok' : '');
    }

    // ── Chart ──
    const ctx = document.getElementById('practiceChart').getContext('2d');
    let chart;

    function updateChart() {
        const days    = parseInt(document.getElementById('days').value)    || 0;
        const minutes = parseInt(document.getElementById('minutes').value) || 0;
        const weekly  = days * minutes;
        const monthly = weekly * 4;

        const data = {
            labels: ['Minutes / week', 'Minutes / month'],
            datasets: [{
                data: [weekly, monthly],
                backgroundColor: ['rgba(220,41,16,0.75)', 'rgba(255,222,0,0.7)'],
                borderColor:     ['#DE2910', '#FFDE00'],
                borderWidth: 1.5,
                borderRadius: 7,
            }]
        };

        if (chart) chart.destroy();
        chart = new Chart(ctx, {
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
                        callbacks: { label: c => ' ' + c.parsed.y + ' min' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid:  { color: 'rgba(255,255,255,0.04)' },
                        ticks: { color: '#4b5563', font: { family: 'Inter', size: 11 } }
                    },
                    x: {
                        grid:  { display: false },
                        ticks: { color: '#4b5563', font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });
    }

    document.getElementById('days').addEventListener('input', updateChart);
    document.getElementById('minutes').addEventListener('input', updateChart);

    // ── Veronica voice intro on load ──
    window.onload = () => {
        updateChart();
        trackProg();

        const intro = "Hi! I'm Veronica AI, your personal Mandarin Chinese coach. Please fill in this profile so I can build the perfect learning plan just for you. Nǐ hǎo! Let's go!";

        if ('speechSynthesis' in window) {
            let voiced = false;

            function speak() {
                if (voiced) return;
                const voices = window.speechSynthesis.getVoices();

                // Best female English voice
                const voice =
                    voices.find(v => v.lang.startsWith('en') && /zira|susan|karen|samantha|victoria|moira|fiona|tessa|female|woman/i.test(v.name)) ||
                    voices.find(v => v.lang === 'en-US') ||
                    voices.find(v => v.lang.startsWith('en'));

                const utter   = new SpeechSynthesisUtterance(intro);
                utter.lang    = 'en-US';
                utter.rate    = 0.95;
                utter.pitch   = 1.15;
                if (voice) utter.voice = voice;

                window.speechSynthesis.speak(utter);
                voiced = true;
            }

            window.speechSynthesis.getVoices().length
                ? speak()
                : (window.speechSynthesis.onvoiceschanged = speak);
        }
    };
    </script>

</body>
</html>
<?php ob_end_flush(); ?>
