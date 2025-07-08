<?php
// Fonctions d'authentification et d'autorisation

/**
 * Génère un token CSRF
 * @return string Le token CSRF généré
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si l'utilisateur est connecté, false sinon
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur connecté est un administrateur
 * @return bool True si l'utilisateur est admin, false sinon
 */
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}
function isresponsable() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'responsable';}
/**
 * Vérifie si l'utilisateur connecté est un étudiant
 * @return bool True si l'utilisateur est étudiant, false sinon
 */
function isEtudiant() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'etudiant';
}

/**
 * Redirige vers le tableau de bord approprié si l'utilisateur est déjà connecté
 */
function redirect_if_logged_in() {
    if (isset($_SESSION['user_type'])) {
        switch ($_SESSION['user_type']) {
            case 'admin':
                header('Location: dashboard_admin.php');
                exit;
            case 'responsable':
                header('Location: dashboard_responsable.php');
                exit;
            case 'etudiant':
                header('Location: dashboard_etudiant.php');
                exit;
        }
    }
}


/**
 * Vérifie que l'utilisateur est un administrateur, sinon redirige
 */
function require_admin() {
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est un étudiant, sinon redirige
 */
function require_etudiant() {
    if (!isLoggedIn() || !isEtudiant()) {
        header('Location: index.php');
        exit();
    }
}
function require_responsable() {
    if (!isLoggedIn() || !isresponsable()) {
        header('Location: index.php');
        exit();
    }
}
/**
 * Échappe les caractères spéciaux HTML
 * @param string $text Le texte à échapper
 * @return string Le texte échappé
 */
function escape_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}