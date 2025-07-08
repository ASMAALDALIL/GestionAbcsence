<?php
// Démarrer la session
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Inclure les fichiers nécessaires
require_once 'config/db.php';
require_once 'includes/auth.php';

// Rediriger si l'utilisateur est déjà connecté
redirect_if_logged_in();

// Variables pour la gestion des formulaires
$error = '';
$success = '';
$nom = '';
$prenom = '';
$email = '';
$numero_apogee = '';
$filiere_id = '';
$modules = [];

// Récupérer les filières pour le formulaire
try {
    $stmt = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY nom");
    $filieres = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières: ' . $e->getMessage();
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité. Veuillez réessayer.';
    } else {
        
        // Récupérer les données du formulaire
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $numero_apogee = trim($_POST['numero_apogee']);
        $filiere_id = $_POST['filiere_id'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $modules = isset($_POST['modules']) ? $_POST['modules'] : [];
       
             $maxSize = 2 * 1024 * 1024;
     $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
     $uploadDir = "listeEtudiant/";
     
     if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
         $photo = $_FILES['photo'];
     
         if (!in_array($photo['type'], $allowedTypes)) {
             die("Type de fichier non autorisé.");//Vérifie si le type du fichier est bien dans la liste des types autorisés
         }
     
         if ($photo['size'] > $maxSize) {
             die("Fichier trop volumineux.");//affiche un message d erreur et le script s arrete 
         }
     
         $extension = pathinfo($photo['name'], PATHINFO_EXTENSION);//nom de fichier envoyer par utilisateur 
         $filename = uniqid("etudiant_") . '.' . $extension;// genere un mot unique en commencant par etudiant
         $destination = $uploadDir . $filename;
     
         if (!is_dir($uploadDir)) {
             mkdir($uploadDir, 0755, true);
         }
     
         if (move_uploaded_file($photo['tmp_name'], $destination)) {//tmp_name et le chemin temporaire de fichier 
             // Stockage dans un fichier texte
             $ligne = $nom . ' ' . $prenom . ':' .  $filiere_id . ':' . $destination . "\n";


             file_put_contents("etudiants.txt", $ligne, FILE_APPEND | LOCK_EX);
     
             echo "Inscription réussie. <a href='galerie.php'>Voir la galerie</a>";
         } else {
             echo "Erreur de transfert.";
         }
     } else {
         echo "Aucune photo reçue.";
        }

        // Valider les entrées
        if (empty($nom) || empty($prenom) || empty($email) || empty($numero_apogee) || 
            empty($filiere_id) || empty($password) || empty($confirm_password) || empty($modules)) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif ($password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            try {
                // Vérifier si le numéro Apogée existe déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE numero_apogee = :numero_apogee");
                $stmt->execute(['numero_apogee' => $numero_apogee]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Ce numéro Apogée est déjà utilisé.';
                } else {
                    // Vérifier si l'email existe déjà
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Cette adresse email est déjà utilisée.';
                    } else {
                        // Hacher le mot de passe
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $code_activation = bin2hex(random_bytes(4));

                        // Insérer l'étudiant
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("INSERT INTO etudiants (numero_apogee, nom, prenom, email, password, filiere_id,code_activation) 
                                              VALUES (:numero_apogee, :nom, :prenom, :email, :password, :filiere_id,:code_activation)");
                        $stmt->execute([
                            'numero_apogee' => $numero_apogee,
                            'nom' => $nom,
                            'prenom' => $prenom,
                            'email' => $email,
                            'password' => $hashed_password,
                            'filiere_id' => $filiere_id,
                            'code_activation' => $code_activation
                        ]);
                        
                        $etudiant_id = $pdo->lastInsertId();
                        
                        // Insérer les inscriptions aux modules
                        $stmt_module = $pdo->prepare("INSERT INTO inscriptions_modules (etudiant_id, module_id) VALUES (:etudiant_id, :module_id)");
                        
                        foreach ($modules as $module_id) {
                            $stmt_module->execute([
                                'etudiant_id' => $etudiant_id,
                                'module_id' => $module_id
                            ]);
                        }
                        
                        $pdo->commit();
                        
                        // Message de succès
                        $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'aldalilasma@gmail.com';
                            $mail->Password = 'evoj wcxe ugtl qdtj';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('aldalilasma@gmail.com', 'application etudiant');
                            $mail->addAddress($email,$nom);
                            $mail->Subject = 'activer votre compte';
                            $mail->Body ="
                                Bienvenue $nom 
                                Merci pour votre inscription. Voilà le code:
                                $code_activation
                            ";
                            $mail->send();
                            $success = 'Inscription réussie ! Code de vérifécation envoyer par email.';
                            header('location:activer_compte.php');
                            exit();
                        } 
                        catch (Exception $e) {
                                echo "Erreur d'envoi : {$mail->ErrorInfo}"; 
                        }
                        
                        // Redirection vers la page de connexion après 2 secondes
                        header("refresh:2;url=index.php");
                        
                        // Réinitialiser les champs du formulaire
                        $nom = '';
                        $prenom = '';
                        $email = '';
                        $numero_apogee = '';
                        $filiere_id = '';
                        $modules = [];
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de l\'inscription: ' . $e->getMessage();
            }
        }
    }
}

