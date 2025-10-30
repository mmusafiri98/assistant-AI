<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = $_SESSION['username'];

$host = "localhost";
$dbname = "veronica_ai_login";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur de connexion à la base : " . $e->getMessage();
    exit;
}

// Récupérer le niveau utilisateur
$stmt = $pdo->prepare("SELECT level FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$user_level = $row ? $row['level'] : 'A1';

// Liste des thèmes de leçon
$themes = [
    'articles'            => ['title' => "Les articles (le, la, les)",                   'desc' => "Les articles définissent le genre et le nombre d'un nom en français."],
    'etre_avoir'          => ['title' => "Le présent des verbes être et avoir",           'desc' => "Le verbe être et le verbe avoir sont des verbes essentiels au présent."],
    'pronoms'             => ['title' => "Les pronoms personnels",                        'desc' => "Les pronoms personnels remplacent le nom dans la phrase."],
    'negation'            => ['title' => "La négation simple (ne...pas)",                 'desc' => "La négation simple permet de dire le contraire."],
    'vocabulaire_saluer'  => ['title' => "Se présenter et saluer",                        'desc' => "Savoir saluer et se présenter est essentiel dans la conversation."],
    'vocabulaire_nombres' => ['title' => "Les nombres de 0 à 100",                        'desc' => "Les nombres sont employés pour compter."],
    'vocabulaire_jours'   => ['title' => "Les jours et les mois",                         'desc' => "Les jours et les mois servent pour la date."],
    'vocabulaire_famille' => ['title' => "La famille",                                    'desc' => "Le vocabulaire de la famille est utile pour se présenter."],
    'conversation_restaurant' => ['title' => "Commander au restaurant",                    'desc' => "Le vocabulaire et les phrases pour commander au restaurant."],
    'conversation_chemin'   => ['title' => "Demander son chemin",                         'desc' => "Expressions pour demander son chemin."],
    'conversation_courses'  => ['title' => "Faire les courses",                           'desc' => "Lexique et phrases pour les courses."],
];

$level    = $_GET['level'] ?? $user_level;
$themeKey = $_GET['theme'] ?? 'articles';
$category = $_GET['category'] ?? (strpos($themeKey, 'vocabulaire') !== false ? 'vocabulaire' : (strpos($themeKey, 'conversation') !== false ? 'conversation' : 'grammaire'));

$title = $themes[$themeKey]['title'] ?? "Thème inconnu";
$intro = $themes[$themeKey]['desc'] ?? "Cette leçon est en cours de préparation par Veronica AI.";

// ⚠️ IMPORTANT : Remplace cette clé par ta propre clé API Cohere
// Obtiens ta clé gratuite sur : https://dashboard.cohere.com/api-keys
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

// Variable pour stocker les logs de debug
$debugLogs = [];

// Fonction de prompt selon le thème
function getPromptForTheme($themeKey, $title, $level)
{
    $baseInstruction = "Tu es Veronica AI, professeur de français. Génère EXACTEMENT 50 phrases à trous adaptées au niveau $level. ";
    $formatInstruction = "Réponds UNIQUEMENT avec un tableau JSON valide, sans texte avant ou après. Format strict : [{\"question\":\"phrase avec __\",\"answer\":\"réponse\"},{\"question\":\"...\",\"answer\":\"...\"}]. ";

    switch ($themeKey) {
        case 'articles':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un article défini (le, la, l', les). Varie les noms : animaux, objets, personnes, lieux. " . $formatInstruction . "Exemple : [{\"question\":\"__ soleil brille.\",\"answer\":\"Le\"},{\"question\":\"__ voiture est rouge.\",\"answer\":\"La\"}]";

        case 'etre_avoir':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec une conjugaison du verbe être ou avoir au présent. Varie les pronoms (je, tu, il/elle, nous, vous, ils/elles). " . $formatInstruction . "Exemple : [{\"question\":\"Tu __ content.\",\"answer\":\"es\"},{\"question\":\"Nous __ une maison.\",\"answer\":\"avons\"}]";

        case 'pronoms':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un pronom personnel sujet (je, tu, il, elle, nous, vous, ils, elles). " . $formatInstruction . "Exemple : [{\"question\":\"__ mange une pomme.\",\"answer\":\"Il\"},{\"question\":\"__ parlons français.\",\"answer\":\"Nous\"}]";

        case 'negation':
            return $baseInstruction . "Chaque phrase doit avoir un ou deux trous pour compléter la négation (ne...pas). " . $formatInstruction . "Exemple : [{\"question\":\"Je __ aime __ les épinards.\",\"answer\":\"ne...pas\"},{\"question\":\"Il __ vient __ aujourd'hui.\",\"answer\":\"ne...pas\"}]";

        case 'vocabulaire_saluer':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un mot de salutation ou de présentation (bonjour, salut, au revoir, je m'appelle, enchanté, bonsoir, bonne nuit, comment allez-vous, etc). " . $formatInstruction . "Exemple : [{\"question\":\"__ ! Comment vas-tu ?\",\"answer\":\"Bonjour\"},{\"question\":\"__ , je suis Marie.\",\"answer\":\"Bonjour\"}]";

        case 'vocabulaire_nombres':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un nombre écrit en lettres entre zéro et cent. Varie les contextes : âge, quantité, prix, heure. " . $formatInstruction . "Exemple : [{\"question\":\"J'ai __ ans.\",\"answer\":\"vingt\"},{\"question\":\"Il y a __ élèves.\",\"answer\":\"trente\"}]";

        case 'vocabulaire_jours':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un jour de la semaine (lundi, mardi, mercredi, jeudi, vendredi, samedi, dimanche) ou un mois (janvier, février, mars, avril, mai, juin, juillet, août, septembre, octobre, novembre, décembre). " . $formatInstruction . "Exemple : [{\"question\":\"Aujourd'hui, c'est __.\",\"answer\":\"lundi\"},{\"question\":\"Mon anniversaire est en __.\",\"answer\":\"mai\"}]";

        case 'vocabulaire_famille':
            return $baseInstruction . "Chaque phrase doit avoir un trou __ à remplir avec un mot de vocabulaire de la famille (mère, père, frère, sœur, grand-mère, grand-père, oncle, tante, cousin, cousine, fils, fille, parents, enfants). " . $formatInstruction . "Exemple : [{\"question\":\"Ma __ s'appelle Sophie.\",\"answer\":\"mère\"},{\"question\":\"Mon __ a 10 ans.\",\"answer\":\"frère\"}]";

        case 'conversation_restaurant':
            return $baseInstruction . "Chaque phrase doit être une phrase typique qu'on utilise au restaurant avec un trou __ à remplir (commander, addition, réserver, menu, plat, boisson, etc). " . $formatInstruction . "Exemple : [{\"question\":\"Je voudrais __ une table pour deux personnes.\",\"answer\":\"réserver\"},{\"question\":\"L'__ s'il vous plaît.\",\"answer\":\"addition\"}]";

        case 'conversation_chemin':
            return $baseInstruction . "Chaque phrase doit être utile pour demander ou donner son chemin avec un trou __ à remplir (tourner, tout droit, gauche, droite, rue, avenue, près de, loin de, etc). " . $formatInstruction . "Exemple : [{\"question\":\"Tournez à __.\",\"answer\":\"gauche\"},{\"question\":\"Continuez __ __.\",\"answer\":\"tout droit\"}]";

        case 'conversation_courses':
            return $baseInstruction . "Chaque phrase doit être utile pour faire les courses avec un trou __ à remplir (acheter, pain, légumes, fruits, combien, prix, kilo, grammes, etc). " . $formatInstruction . "Exemple : [{\"question\":\"Je voudrais un __ de tomates.\",\"answer\":\"kilo\"},{\"question\":\"Combien coûte le __ ?\",\"answer\":\"pain\"}]";

        default:
            return $baseInstruction . "Génère 50 exercices variés pour le thème '$title'. " . $formatInstruction;
    }
}

function generateExercisesWithCohere($themeKey, $title, $level)
{
    global $debugLogs;

    $debugLogs[] = "🔵 DÉBUT GÉNÉRATION - Thème: $themeKey, Niveau: $level";

    $api_url = "https://api.cohere.ai/v1/chat";
    $prompt = getPromptForTheme($themeKey, $title, $level);

    $debugLogs[] = "📝 Prompt créé (longueur: " . strlen($prompt) . " caractères)";

    $payload = [
        "model" => "command-a-vision-07-2025",
        "temperature" => 0.3,
        "max_tokens" => 4000,
        "message" => $prompt
    ];

    $debugLogs[] = "📦 Payload préparé - Modèle: command-r-plus";

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . COHERE_API_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $debugLogs[] = "🌐 Envoi de la requête à Cohere API...";

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $debugLogs[] = "📡 Réponse reçue - Code HTTP: $httpCode";

    if ($curlError) {
        $debugLogs[] = "❌ Erreur cURL: $curlError";
        return false;
    }

    if ($httpCode !== 200) {
        $debugLogs[] = "❌ HTTP $httpCode - Réponse: " . substr($resp, 0, 200);
        return false;
    }

    $debugLogs[] = "✅ Statut HTTP 200 OK";

    $result = json_decode($resp, true);
    if (!$result) {
        $debugLogs[] = "❌ Impossible de décoder le JSON";
        return false;
    }

    $debugLogs[] = "✅ JSON décodé avec succès";

    $text = $result['text'] ?? '';
    if (empty($text)) {
        $debugLogs[] = "❌ Pas de texte dans la réponse";
        $debugLogs[] = "Structure reçue: " . json_encode(array_keys($result));
        return false;
    }

    $debugLogs[] = "📄 Texte reçu (longueur: " . strlen($text) . ")";
    $debugLogs[] = "📄 Aperçu: " . substr($text, 0, 150) . "...";

    $text = trim($text);

    if (preg_match('/\[.*\]/s', $text, $matches)) {
        $debugLogs[] = "✅ Tableau JSON trouvé dans le texte";

        $jsonText = $matches[0];
        $json = json_decode($jsonText, true);

        if (is_array($json) && !empty($json)) {
            $debugLogs[] = "✅ Tableau parsé - " . count($json) . " éléments";

            $validExercises = array_filter($json, function ($item) {
                return isset($item['question']) && isset($item['answer']);
            });

            $debugLogs[] = "✅ Exercices valides: " . count($validExercises);

            if (count($validExercises) >= 10) {
                $final = array_slice($validExercises, 0, 50);
                $debugLogs[] = "🎉 SUCCÈS - " . count($final) . " exercices générés";
                return $final;
            } else {
                $debugLogs[] = "⚠️ Pas assez d'exercices valides (minimum 10 requis)";
            }
        } else {
            $debugLogs[] = "❌ Le JSON parsé n'est pas un tableau valide";
        }
    } else {
        $debugLogs[] = "❌ Aucun tableau JSON trouvé dans la réponse";
    }

    $debugLogs[] = "❌ ÉCHEC - Utilisation des exercices de secours";
    return false;
}

// Fonction pour générer une explication détaillée de l'erreur avec l'IA
function generateExplanationWithCohere($question, $correctAnswer, $userAnswer, $themeKey)
{
    $api_url = "https://api.cohere.ai/v1/chat";

    $themeContext = "";
    switch ($themeKey) {
        case 'articles':
            $themeContext = "Cet exercice porte sur les articles définis français (le, la, l', les). ";
            break;
        case 'etre_avoir':
            $themeContext = "Cet exercice porte sur la conjugaison des verbes être et avoir au présent. ";
            break;
        case 'pronoms':
            $themeContext = "Cet exercice porte sur les pronoms personnels sujets en français. ";
            break;
        case 'negation':
            $themeContext = "Cet exercice porte sur la négation simple (ne...pas) en français. ";
            break;
    }

    $prompt = "Tu es Veronica AI, professeur de français. Un élève a fait une erreur dans cet exercice :\n\n";
    $prompt .= $themeContext;
    $prompt .= "Question : \"$question\"\n";
    $prompt .= "Réponse correcte : \"$correctAnswer\"\n";
    $prompt .= "Réponse de l'élève : \"$userAnswer\"\n\n";
    $prompt .= "Explique en 2-3 phrases courtes et claires :\n";
    $prompt .= "1. POURQUOI la réponse correcte est \"$correctAnswer\"\n";
    $prompt .= "2. Quelle est l'ERREUR dans la réponse \"$userAnswer\"\n";
    $prompt .= "3. La RÈGLE grammaticale à retenir\n\n";
    $prompt .= "Ton explication doit être bienveillante, pédagogique et encourageante. Réponds directement sans formule de politesse.";

    $payload = [
        "model" => "command-a-vision-07-2025",
        "temperature" => 0.5,
        "max_tokens" => 300,
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return "La bonne réponse est '$correctAnswer'. Révise cette règle et réessaie !";
    }

    $result = json_decode($resp, true);
    $explanation = $result['text'] ?? '';

    if (empty($explanation)) {
        return "La bonne réponse est '$correctAnswer'. Révise cette règle et réessaie !";
    }

    return trim($explanation);
}

// Gérer la soumission des réponses (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $userAnswers = $input['answers'] ?? [];
    $exercises = $input['exercises'] ?? [];

    if (empty($exercises)) {
        echo json_encode(['success' => false, 'error' => 'Exercices manquants']);
        exit;
    }

    $feedback = [];
    $correct = 0;

    foreach ($exercises as $index => $exercise) {
        $expected = trim($exercise['answer']);
        $user = isset($userAnswers[$index]) ? trim($userAnswers[$index]) : '';

        $isCorrect = (strcasecmp($expected, $user) === 0);
        if ($isCorrect) {
            $correct++;
            $explanation = "Bravo ! Tu as bien compris la règle. Continue comme ça ! 🎉";
        } else {
            if ($category === 'grammaire') {
                $explanation = generateExplanationWithCohere(
                    $exercise['question'],
                    $expected,
                    $user,
                    $themeKey
                );
            } else {
                $explanation = "La bonne réponse est '$expected'. Révise ce vocabulaire !";
            }
        }

        $feedback[] = [
            'index' => $index,
            'correct' => $isCorrect,
            'expected' => $expected,
            'user' => $user,
            'explanation' => $explanation
        ];
    }

    $score = round(($correct / count($exercises)) * 10, 1);
    $status = $score >= 7 ? 'validé' : 'non validé';

    echo json_encode([
        'success' => true,
        'score' => $score,
        'status' => $status,
        'feedback' => $feedback
    ]);
    exit;
}

// Générer les exercices pour l'affichage
$exercises = generateExercisesWithCohere($themeKey, $title, $level);

// Si la génération échoue, utiliser des exercices de secours ADAPTÉS AU THÈME
if (!$exercises) {
    $debugLogs[] = "⚠️ Utilisation des exercices de secours pour: $themeKey";

    switch ($themeKey) {
        case 'articles':
            $exercises = [
                ['question' => '__ chat dort sur le canapé.', 'answer' => 'Le'],
                ['question' => '__ maison est grande.', 'answer' => 'La'],
                ['question' => '__ élèves étudient le français.', 'answer' => 'Les'],
                ['question' => "__ arbre est dans le jardin.", 'answer' => "L'"],
                ['question' => '__ oiseau chante.', 'answer' => "L'"],
            ];
            break;

        case 'etre_avoir':
            $exercises = [
                ['question' => 'Je __ heureux.', 'answer' => 'suis'],
                ['question' => 'Tu __ un vélo.', 'answer' => 'as'],
                ['question' => 'Il __ professeur.', 'answer' => 'est'],
                ['question' => 'Nous __ fatigués.', 'answer' => 'sommes'],
                ['question' => 'Vous __ raison.', 'answer' => 'avez'],
            ];
            break;

        case 'pronoms':
            $exercises = [
                ['question' => '__ parle français.', 'answer' => 'Je'],
                ['question' => '__ manges une pomme.', 'answer' => 'Tu'],
                ['question' => '__ dort beaucoup.', 'answer' => 'Il'],
                ['question' => '__ travaillons ensemble.', 'answer' => 'Nous'],
                ['question' => '__ aimez le sport.', 'answer' => 'Vous'],
            ];
            break;

        case 'negation':
            $exercises = [
                ['question' => 'Je __ aime __ les épinards.', 'answer' => 'ne...pas'],
                ['question' => 'Il __ vient __ aujourd\'hui.', 'answer' => 'ne...pas'],
                ['question' => 'Nous __ parlons __ anglais.', 'answer' => 'ne...pas'],
                ['question' => 'Tu __ es __ content.', 'answer' => 'ne...pas'],
                ['question' => 'Elle __ a __ de voiture.', 'answer' => 'ne...pas'],
            ];
            break;

        case 'vocabulaire_saluer':
            $exercises = [
                ['question' => '__ ! Comment allez-vous ?', 'answer' => 'Bonjour'],
                ['question' => '__ ! À demain !', 'answer' => 'Au revoir'],
                ['question' => 'Je __ Pierre.', 'answer' => "m'appelle"],
                ['question' => '__, enchanté de vous rencontrer.', 'answer' => 'Bonsoir'],
                ['question' => '__ bien, merci !', 'answer' => 'Très'],
            ];
            break;

        default:
            $exercises = [
                ['question' => 'Exercice exemple 1 __.', 'answer' => 'test'],
                ['question' => 'Exercice exemple 2 __.', 'answer' => 'test'],
                ['question' => 'Exercice exemple 3 __.', 'answer' => 'test'],
            ];
    }

    $errorMessage = "⚠️ Erreur lors de la génération des exercices avec l'IA. Voici des exercices de démonstration pour le thème \"$title\".";
}
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — Veronica AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .debug-panel {
            background: #1f2937;
            color: #10b981;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #374151;
        }

        .debug-panel h3 {
            color: #60a5fa;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .debug-log {
            margin: 4px 0;
            padding: 4px 0;
            border-left: 3px solid transparent;
            padding-left: 8px;
        }

        .debug-log:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #4f46e5;
            transform: translateX(-5px);
        }

        h1 {
            color: #1f2937;
            margin-bottom: 16px;
            font-size: 2rem;
            font-weight: 700;
        }

        .badges {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
        }

        .intro {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 24px;
            font-size: 1.05rem;
        }

        .error-message {
            background: #fef3c7;
            color: #92400e;
            padding: 16px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
            font-weight: 500;
        }

        .section-title {
            color: #1f2937;
            margin: 24px 0 16px 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .exercise {
            background: #f9fafb;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .exercise:hover {
            border-color: #c7d2fe;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }

        .question-text {
            color: #374151;
            font-size: 1rem;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .question-number {
            color: #4f46e5;
            font-weight: 700;
            margin-right: 8px;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            margin-top: 8px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .feedback {
            margin-top: 12px;
            padding: 14px;
            border-radius: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-correct {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .result-wrong {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .feedback-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .answer-details {
            margin-top: 10px;
            font-size: 0.95rem;
        }

        .answer-row {
            margin-top: 6px;
            padding: 6px 0;
        }

        .answer-label {
            font-weight: 600;
        }

        .correct-answer {
            color: #059669;
            font-weight: 700;
        }

        .wrong-answer {
            color: #dc2626;
            font-weight: 700;
        }

        .explanation {
            margin-top: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            font-size: 0.95rem;
            line-height: 1.6;
            border-left: 3px solid #64748b;
        }

        .score-display {
            margin-top: 24px;
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
            animation: slideDown 0.5s ease;
        }

        .score-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .score-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .score-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .toggle-debug {
            background: #374151;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .toggle-debug:hover {
            background: #1f2937;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="lessons.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px;">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Retour aux leçons
        </a>

        <?php if (!empty($debugLogs)): ?>
            <button class="toggle-debug" onclick="document.getElementById('debug-panel').style.display = document.getElementById('debug-panel').style.display === 'none' ? 'block' : 'none'">
                🔍 Afficher/Masquer les logs de debug
            </button>

            <div class="debug-panel" id="debug-panel">
                <h3>📊 Logs de génération des exercices (Thème: <?= htmlspecialchars($themeKey) ?>)</h3>
                <?php foreach ($debugLogs as $log): ?>
                    <div class="debug-log"><?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h1><?= htmlspecialchars($title) ?></h1>

        <div class="badges">
            <span class="badge">📚 Niveau <?= htmlspecialchars($level) ?></span>
            <span class="badge">
                <?php
                $icon = $category === 'grammaire' ? '✍️' : ($category === 'vocabulaire' ? '📖' : '💬');
                echo $icon . ' ' . ucfirst(htmlspecialchars($category));
                ?>
            </span>
            <span class="badge">🎯 Thème: <?= htmlspecialchars($themeKey) ?></span>
        </div>

        <p class="intro"><?= htmlspecialchars($intro) ?></p>

        <?php if (isset($errorMessage)): ?>
            <div class="error-message">
                <?= htmlspecialchars($errorMessage) ?>
                <br><br>
                <strong>💡 Consulte les logs de debug ci-dessus pour comprendre pourquoi l'IA n'a pas généré les exercices.</strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($exercises)): ?>
            <h3 class="section-title">
                📝 Exercices pratiques (<?= count($exercises); ?> questions)
            </h3>

            <div id="exercises">
                <?php foreach ($exercises as $index => $exercise): ?>
                    <div class="exercise" data-index="<?= $index ?>">
                        <div class="question-text">
                            <span class="question-number"><?= ($index + 1) ?>.</span>
                            <?= htmlspecialchars($exercise['question']) ?>
                        </div>
                        <input
                            type="text"
                            placeholder="Écris ta réponse ici..."
                            class="answer-input"
                            autocomplete="off" />
                        <div class="feedback" style="display:none;"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="submit-btn" id="submit-btn">
                ✅ Terminer et recevoir la correction
            </button>

            <div id="overall" style="display:none"></div>
        <?php else: ?>
            <p class="intro">Aucun exercice disponible pour l'instant. Contacte ton professeur.</p>
        <?php endif; ?>
    </div>

    <script>
        const exercisesData = <?= json_encode($exercises) ?>;
        const themeKey = <?= json_encode($themeKey) ?>;
        const category = <?= json_encode($category) ?>;

        console.log('🎯 Page chargée pour le thème:', themeKey);
        console.log('📚 Nombre d\'exercices:', exercisesData.length);

        document.getElementById('submit-btn').addEventListener('click', async function() {
            const btn = this;

            const exercises = document.querySelectorAll('#exercises .exercise');
            const answers = Array.from(exercises).map(el => el.querySelector('.answer-input').value.trim());

            const hasAnswers = answers.some(answer => answer !== '');
            if (!hasAnswers) {
                alert('⚠️ Tu dois répondre à au moins une question avant de soumettre !');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '⏳ Correction en cours...';

            exercises.forEach(el => {
                const fb = el.querySelector('.feedback');
                fb.style.display = 'none';
                fb.innerHTML = '';
            });
            document.getElementById('overall').style.display = 'none';

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        answers: answers,
                        exercises: exercisesData
                    })
                });

                if (!res.ok) {
                    throw new Error('Erreur serveur (code ' + res.status + ')');
                }

                const json = await res.json();

                if (!json.success) {
                    throw new Error(json.error || 'Erreur lors de l\'évaluation');
                }

                json.feedback.forEach(fb => {
                    const container = document.querySelector('#exercises .exercise[data-index="' + fb.index + '"]');
                    const fbDiv = container.querySelector('.feedback');
                    fbDiv.style.display = 'block';

                    if (fb.correct) {
                        fbDiv.className = 'feedback result-correct';
                        fbDiv.innerHTML = `
                            <div class="feedback-title">✅ Excellente réponse !</div>
                            <div class="explanation">📚 ${escapeHtml(fb.explanation)}</div>
                        `;
                    } else {
                        fbDiv.className = 'feedback result-wrong';
                        let html = '<div class="feedback-title">❌ Pas tout à fait...</div>';
                        html += '<div class="answer-details">';
                        html += '<div class="answer-row"><span class="answer-label">✓ Réponse correcte :</span> <span class="correct-answer">' + escapeHtml(fb.expected) + '</span></div>';
                        if (fb.user) {
                            html += '<div class="answer-row"><span class="answer-label">✗ Ta réponse :</span> <span class="wrong-answer">' + escapeHtml(fb.user) + '</span></div>';
                        } else {
                            html += '<div class="answer-row"><span class="answer-label">✗ Ta réponse :</span> <span class="wrong-answer">(non répondu)</span></div>';
                        }
                        html += '</div>';
                        html += '<div class="explanation">📚 ' + escapeHtml(fb.explanation) + '</div>';
                        fbDiv.innerHTML = html;
                    }
                });

                const overall = document.getElementById('overall');
                overall.style.display = 'block';
                const isValidated = json.status === 'validé';
                const scoreColor = isValidated ? '#10b981' : '#f59e0b';
                const statusBadge = isValidated ?
                    '<span style="background:#d1fae5;color:#065f46;padding:8px 20px;border-radius:999px;font-weight:700;">🎉 Validé</span>' :
                    '<span style="background:#fed7aa;color:#92400e;padding:8px 20px;border-radius:999px;font-weight:700;">📖 Non validé</span>';

                overall.innerHTML = `
                    <div class="score-display" style="border-left: 4px solid ${scoreColor}">
                        <div class="score-content">
                            <div>
                                <span class="score-label">📊 Ton score :</span>
                                <span class="score-value" style="color: ${scoreColor}">${json.score}/10</span>
                            </div>
                            ${statusBadge}
                        </div>
                    </div>
                `;

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                if (isValidated) {
                    setTimeout(() => {
                        if (confirm(`🎉 Félicitations ! Tu as obtenu ${json.score}/10.\n\nCette leçon est validée !\n\nVeux-tu retourner aux leçons ?`)) {
                            window.location.href = 'lessons.php';
                        }
                    }, 800);
                } else {
                    setTimeout(() => {
                        alert(`📖 Tu as obtenu ${json.score}/10.\n\nLis bien les explications ci-dessous pour comprendre tes erreurs.\n\nIl te faut au moins 7/10 pour valider la leçon.\n\nCourage, tu peux réessayer !`);
                    }, 800);
                }
            } catch (error) {
                alert('❌ Erreur : ' + error.message + '\n\nVérifie ta connexion internet et réessaie.');
                console.error('Erreur détaillée:', error);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '✅ Terminer et recevoir la correction';
            }
        });

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            };
            return text.replace(/[&<>"'`=\/]/g, s => map[s]);
        }

        const inputs = document.querySelectorAll('.answer-input');
        inputs[inputs.length - 1]?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('submit-btn').click();
            }
        });
    </script>
</body>

</html>