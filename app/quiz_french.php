<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $how_found = htmlspecialchars(trim($_POST['how_found'] ?? ''));
    $level = htmlspecialchars(trim($_POST['level'] ?? ''));
    $goal = htmlspecialchars(trim($_POST['goal'] ?? ''));
    $duration = htmlspecialchars(trim($_POST['duration'] ?? ''));
    $motivation = htmlspecialchars(trim($_POST['motivation'] ?? ''));
    $skills = isset($_POST['skills']) && is_array($_POST['skills']) ? implode(", ", array_map('trim', $_POST['skills'])) : '';
    $accent = htmlspecialchars(trim($_POST['accent'] ?? ''));
    $days = intval($_POST['days'] ?? 0);
    $minutes = intval($_POST['minutes'] ?? 0);

    if ($how_found === '' || $level === '' || $goal === '' || $duration === '' || $motivation === '' || $accent === '') {
        $_SESSION['quiz_error'] = "Merci de remplir tous les champs obligatoires.";
    } else {
        try {
            $conn = new PDO("mysql:host=localhost;dbname=veronica_ai_login;charset=utf8", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $sql = "INSERT INTO user_quiz (username, how_found, level, goal, duration, motivation, skills)
                    VALUES (:username, :how_found, :level, :goal, :duration, :motivation, :skills)";
            $stmt = $conn->prepare($sql);
            $username = $_SESSION['username'] ?? 'invité';

            $stmt->execute([
                ':username' => $username,
                ':how_found' => $how_found,
                ':level' => $level,
                ':goal' => $goal,
                ':duration' => $duration,
                ':motivation' => $motivation,
                ':skills' => $skills
            ]);

            $conn = null;
            header("Location: thankyou.php");
            exit;
        } catch (PDOException $e) {
            error_log("DB ERROR: " . $e->getMessage());
            $_SESSION['quiz_error'] = "Une erreur est survenue lors de l'enregistrement.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Quiz linguistique – Veronica AI</title>
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
label { font-weight: 600; display: block; margin: 10px 0 5px; }
select, textarea, input[type="number"] {
    width: 100%; padding: 10px; border-radius: 10px;
    border: 1px solid #dbeafe; margin-bottom: 15px;
    font-size: 1rem;
}
textarea { height: 80px; resize: none; }
.skills-group { display: flex; flex-direction: column; gap: 8px; }
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
.alert-error { background: rgba(220,38,38,0.12); color: #b91c1c;
    padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; }
.chart-container { margin-top: 25px; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="quiz-container">
    <h2>🎓 Profil linguistique Veronica AI</h2>

    <div class="veronica-message" id="veronicaMsg">
        Salut 👋 Je suis <strong>Veronica AI</strong> !  
        Réponds à ces questions pour personnaliser ton apprentissage du français 🇫🇷
    </div>

    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error"><?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <form method="post" id="quizForm">
        <label for="how_found">Comment as-tu connu Veronica AI ?</label>
        <select name="how_found" id="how_found" required>
            <option value="">-- Sélectionne une réponse --</option>
            <option>Par un ami</option>
            <option>Sur les réseaux sociaux</option>
            <option>Via une publicité</option>
            <option>Recherche Internet</option>
            <option>Autre</option>
        </select>

        <label for="level">Quel est ton niveau actuel en français ?</label>
        <select name="level" id="level" required>
            <option value="">-- Choisis ton niveau --</option>
            <option>Débutant (A1–A2)</option>
            <option>Intermédiaire (B1–B2)</option>
            <option>Avancé (C1–C2)</option>
            <option>Je ne sais pas</option>
        </select>

        <label for="goal">Pourquoi veux-tu apprendre le français ?</label>
        <select name="goal" id="goal" required>
            <option value="">-- Ton objectif principal --</option>
            <option>Voyager dans un pays francophone</option>
            <option>Étudier en France / Canada</option>
            <option>Raisons professionnelles</option>
            <option>Culture et passion pour la langue</option>
            <option>Autre</option>
        </select>

        <label for="accent">Quel accent préfères-tu apprendre ?</label>
        <select name="accent" id="accent" required>
            <option value="">-- Choisis un accent --</option>
            <option>Français de Paris 🇫🇷</option>
            <option>Français du Canada 🇨🇦</option>
            <option>Français de Belgique 🇧🇪</option>
        </select>

        <label for="duration">En combien de temps souhaites-tu atteindre ton objectif ?</label>
        <select name="duration" id="duration" required>
            <option value="">-- Choisis une durée --</option>
            <option>Moins de 3 mois</option>
            <option>3 à 6 mois</option>
            <option>6 à 12 mois</option>
            <option>Plus d’un an</option>
        </select>

        <label>Combien veux-tu pratiquer ?</label>
        <div style="display:flex; gap:10px;">
            <input type="number" name="days" id="days" min="1" max="7" placeholder="Jours / semaine">
            <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minutes / jour">
        </div>

        <div class="chart-container">
            <canvas id="practiceChart" height="200"></canvas>
        </div>

        <label>Quelles compétences aimerais-tu développer ?</label>
        <div class="skills-group">
            <label><input type="checkbox" name="skills[]" value="Entretien professionnel"> Entretien professionnel</label>
            <label><input type="checkbox" name="skills[]" value="Présentation orale"> Présentation orale</label>
            <label><input type="checkbox" name="skills[]" value="Négociation"> Négociation</label>
            <label><input type="checkbox" name="skills[]" value="Réunion ou conférence"> Réunion / conférence</label>
            <label><input type="checkbox" name="skills[]" value="Communication informelle"> Communication informelle</label>
        </div>

        <label for="motivation">Ta motivation à apprendre le français :</label>
        <textarea name="motivation" id="motivation" required placeholder="Ex : J’aime la culture française, la musique, etc."></textarea>

        <button type="submit">Valider mon profil</button>
    </form>

    <footer style="margin-top:25px; font-size:0.9rem; color:#334155;">
        🪄 Veronica AI – Ton coach linguistique intelligent
    </footer>
</div>

<script>
const ctx = document.getElementById('practiceChart');
let chart;

function updateChart() {
    const days = parseInt(document.getElementById('days').value) || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const totalWeekly = days * minutes;
    const totalMonthly = totalWeekly * 4;

    const data = {
        labels: ['Minutes / semaine', 'Minutes / mois'],
        datasets: [{
            label: 'Temps de pratique estimé',
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
    const intro = "Bonjour ! Je suis Veronica AI. Réponds à ces questions pour que je puisse personnaliser ton apprentissage selon ton accent préféré et ton rythme d’étude.";
    const msg = document.getElementById('veronicaMsg');
    msg.textContent = intro;

    if ('speechSynthesis' in window) {
        const utter = new SpeechSynthesisUtterance(intro);
        utter.lang = 'fr-FR';
        window.speechSynthesis.speak(utter);
    }
};
</script>

</body>
</html>

<?php ob_end_flush(); ?>
