<?php
session_start();
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

$theme = $_GET['theme'] ?? 'articles';
$exercises = $_SESSION['exercises'][$theme] ?? [];
$userAnswers = $_SESSION['user_answers'] ?? [];

if (!$exercises) die("Aucun exercice trouvé.");

// --- Calcul du score ---
$results = [];
$score = 0;
$mistakes = [];

foreach ($exercises as $i => $ex) {
    $user = trim($userAnswers[$i] ?? '');
    $correct = trim($ex['answer']);
    if (strcasecmp($user, $correct) == 0) {
        $results[] = ['q' => $ex['question'], 'user' => $user, 'ok' => true];
        $score++;
    } else {
        $results[] = ['q' => $ex['question'], 'user' => $user, 'ok' => false, 'answer' => $correct];
        $mistakes[] = ['question' => $ex['question'], 'user' => $user, 'answer' => $correct];
    }
}

// --- Appel IA pour explications des erreurs ---
$explanations = [];
if ($mistakes) {
    $prompt = "Tu es Veronica AI, professeur de français. Explique calmement et naturellement les erreurs de l'élève.
    Voici les erreurs :
    " . json_encode($mistakes, JSON_UNESCAPED_UNICODE) . "
    Pour chaque erreur, explique en 2-3 phrases pourquoi la réponse est fausse et quelle est la bonne réponse.";

    $payload = [
        "model" => "command-a-vision-07-2025",
        "message" => $prompt,
        "temperature" => 0.5,
        "max_tokens" => 1000
    ];

    $ch = curl_init("https://api.cohere.ai/v1/chat");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . COHERE_API_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($resp, true);
    $explanationText = $result['text'] ?? "L’IA n’a pas pu expliquer les erreurs pour le moment.";
}

// --- Validation ---
$validation = ($score >= 20) ? "🎉 Bravo ! Tu as validé cet exercice." : "⚠️ Tu dois encore t’entraîner.";
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Résultats — Veronica AI</title>
<style>
body{font-family:sans-serif;background:#f4f4f4;padding:30px;}
.container{max-width:900px;margin:auto;background:white;padding:30px;border-radius:10px;}
.correct{color:green;}
.wrong{color:red;}
.result{margin-top:10px;padding:10px;background:#f9fafb;border-radius:8px;}
</style>
</head>
<body>
<div class="container">
    <h1>Résultats de ton test</h1>
    <h2>Score : <?= $score ?>/39</h2>
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
            <li class="wrong"><?= htmlspecialchars($r['q']) ?><br>
                Ta réponse : <b><?= htmlspecialchars($r['user']) ?></b><br>
                Bonne réponse : <b><?= htmlspecialchars($r['answer']) ?></b>
            </li>
        <?php endif; endforeach; ?>
    </ul>

    <div class="result">
        <h3>🧠 Explications de Veronica AI :</h3>
        <p><?= nl2br(htmlspecialchars($explanationText)) ?></p>
    </div>

    <a href="dashboard.php">⬅ Retour au tableau de bord</a>
</div>
</body>
</html>
