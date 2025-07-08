<?php
// Démarrer la session
session_start();

// Afficher les informations de session avant réinitialisation (pour debug)
echo "<h3>Contenu de la session avant réinitialisation:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Détruire toutes les données de session
$_SESSION = array();

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

echo "<h3>Session réinitialisée avec succès!</h3>";
echo "<p>Toutes les données de session ont été supprimées.</p>";
echo "<a href='index.php' class='btn btn-primary'>Retourner à la page d'accueil</a>";
?>