<?php
ob_start(); // Active le tampon de sortie pour éviter les erreurs de header

// --- Démarrage sécurisé de la session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax', // Garde la session après redirection
    ]);
}

// ====== Vérification de la session ======
if (empty($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// ====== Clé API Cohere ======
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

// --- Récupération du thème actuel et des exercices ---
$theme = $_SESSION['current_theme'] ?? 'articles';
$exercises = $_SESSION['exercises'][$theme] ?? [];
$userAnswers = $_SESSION['user_answers'] ?? [];

if (empty($exercises)) {
    die("<h3 style='color:red;'>❌ Aucun exercice trouvé dans la session. Retourne sur la page des leçons.</h3>");
}

// --- Calcul du score et collecte des erreurs ---
$results = [];
$score = 0;
$mistakes = [];

foreach ($exercises as $i => $ex) {
    $user = trim($userAnswers[$i] ?? '');
    $correct = trim($ex['answer']);

    if (strcasecmp($user, $correct) === 0) {
        $results[] = [
            'q' => $ex['question'],
            'user' => $user,
            'ok' => true
        ];
        $score++;
    } else {
        $results[] = [
            'q' => $ex['question'],
            'user' => $user,
            'ok' => false,
            'answer' => $correct
        ];
        $mistakes[] = [
            'question' => $ex['question'],
            'user' => $user,
            'answer' => $correct
        ];
    }
}

// --- Appel à l’API Cohere pour explications naturelles ---
$explanationText = "Aucune erreur détectée. Excellent travail !";

if (!empty($mistakes)) {
    $prompt = "Tu es Veronica AI, professeur de français. Explique à l’élève ses erreurs calmement.
Voici les erreurs :
" . json_encode($mistakes, JSON_UNESCAPED_UNICODE) . "
Pour chaque erreur, explique pourquoi la réponse est incorrecte et donne la bonne réponse, de manière naturelle et bienveillante.";

    $payload = [
        "model" => "command-a-vision-07-2025",
        "message" => $prompt,
        "temperature" => 0.5,
        "max_tokens" => 1000
    ];

    $ch = curl_init("https://api.cohere.ai/v1/chat");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . COHERE_API_KEY,
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($resp, true);
    $explanationText = $result['text'] ?? "⚠️ L’IA n’a pas pu générer les explications pour le moment.";
}

// --- Message de validation ---
$validation = ($score >= count($exercises) * 0.8)
    ? "🎉 Bravo ! Tu as validé ce thème avec succès."
    : "⚠️ Tu dois encore t’entraîner un peu.";

// --- Récupération du nom d’utilisateur ---
$username = htmlspecialchars($_SESSION['username']);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Résultats — Veronica AI</title>
<style>
body {
    font-family: "Poppins", sans-serif;
    background: #f4f4f4;
    padding: 30px;
}
.container {
    max-width: 900px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.correct { color: #16a34a; }
.wrong { color: #dc2626; }
.result {
    margin-top: 10px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
}
a.button {
    display: inline-block;
    margin-top: 20px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
a.button:hover {
    opacity: 0.9;
}
h1, h2, h3 { color: #1e293b; }
</style>
</head>
<body>
<div class="container">
    <h1>Résultats du thème <em><?= htmlspecialchars($theme) ?></em></h1>
    <h2>👋 Bonjour <?= $username ?></h2>
    <p><strong>Score :</strong> <?= $score ?>/<?= count($exercises) ?></p>
    <p><?= $validation ?></p>

    <h3>🟢 Réponses correctes :</h3>
    <ul>
        <?php foreach ($results as $r): if ($r['ok']): ?>
            <li class="correct"><?= htmlspecialchars($r['q']) ?> — <b><?= htmlspecialchars($r['user']) ?></b></li>
        <?php endif; endforeach; ?>
    </ul>

    <h3>🔴 Réponses incorrectes :</h3>
    <ul>
        <?php foreach ($results as $r): if (!$r['ok']): ?>
            <li class="wrong">
                <?= htmlspecialchars($r['q']) ?><br>
                Ta réponse : <b><?= htmlspecialchars($r['user']) ?></b><br>
                Bonne réponse : <b><?= htmlspecialchars($r['answer']) ?></b>
            </li>
        <?php endif; endforeach; ?>
    </ul>

    <div class="result">
        <h3>🧠 Explications de Veronica AI :</h3>
        <p><?= nl2br(htmlspecialchars($explanationText)) ?></p>
    </div>

    <a href="dashboard.php" class="button">⬅ Retour au tableau de bord</a>
</div>
</body>
</html>
<?php ob_end_flush(); ?>


