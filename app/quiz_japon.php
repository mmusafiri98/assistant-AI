<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ===== TRAITEMENT DU FORMULAIRE =====
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
        $_SESSION['quiz_error'] = "すべての必須項目を入力してください。";
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
            $username = $_SESSION['username'] ?? 'ゲスト';

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
            $_SESSION['quiz_error'] = "プロフィールの保存中にエラーが発生しました。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>言語クイズ – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Noto Sans JP", sans-serif;
            background: linear-gradient(135deg, #f9a8d4, #818cf8, #38bdf8);
            background-size: 300% 300%;
            animation: gradientShift 10s ease infinite;
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
            background: rgba(255, 255, 255, 0.28);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(255,255,255,0.45);
            border-radius: 24px;
            padding: 44px 40px;
            width: 100%;
            max-width: 680px;
            box-shadow: 0 12px 48px rgba(0,0,0,0.18);
            animation: fadeIn 0.7s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── HEADER ── */
        .quiz-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .quiz-header .logo {
            font-size: 2.8rem;
            display: block;
            margin-bottom: 8px;
        }
        .quiz-header h2 {
            font-size: 1.55rem;
            font-weight: 700;
            color: #1e1b4b;
            line-height: 1.4;
        }
        .quiz-header p {
            font-size: 0.82rem;
            color: #475569;
            margin-top: 4px;
        }

        /* ── VERONICA MSG ── */
        .veronica-message {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: rgba(255,255,255,0.35);
            border-left: 4px solid #818cf8;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 28px;
            font-size: 0.92rem;
            line-height: 1.7;
            color: #1e293b;
        }
        .veronica-message .avatar {
            font-size: 2rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ── ALERT ── */
        .alert-error {
            background: rgba(220,38,38,0.12);
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.88rem;
            border-left: 4px solid #ef4444;
        }

        /* ── FORM ELEMENTS ── */
        .field {
            margin-bottom: 20px;
        }
        label {
            font-weight: 600;
            font-size: 0.88rem;
            display: block;
            margin-bottom: 7px;
            color: #1e1b4b;
        }
        label .required {
            color: #e11d48;
            margin-left: 3px;
        }
        select, textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 12px;
            border: 1.5px solid rgba(129,140,248,0.4);
            background: rgba(255,255,255,0.6);
            font-family: "Noto Sans JP", sans-serif;
            font-size: 0.93rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23818cf8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 38px;
        }
        select:focus, textarea:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,0.2);
        }
        textarea {
            height: 90px;
            resize: vertical;
            line-height: 1.7;
        }

        /* ── PRACTICE INPUTS ── */
        .practice-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .practice-input-wrap {
            position: relative;
        }
        .practice-input-wrap input[type="number"] {
            width: 100%;
            padding: 11px 50px 11px 14px;
            border-radius: 12px;
            border: 1.5px solid rgba(129,140,248,0.4);
            background: rgba(255,255,255,0.6);
            font-family: "Noto Sans JP", sans-serif;
            font-size: 0.93rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            -moz-appearance: textfield;
        }
        .practice-input-wrap input[type="number"]::-webkit-inner-spin-button,
        .practice-input-wrap input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
        .practice-input-wrap input[type="number"]:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,0.2);
        }
        .practice-input-wrap .unit {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.73rem;
            color: #818cf8;
            font-weight: 600;
            pointer-events: none;
        }

        /* ── SKILLS ── */
        .skills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
        }
        .skill-item {
            display: flex;
            align-items: center;
            gap: 9px;
            background: rgba(255,255,255,0.4);
            border: 1.5px solid rgba(129,140,248,0.25);
            border-radius: 10px;
            padding: 10px 13px;
            cursor: pointer;
            transition: all 0.18s;
            font-size: 0.83rem;
            font-weight: 400;
            color: #1e293b;
        }
        .skill-item:hover {
            background: rgba(129,140,248,0.15);
            border-color: #818cf8;
        }
        .skill-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #818cf8;
            flex-shrink: 0;
        }

        /* ── CHART ── */
        .chart-wrap {
            background: rgba(255,255,255,0.35);
            border-radius: 14px;
            padding: 16px;
            margin-top: 14px;
        }

        /* ── SUBMIT ── */
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: "Noto Sans JP", sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 18px rgba(99,102,241,0.35);
            margin-top: 8px;
        }
        .submit-btn:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.45);
        }
        .submit-btn:active {
            transform: translateY(0);
        }

        /* ── DIVIDER ── */
        .section-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 24px 0 20px;
            color: #94a3b8;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(148,163,184,0.35);
        }

        /* ── FOOTER ── */
        .quiz-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.8rem;
            color: #475569;
        }

        /* ── PROGRESS DOTS ── */
        .progress-dots {
            display: flex;
            justify-content: center;
            gap: 7px;
            margin-bottom: 28px;
        }
        .dot {
            width: 9px; height: 9px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            border: 1.5px solid rgba(129,140,248,0.5);
            transition: background 0.3s;
        }
        .dot.active { background: #818cf8; border-color: #818cf8; }

        @media (max-width: 540px) {
            .quiz-container { padding: 28px 20px; }
            .skills-grid { grid-template-columns: 1fr; }
            .practice-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="quiz-container">

    <!-- Header -->
    <div class="quiz-header">
        <span class="logo">🌸</span>
        <h2>言語プロフィール設定<br>Veronica AI</h2>
        <p>あなたの学習を完全にパーソナライズします</p>
    </div>

    <!-- Progress dots -->
    <div class="progress-dots" id="progressDots">
        <div class="dot active"></div>
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
    </div>

    <!-- Veronica message -->
    <div class="veronica-message">
        <span class="avatar">🤖</span>
        <span id="veronicaMsg">
            こんにちは！私は <strong>Veronica AI</strong> です。<br>
            以下の質問に答えて、あなたに最適なフランス語学習プランを作成しましょう 🇫🇷
        </span>
    </div>

    <!-- Error -->
    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <!-- Form -->
    <form method="post" id="quizForm">

        <!-- Q1 -->
        <div class="field">
            <label for="how_found">Veronica AI をどのようにお知りになりましたか？<span class="required">*</span></label>
            <select name="how_found" id="how_found" required onchange="updateDots(0)">
                <option value="">-- 回答を選択してください --</option>
                <option>友人・知人から</option>
                <option>SNS（Instagram、TikTok など）</option>
                <option>広告を見て</option>
                <option>インターネット検索</option>
                <option>その他</option>
            </select>
        </div>

        <div class="section-divider">現在のレベル</div>

        <!-- Q2 -->
        <div class="field">
            <label for="level">現在のフランス語レベルは？<span class="required">*</span></label>
            <select name="level" id="level" required onchange="updateDots(1)">
                <option value="">-- レベルを選択してください --</option>
                <option>初級（A1–A2）</option>
                <option>中級（B1–B2）</option>
                <option>上級（C1–C2）</option>
                <option>わからない</option>
            </select>
        </div>

        <!-- Q3 -->
        <div class="field">
            <label for="goal">フランス語を学ぶ目的は？<span class="required">*</span></label>
            <select name="goal" id="goal" required onchange="updateDots(2)">
                <option value="">-- 主な目標を選択してください --</option>
                <option>フランス語圏への旅行</option>
                <option>フランス・カナダへの留学</option>
                <option>仕事・キャリアのため</option>
                <option>文化・語学への情熱</option>
                <option>その他</option>
            </select>
        </div>

        <div class="section-divider">学習スタイル</div>

        <!-- Q4 -->
        <div class="field">
            <label for="accent">学びたいアクセントは？<span class="required">*</span></label>
            <select name="accent" id="accent" required onchange="updateDots(3)">
                <option value="">-- アクセントを選択してください --</option>
                <option>パリ・フランス語 🇫🇷</option>
                <option>カナダ・フランス語 🇨🇦</option>
                <option>ベルギー・フランス語 🇧🇪</option>
            </select>
        </div>

        <!-- Q5 -->
        <div class="field">
            <label for="duration">目標達成までの期間は？<span class="required">*</span></label>
            <select name="duration" id="duration" required onchange="updateDots(4)">
                <option value="">-- 期間を選択してください --</option>
                <option>3ヶ月未満</option>
                <option>3〜6ヶ月</option>
                <option>6〜12ヶ月</option>
                <option>1年以上</option>
            </select>
        </div>

        <!-- Q6 練習量 -->
        <div class="field">
            <label>どのくらい練習しますか？</label>
            <div class="practice-row">
                <div class="practice-input-wrap">
                    <input type="number" name="days" id="days" min="1" max="7" placeholder="日数">
                    <span class="unit">日/週</span>
                </div>
                <div class="practice-input-wrap">
                    <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="分数">
                    <span class="unit">分/日</span>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="practiceChart" height="160"></canvas>
            </div>
        </div>

        <div class="section-divider">身につけたいスキル</div>

        <!-- Skills -->
        <div class="field">
            <label>どのスキルを伸ばしたいですか？（複数選択可）</label>
            <div class="skills-grid">
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="就職・転職面接"> 💼 就職・転職面接
                </label>
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="プレゼンテーション"> 🎤 プレゼンテーション
                </label>
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="ビジネス交渉"> 🤝 ビジネス交渉
                </label>
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="会議・カンファレンス"> 📋 会議・カンファレンス
                </label>
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="日常会話"> 💬 日常会話
                </label>
                <label class="skill-item">
                    <input type="checkbox" name="skills[]" value="旅行フランス語"> ✈️ 旅行フランス語
                </label>
            </div>
        </div>

        <div class="section-divider">モチベーション</div>

        <!-- Motivation -->
        <div class="field">
            <label for="motivation">フランス語を学ぶモチベーションを教えてください<span class="required">*</span></label>
            <textarea name="motivation" id="motivation" required
                placeholder="例：フランス映画が好き、パリに住みたい、仕事でフランス語が必要…"
                oninput="updateDots(5)"></textarea>
        </div>

        <button type="submit" class="submit-btn">
            🌸 プロフィールを送信する
        </button>

    </form>

    <div class="quiz-footer">
        🤖 Veronica AI – あなたのインテリジェント語学コーチ
    </div>
</div>

<script>
// ── Progress dots ──
const dots = document.querySelectorAll('.dot');
function updateDots(idx) {
    dots.forEach((d, i) => d.classList.toggle('active', i <= idx));
}

// ── Chart ──
const chartCtx = document.getElementById('practiceChart');
let chart;

function updateChart() {
    const days    = parseInt(document.getElementById('days').value)    || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const weekly  = days * minutes;
    const monthly = weekly * 4;

    const data = {
        labels: ['週あたり（分）', '月あたり（分）'],
        datasets: [{
            label: '練習時間の見積もり',
            data: [weekly, monthly],
            backgroundColor: [
                'rgba(129, 140, 248, 0.75)',
                'rgba(249, 168, 212, 0.75)'
            ],
            borderColor: ['#818cf8', '#f9a8d4'],
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
                        label: ctx => ' ' + ctx.parsed.y + ' 分'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148,163,184,0.15)' },
                    ticks: { font: { family: 'Noto Sans JP', size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Noto Sans JP', size: 11 } }
                }
            }
        }
    });
}

document.getElementById('days').addEventListener('input', updateChart);
document.getElementById('minutes').addEventListener('input', updateChart);

// ── Intro speech ──
window.onload = () => {
    const intro = "こんにちは！私はVeronica AIです。いくつかの質問に答えて、あなただけのフランス語学習プランを作成しましょう。";
    if ('speechSynthesis' in window) {
        // Wait for voices to load
        let voiced = false;
        function speak() {
            if (voiced) return;
            const voices = window.speechSynthesis.getVoices();
            const jaVoice = voices.find(v => v.lang.startsWith('ja')) || null;
            const utter = new SpeechSynthesisUtterance(intro);
            utter.lang = 'ja-JP';
            if (jaVoice) utter.voice = jaVoice;
            utter.rate = 0.9;
            window.speechSynthesis.speak(utter);
            voiced = true;
        }
        if (window.speechSynthesis.getVoices().length) {
            speak();
        } else {
            window.speechSynthesis.onvoiceschanged = speak;
        }
    }
    updateChart();
};
</script>

</body>
</html>
<?php ob_end_flush(); ?>
