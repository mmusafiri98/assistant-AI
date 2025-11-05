<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// --- Configuration de la base Neon ---
$db_host = 'ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech';
$db_port = '5432';
$db_name = 'veronica_db_login';
$db_user = 'neondb_owner';
$db_pass = 'npg_QolPDv5L9gVj';

// --- Connexion à la base de données ---
try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// --- Si l’utilisateur n’est pas connecté, on utilise un nom générique ---
$username = $_SESSION['username'] ?? 'invité';

// --- Récupérer le niveau utilisateur si disponible ---
try {
    $stmt = $pdo->prepare("SELECT level FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_level = $row ? $row['level'] : 'A1';
} catch (PDOException $e) {
    $user_level = 'A1';
}

// --- Liste des thèmes ---
$themes = [
    'articles'            => ['title' => "Les articles (le, la, les)",                   'desc' => "Les articles définissent le genre et le nombre d'un nom en français."],
    'etre_avoir'          => ['title' => "Le présent des verbes être et avoir",          'desc' => "Les verbes être et avoir sont essentiels au présent."],
    'pronoms'             => ['title' => "Les pronoms personnels",                       'desc' => "Les pronoms personnels remplacent un nom dans la phrase."],
    'negation'            => ['title' => "La négation simple (ne...pas)",                'desc' => "La négation simple permet de dire le contraire."],
    'vocabulaire_saluer'  => ['title' => "Se présenter et saluer",                       'desc' => "Savoir saluer et se présenter est essentiel."],
    'vocabulaire_nombres' => ['title' => "Les nombres de 0 à 100",                       'desc' => "Les nombres sont employés pour compter."],
    'vocabulaire_jours'   => ['title' => "Les jours et les mois",                        'desc' => "Les jours et les mois servent à parler des dates."],
    'vocabulaire_famille' => ['title' => "La famille",                                   'desc' => "Le vocabulaire de la famille est utile pour se présenter."],
    'conversation_restaurant' => ['title' => "Commander au restaurant",                  'desc' => "Expressions pour commander au restaurant."],
    'conversation_chemin' => ['title' => "Demander son chemin",                          'desc' => "Phrases pour demander ou indiquer le chemin."],
    'conversation_courses'=> ['title' => "Faire les courses",                            'desc' => "Lexique et phrases pour faire les courses."],
];

$level    = $_GET['level'] ?? $user_level;
$themeKey = $_GET['theme'] ?? 'articles';
$category = $_GET['category'] ?? (
    strpos($themeKey, 'vocabulaire') !== false ? 'vocabulaire' :
    (strpos($themeKey, 'conversation') !== false ? 'conversation' : 'grammaire')
);

$title = $themes[$themeKey]['title'] ?? "Thème inconnu";
$intro = $themes[$themeKey]['desc'] ?? "Cette leçon est en cours de préparation par Veronica AI.";

// --- Clé API Cohere (remplace par la tienne) ---
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

// Fonction utilitaire pour le prompt AI
function getPromptForTheme($themeKey, $title, $level) {
    $base = "Tu es Veronica AI, professeur de français. Génère EXACTEMENT 50 phrases à trous adaptées au niveau $level. ";
    $format = "Réponds UNIQUEMENT avec un tableau JSON valide sans texte avant ou après. Format : [{\"question\":\"__ phrase\",\"answer\":\"mot\"}]. ";

    switch ($themeKey) {
        case 'articles':
            return $base . "Chaque phrase doit contenir un article défini à compléter (le, la, l', les). " . $format;
        case 'etre_avoir':
            return $base . "Complète chaque phrase avec une forme correcte des verbes être ou avoir au présent. " . $format;
        case 'pronoms':
            return $base . "Complète chaque phrase avec un pronom personnel sujet (je, tu, il, nous...). " . $format;
        default:
            return $base . "Génère des phrases adaptées au thème '$title'. " . $format;
    }
}

// Fonction de génération IA via Cohere
function generateExercisesWithCohere($themeKey, $title, $level) {
    $api_url = "https://api.cohere.ai/v1/chat";
    $prompt = getPromptForTheme($themeKey, $title, $level);

    $payload = [
        "model" => "command-a-vision-07-2025",
        "temperature" => 0.3,
        "max_tokens" => 4000,
        "message" => $prompt
    ];

    $ch = curl_init($api_url);
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
    $text = $result['text'] ?? '';

    if (preg_match('/\[.*\]/s', $text, $matches)) {
        $json = json_decode($matches[0], true);
        if (is_array($json) && !empty($json)) return array_slice($json, 0, 50);
    }
    return false;
}

// Générer les exercices
$exercises = generateExercisesWithCohere($themeKey, $title, $level);

// Exercices de secours si IA échoue
if (!$exercises) {
    $exercises = [
        ['question' => '__ chat dort sur le canapé.', 'answer' => 'Le'],
        ['question' => '__ maison est grande.', 'answer' => 'La'],
        ['question' => '__ élèves étudient le français.', 'answer' => 'Les']
    ];
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?> — Veronica AI</title>
<style>
body{font-family:sans-serif;background:#f4f4f4;padding:30px;}
.container{max-width:800px;margin:auto;background:white;padding:30px;border-radius:10px;}
h1{color:#333;}
.exercise{background:#f9fafb;padding:15px;margin:10px 0;border-radius:8px;}
input{width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;}
button{padding:12px 20px;border:none;background:#4f46e5;color:white;border-radius:8px;cursor:pointer;}
</style>
</head>
<body>
<div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($intro) ?></p>

    <?php foreach ($exercises as $i => $ex): ?>
        <div class="exercise">
            <strong><?= ($i+1) ?>.</strong> <?= htmlspecialchars($ex['question']) ?><br>
            <input type="text" placeholder="Ta réponse...">
        </div>
    <?php endforeach; ?>

    <button>✅ Soumettre</button>
</div>
</body>
</html>


