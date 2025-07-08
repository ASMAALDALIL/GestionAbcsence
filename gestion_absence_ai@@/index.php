<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Démarrer la session
session_start();

// Inclure les fichiers nécessaires
require_once 'config/db.php';
require_once 'includes/auth.php';

// Rediriger si l'utilisateur est déjà connecté
redirect_if_logged_in();

// Variables pour la gestion des formulaires
$error = '';
$username = '';
$type = isset($_GET['type']) ? $_GET['type'] : 'etudiant';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité. Veuillez réessayer.';
    } else {
        
        // Récupérer les données du formulaire
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $type = $_POST['type'];
        
        // Valider les entrées
        if (empty($username) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            try {
                // Authentification en fonction du type d'utilisateur
                if ($type === 'admin') {
                    // Authentification admin
                    $stmt = $pdo->prepare("SELECT id, username, password, nom, prenom FROM administrateurs WHERE username = :username");
                    $stmt->execute(['username' => $username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Connexion réussie
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        
                        // Redirection vers le tableau de bord admin
                        header('Location: dashboard_admin.php');
                        exit;
                    } else {
                        $error = 'Identifiants incorrects.';
                        }}
                elseif ($type === 'responsable') {
    try {
        // Préparer et exécuter la requête pour chercher le responsable par email
        $stmt = $pdo->prepare("SELECT id, email, password, nom, prenom FROM responsables WHERE email = :email");
        $stmt->execute(['email' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Authentification réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'responsable';
            $_SESSION['username'] = $user['email'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];

            // Redirection vers le tableau de bord du responsable
            header('Location: dashboard_responsable.php');
            exit;
        } else {
            $error = 'Identifiants incorrects pour le responsable.';
        }
    } catch (PDOException $e) {
        $error = 'Erreur lors de la connexion à la base de données : ' . $e->getMessage();
    }
}
    else {
                    // Authentification étudiant
                    $stmt = $pdo->prepare("SELECT id, numero_apogee, password, nom, prenom, filiere_id FROM etudiants WHERE numero_apogee = :numero_apogee");
                    $stmt->execute(['numero_apogee' => $username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Connexion réussie
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'etudiant';
                        $_SESSION['username'] = $user['numero_apogee'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        $_SESSION['filiere_id'] = $user['filiere_id'];
                        
                        // Redirection vers le tableau de bord étudiant
                        header('Location: dashboard_etudiant.php');
                        exit;
                    } else {
                        $error = 'Identifiants incorrects.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
            }
        }
    }
}

// Générer un nouveau token CSRF
$csrf_token = generate_csrf_token();

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card auth-form">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4><i class="fas fa-sign-in-alt me-2"></i> Connexion</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape_html($error); ?>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-pills mb-4 justify-content-center" id="loginTabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($type === 'etudiant') ? 'active' : ''; ?>" href="#" data-type="etudiant">
                            <i class="fas fa-user-graduate me-1"></i> Étudiant
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($type === 'admin') ? 'active' : ''; ?>" href="#" data-type="admin">
                            <i class="fas fa-user-shield me-1"></i> Administrateur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($type === 'responsable') ? 'active' : ''; ?>" href="#" data-type="responsable">
                            <i class="fas fa-chalkboard-teacher me-1"></i> Responsable
                        </a>
                    </li>
                </ul>

                <form method="post" action="index.php" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                    <input type="hidden" name="type" id="userType" value="<?php echo escape_html($type); ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <span class="student-label <?php echo ($type !== 'etudiant') ? 'd-none' : ''; ?>">Numéro Apogée</span>
                            <span class="admin-label <?php echo ($type !== 'admin') ? 'd-none' : ''; ?>">Nom d'utilisateur</span>
                            <span class="responsable-label <?php echo ($type !== 'responsable') ? 'd-none' : ''; ?>">Email</span>
                        </label>

                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo escape_html($username); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <p class="student-text <?php echo ($type !== 'etudiant') ? 'd-none' : ''; ?>">
                        Vous n'avez pas de compte? <a href="register.php">Inscrivez-vous</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets de connexion
    const tabs = document.querySelectorAll('#loginTabs .nav-link');
    const userTypeInput = document.getElementById('userType');
    const studentLabel = document.querySelector('.student-label');       
    const adminLabel = document.querySelector('.admin-label');          
    const responsableLabel = document.querySelector('.responsable-label'); 
    const studentText = document.querySelector('.student-text');       

    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();

            // Mettre à jour l'onglet actif
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Mettre à jour le type d'utilisateur
            const type = this.getAttribute('data-type');
            userTypeInput.value = type;

            // Afficher/masquer les labels selon le type
            if (type === 'admin') {
                adminLabel.classList.remove('d-none');
                responsableLabel.classList.add('d-none');
                studentLabel.classList.add('d-none');

                // Cacher texte inscription
                studentText.classList.add('d-none');

            } else if (type === 'responsable') {
                adminLabel.classList.add('d-none');
                responsableLabel.classList.remove('d-none');
                studentLabel.classList.add('d-none');

                // Cacher texte inscription aussi pour responsable
                studentText.classList.add('d-none');

            } else {
                adminLabel.classList.add('d-none');
                responsableLabel.classList.add('d-none');
                studentLabel.classList.remove('d-none');

                // Afficher texte inscription
                studentText.classList.remove('d-none');
            }
        });
    });

    // Afficher/masquer le mot de passe
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Changer l'icône
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
});
</script>

<?php include 'includes/footer.php'; ?>