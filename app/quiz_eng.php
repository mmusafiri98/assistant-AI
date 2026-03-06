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
                PDO::ATTR_ERRMODE        => PDO::ERRMODE_EXCEPTION,
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
            $_SESSION['quiz_error'] = "An error occurred while saving your profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Language Quiz – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: "Poppins", sans-serif;
            background: linear-gradient(135deg, #a78bfa, #7dd3fc);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1e293b;
        }
        .quiz-container {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            text-align: center;
            animation: fadeIn 0.8s ease-in-out;
        }
        h2 { font-size: 1.8rem; color: #1e293b; margin-bottom: 20px; }
        label { font-weight: 600; display: block; margin: 10px 0 5px; text-align: left; }
        select, textarea, input[type="number"] {
            width: 100%; padding: 10px; border-radius: 10px;
            border: 1px solid #dbeafe; margin-bottom: 15px;
            font-size: 1rem;
        }
        textarea { height: 80px; resize: none; }
        .skills-group { display: flex; flex-direction: column; gap: 8px; text-align: left; }
        .skills-group label { font-weight: 400; display: flex; align-items: center; gap: 8px; }
        button {
            background-color: #4f46e5; color: white; border: none;
            border-radius: 10px; padding: 12px 25px; cursor: pointer;
            font-size: 1.1rem; width: 100%; transition: 0.3s;
        }
        button:hover { background-color: #4338ca; transform: translateY(-2px); }
        .veronica-message {
            font-style: italic; margin-bottom: 20px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 10px; padding: 15px;
        }
        .alert-error {
            background: rgba(220,38,38,0.12); color: #b91c1c;
            padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;
        }
        .chart-container { margin-top: 25px; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="quiz-container">
    <h2>🎓 Your Language Profile – Veronica AI</h2>

    <div class="veronica-message" id="veronicaMsg">
        Hey 👋 I'm <strong>Veronica AI</strong>!
        Answer these questions so I can personalise your French learning journey 🇫🇷
    </div>

    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error"><?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <form method="post" id="quizForm">

        <label for="how_found">How did you hear about Veronica AI?</label>
        <select name="how_found" id="how_found" required>
            <option value="">-- Select an answer --</option>
            <option>Through a friend</option>
            <option>On social media</option>
            <option>Via an advertisement</option>
            <option>Internet search</option>
            <option>Other</option>
        </select>

        <label for="level">What is your current French level?</label>
        <select name="level" id="level" required>
            <option value="">-- Choose your level --</option>
            <option>Beginner (A1–A2)</option>
            <option>Intermediate (B1–B2)</option>
            <option>Advanced (C1–C2)</option>
            <option>I'm not sure</option>
        </select>

        <label for="goal">Why do you want to learn French?</label>
        <select name="goal" id="goal" required>
            <option value="">-- Your main goal --</option>
            <option>Travel to a French-speaking country</option>
            <option>Study in France / Canada</option>
            <option>Professional reasons</option>
            <option>Culture and passion for the language</option>
            <option>Other</option>
        </select>

        <label for="accent">Which accent would you like to learn?</label>
        <select name="accent" id="accent" required>
            <option value="">-- Choose an accent --</option>
            <option>Parisian French 🇫🇷</option>
            <option>Canadian French 🇨🇦</option>
            <option>Belgian French 🇧🇪</option>
        </select>

        <label for="duration">How long do you want to take to reach your goal?</label>
        <select name="duration" id="duration" required>
            <option value="">-- Choose a timeframe --</option>
            <option>Less than 3 months</option>
            <option>3 to 6 months</option>
            <option>6 to 12 months</option>
            <option>More than a year</option>
        </select>

        <label>How much do you want to practise?</label>
        <div style="display:flex; gap:10px;">
            <input type="number" name="days"    id="days"    min="1" max="7"   placeholder="Days / week">
            <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minutes / day">
        </div>

        <div class="chart-container">
            <canvas id="practiceChart" height="200"></canvas>
        </div>

        <label style="margin-top:10px;">Which skills would you like to develop?</label>
        <div class="skills-group">
            <label><input type="checkbox" name="skills[]" value="Professional interview"> Professional interview</label>
            <label><input type="checkbox" name="skills[]" value="Oral presentation"> Oral presentation</label>
            <label><input type="checkbox" name="skills[]" value="Negotiation"> Negotiation</label>
            <label><input type="checkbox" name="skills[]" value="Meeting or conference"> Meeting / conference</label>
            <label><input type="checkbox" name="skills[]" value="Informal communication"> Informal communication</label>
        </div>

        <label for="motivation" style="margin-top:16px;">Your motivation to learn French:</label>
        <textarea name="motivation" id="motivation" required
            placeholder="E.g.: I love French culture, music, cinema…"></textarea>

        <button type="submit">Submit my profile</button>
    </form>

    <footer style="margin-top:25px; font-size:0.9rem; color:#334155;">
        🪄 Veronica AI – Your intelligent language coach
    </footer>
</div>

<script>
const ctx = document.getElementById('practiceChart');
let chart;

function updateChart() {
    const days    = parseInt(document.getElementById('days').value)    || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const totalWeekly  = days * minutes;
    const totalMonthly = totalWeekly * 4;

    const data = {
        labels: ['Minutes / week', 'Minutes / month'],
        datasets: [{
            label: 'Estimated practice time',
            data: [totalWeekly, totalMonthly],
            borderWidth: 2,
            backgroundColor: ['#4f46e5', '#7dd3fc']
        }]
    };

    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'bar',
        data,
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

document.getElementById('days').addEventListener('input', updateChart);
document.getElementById('minutes').addEventListener('input', updateChart);

window.onload = () => {
    const intro = "Hello! I'm Veronica AI. Answer these questions so I can personalise your learning path based on your preferred accent and study rhythm.";
    document.getElementById('veronicaMsg').textContent = intro;

    if ('speechSynthesis' in window) {
        const utter = new SpeechSynthesisUtterance(intro);
        utter.lang = 'en-US';
        window.speechSynthesis.speak(utter);
    }
};
</script>

</body>
</html>
<?php ob_end_flush(); ?>
