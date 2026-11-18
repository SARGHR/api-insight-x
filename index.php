<?php

session_start();
header("X-Content-Type-Options: nosniff");
// ini_set("session.save_path", "./sessions"); //


require("tbs_class.php");
require("models/Database.php");
require("models/ApiAnalyzer.php");
require("models/ScanManager.php");
require("controllers/AuthController.php");
require("controllers/ScanController.php");

//Objet TinyButStrong
$tbs = new clsTinyButStrong;
$tbs->NoErr = true;


$message      = "";
$messageType  = "";
$view         = "views/home.html";

if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(16));
// liens
$lienAdmin    = "index.php?action=login";
$libelleAdmin = "Admin";
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged']) {
    $lienAdmin    = "index.php?action=logout";
    $libelleAdmin = "Déconnexion";
}

// connexion try/catch
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $login, $password, [PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $authCtrl = new AuthController();
    $scanMgr  = new ScanManager($pdo);
    $scanCtrl = new ScanController($scanMgr);

    //routeur switch
    $action = "";
    if (isset($_GET["action"]))  $action = $_GET["action"];
    if (isset($_POST["action"])) $action = $_POST["action"];

    switch ($action) {

        case "analyze":
            if (isset($_POST["api_url"]) && trim($_POST["api_url"]) !== "") {
                $url = trim($_POST["api_url"]);
                if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== "https") {
                    $message = "Utilise une URL HTTPS valide.";
                    break;
                }
                $hostname = parse_url($url, PHP_URL_HOST);
                $ip = gethostbyname($hostname);
                if ($ip === $hostname || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $message = "Adresse non publique ou inaccessible.";
                    break;
                }
                $r = $scanCtrl->runAnalysis(trim($_POST["api_url"]));

                $treeKeys  = [];
                $treeTypes = [];
                foreach ($r["tree"] as $k => $t) {
                    $treeKeys[]  = $k;
                    $treeTypes[] = $t;
                }

                $tbs->LoadTemplate("views/dashboard.html");

                // Champs onshow
                $tbs->MergeField("url",          $r["url"]);
                $tbs->MergeField("httpCode",      $r["http_code"]);
                $tbs->MergeField("httpClass",     ($r["http_code"] < 400) ? "color-ok" : "color-err");
                $tbs->MergeField("responseTime",  $r["response_time_ms"]);
                $tbs->MergeField("responseSize",  number_format($r["response_size"] / 1024, 1));
                $tbs->MergeField("itemCount",     $r["item_count"] ?? "—");
                $tbs->MergeField("score",         $r["score"]);
                $tbs->MergeField("scorePerf",     $r["score_performance"]);
                $tbs->MergeField("scoreSecu",     $r["score_security"]);
                $tbs->MergeField("scoreStruct",   $r["score_structure"]);
                $tbs->MergeField("apiType",       $r["api_type"]);
                $tbs->MergeField("resourceType",  $r["resource_type"]);
                $tbs->MergeField("structure",     $r["structure"]);
                $raw = $r["raw_response"] ?? "";
$decoded = json_decode($raw, true);

if (json_last_error() === JSON_ERROR_NONE) {
    $formatted = json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
} else {
    $formatted = $raw;
}

$tbs->MergeField(
    "rawResponse",
    substr($formatted ?: "Aucune réponse disponible", 0, 1500)
);
                $tbs->MergeField("lienAdmin",     $lienAdmin);
                $tbs->MergeField("libelleAdmin",  $libelleAdmin);


                $tbs->MergeBlock("tree",  $treeKeys);
                $tbs->MergeBlock("tree",  $treeTypes);
                $tbs->MergeBlock("sim",   $r["simulations"]);
                $tbs->MergeBlock("warn",  $r["warnings"]);
                $tbs->MergeBlock("rec",   $r["recommendations"]);

                $tbs->Show();
                exit;
            }
            $message     = "Veuillez saisir une URL valide.";
            $messageType = "alert-error";
            break;

        // --- Historique ---
        case "history":
            $scans = $scanCtrl->getHistory();
            $lignes = [];
            foreach ($scans as $s) {
                $cls   = ($s['score'] >= 70) ? 'score-high' : (($s['score'] >= 40) ? 'score-medium' : 'score-low');
                $lien  = (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'])
                         ? '<form method="post" action="index.php" onsubmit="return confirm(\'Supprimer ?\')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$s['id'] . '"><input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '"><button class="btn btn-danger btn-sm">Effacer</button></form>'
                         : '';
                $lignes[] = [
                    "id"         => $s['id'],
                    "url"        => htmlspecialchars($s['url']),
                    "http_code"  => $s['http_code'],
                    "score"      => '<span class="score-badge ' . $cls . '">' . $s['score'] . '/100</span>',
                    "created_at" => $s['created_at'],
                    "lien_suppr" => $lien,
                ];
            }
            $colonneAction = (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged']) ? "Action" : "";

            $tbs->LoadTemplate("views/history.html");
            $tbs->MergeField("message",       $message);
            $tbs->MergeField("messageType",   $messageType);
            $tbs->MergeField("lienAdmin",     $lienAdmin);
            $tbs->MergeField("libelleAdmin",  $libelleAdmin);
            $tbs->MergeField("colonneAction", $colonneAction);
            $tbs->MergeBlock("scan", $lignes);
            $tbs->Show();
            exit;

        //Supp
        case "delete":
            if ($_SERVER["REQUEST_METHOD"] === "POST" && $authCtrl->isLogged() && isset($_POST["id"]) && hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "invalid")) {
                $scanCtrl->deleteScan((int)$_POST["id"]);
                $message     = "Analyse supprimée.";
                $messageType = "alert-success";
            } else {
                $message     = "Action réservée à l'administrateur.";
                $messageType = "alert-error";
            }

            header("Location: index.php?action=history");
            exit;

        //formul
        case "login":
            $tbs->LoadTemplate("views/login.html");
            $tbs->MergeField("message",      $message);
            $tbs->MergeField("messageType",  $messageType);
            $tbs->Show();
            exit;

        // trtmt
        case "do_login":
            $res = $authCtrl->doLogin();
            $message     = $res["message"];
            $messageType = $res["type"];
            if ($res["success"]) {
                $lienAdmin    = "index.php?action=logout";
                $libelleAdmin = "Déconnexion";
                header("Location: index.php?action=history");
                exit;
            }
            $tbs->LoadTemplate("views/login.html");
            $tbs->MergeField("message",     $message);
            $tbs->MergeField("messageType", $messageType);
            $tbs->Show();
            exit;

        // logout
        case "logout":
            $authCtrl->doLogout();
            header("Location: index.php");
            exit;


        default:
            break;
    }

} catch (Throwable $erreur) {
    error_log($erreur->getMessage());
    $message     = "Une erreur est survenue. Vérifie la configuration ou l’URL de l’API.";
    $messageType = "alert-error";
}


$tbs->LoadTemplate("views/home.html");
$tbs->Show();