// Générer un nouveau token CSRF
$csrf_token = generate_csrf_token();

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4><i class="fas fa-user-plus me-2"></i> Inscription Étudiant</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape_html($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo escape_html($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="register.php" id="registerForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?php echo escape_html($nom); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo escape_html($prenom); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo escape_html($email); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="numero_apogee" class="form-label">Numéro Apogée</label>
                            <input type="text" class="form-control" id="numero_apogee" name="numero_apogee" value="<?php echo escape_html($numero_apogee); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="filiere_id" class="form-label">Filière</label>
                        <select class="form-select" id="filiere_id" name="filiere_id" required>
                            <option value="">Sélectionnez une filière</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo escape_html($filiere['id']); ?>" <?php echo ($filiere_id == $filiere['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape_html($filiere['code'] . ' - ' . $filiere['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Modules</label>
                        <div id="modulesList" class="border rounded p-3">
                            <div class="text-center">
                                <p>Veuillez d'abord sélectionner une filière</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Mot de passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                      <div class="mb-3">
        <label for="photo" class="form-label">Photo de profil</label>
        <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>S'inscrire
                        </button>
                    </div>
                  
                </form>
                
                <div class="text-center mt-4">
                    <p>Vous avez déjà un compte? <a href="index.php">Connectez-vous</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filiereSelect = document.getElementById('filiere_id');
    const modulesList = document.getElementById('modulesList');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    // Chargement des modules en fonction de la filière sélectionnée
    filiereSelect.addEventListener('change', function() {
        const filiereId = this.value;
        
        if (filiereId) {
            // Afficher un indicateur de chargement
            modulesList.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div><p class="mt-2">Chargement des modules...</p></div>';
            
            // Requête AJAX pour récupérer les modules
            fetch('get_modules.php?filiere_id=' + filiereId)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        let html = '<div class="row">';
                        data.forEach(module => {
                            html += `
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="modules[]" id="module${module.id}" value="${module.id}">
                                        <label class="form-check-label" for="module${module.id}">
                                            ${module.code} - ${module.nom} (${module.semestre})
                                        </label>
                                    </div>
                                </div>`;
                        });
                        html += '</div>';
                        modulesList.innerHTML = html;
                    } else {
                        modulesList.innerHTML = '<div class="alert alert-info">Aucun module trouvé pour cette filière.</div>';
                    }
                })
                .catch(error => {
                    modulesList.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des modules.</div>';
                    console.error('Erreur:', error);
                });
        } else {
            modulesList.innerHTML = '<div class="text-center"><p>Veuillez d\'abord sélectionner une filière</p></div>';
        }
    });
    
    // Afficher/masquer le mot de passe
    togglePasswordBtn.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Changer l'icône
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
    
    // Validation du formulaire
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        // Vérifier que les mots de passe correspondent
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas.');
            return false;
        }
        
        // Vérifier qu'au moins un module est sélectionné
        const selectedModules = document.querySelectorAll('input[name="modules[]"]:checked');
        if (selectedModules.length === 0) {
            e.preventDefault();
            alert('Veuillez sélectionner au moins un module.');
            return false;
        }
        
        return true;
    });
});
</script>

<?php include 'includes/footer.php'; ?>