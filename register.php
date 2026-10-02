<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

initSession();

$error = '';
$success = '';

// Get departments
$departments = [];
try {
    $stmt = $pdo->query("SELECT departement_id, title FROM departement ORDER BY title");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load departments.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitizeInput($_POST['role']);
    $matricule = $_POST['matricule'] ?? null;
    $department_id = $_POST['department_id'] ?? null;
    $nin = $_POST['nin'] ?? null;
    $birth_date = $_POST['date_de_naissance'] ?? null;
    $birth_place = $_POST['lieu_de_naissance'] ?? null;
    $specialty = $_POST['specialite'] ?? null;
    $grade = $_POST['grade'] ?? null;

    // Validation
    if (!$first_name || !$last_name || !$email || !$password) {
        $error = 'All required fields must be filled.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'student' && empty($matricule)) {
        $error = 'Matricule is required for students.';
    } elseif (in_array($role, ['student', 'professor']) && empty($department_id)) {
        $error = 'Department is required.';
    } elseif ($role === 'student' && empty($nin)) {
        $error = 'NIN is required for students.';
    } elseif ($role === 'professor' && empty($grade)) {
        $error = 'Grade is required for professors.';
    } else {
        try {
            // Check email uniqueness
            $stmt = $pdo->prepare("SELECT id FROM account WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email is already in use.';
            }

            // Check matricule uniqueness for students
            if (empty($error) && $role === 'student') {
                $stmt = $pdo->prepare("SELECT id FROM student WHERE matricule = ?");
                $stmt->execute([$matricule]);
                if ($stmt->fetch()) {
                    $error = 'Matricule is already used.';
                }
            }

            if (empty($error)) {
                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users
                $stmt = $pdo->prepare("INSERT INTO user (first_name, last_name, role) VALUES (?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $role]);
                $user_id = $pdo->lastInsertId();

                // Insert into account
                $stmt = $pdo->prepare("INSERT INTO account (email, password, status, id_user) VALUES (?, ?, 'pending', ?)");
                $stmt->execute([$email, $hashed_password, $user_id]);

                // Role-specific inserts
                if ($role === 'student') {
                    $stmt = $pdo->prepare("INSERT INTO student (user_id, department_id, matricule, nin, date_de_naissance, lieu_de_naissance, specialite) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $department_id, $matricule, $nin, $birth_date, $birth_place, $specialty]);
                } elseif ($role === 'professor') {
                    $stmt = $pdo->prepare("INSERT INTO professor (user_id, department_id, grade) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $department_id, $grade]);
                }

                $pdo->commit();
                $success = "Inscription réussie ! Votre compte est en attente d'approbation par l'administrateur.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/logo_without backg.png" style="background: #2c3e50;" alt="Logo" width="90" height="70">
                Système de Gestion des Projets Universitaires
            </a>

            <div class="nav-links">
                <a href="index.php" class="nav-link">Page d'accueil</a>
            </div>

        </div>
    </div>

    <div class="login-container">
        <h2>Créer un nouveau compte</h2>
        <p>Créez un compte pour accéder au système de gestion de projets universitaires.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <p>Vous pourrez vous <a href="login.php">connecter</a> une fois votre compte approuvé.</p>
            </div>
        <?php else: ?>
            <form action="register.php" method="post">
                <div class="form-group">
                    <label for="first_name">Prénom:</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Nom:</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="role">Rôle:</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">Sélectionnez un rôle</option>
                        <option value="student">Étudiant(e)</option>
                        <option value="professor">Professeur</option>
                        <option value="admin">Administrateur</option>
                        <option value="admin_library">Responsable de la bibliothèque</option>
                    </select>
                </div>

                <!-- Student specific fields -->
                <div class="form-group role-specific student-specific">
                    <label for="matricule">Matricule:</label>
                    <input type="text" id="matricule" name="matricule" class="form-control">
                </div>

                <div class="form-group role-specific student-specific">
                    <label for="nin">NIN (Numéro d'identification national):</label>
                    <input type="text" id="nin" name="nin" class="form-control">
                </div>

                <div class="form-group role-specific student-specific">
                    <label for="date_de_naissance">Date de naissance:</label>
                    <input type="date" id="date_de_naissance" name="date_de_naissance" class="form-control">
                </div>

                <div class="form-group role-specific student-specific">
                    <label for="lieu_de_naissance">Lieu de naissance:</label>
                    <input type="text" id="lieu_de_naissance" name="lieu_de_naissance" class="form-control">
                </div>

                <div class="form-group role-specific student-specific">
                    <label for="specialite">Spécialité:</label>
                    <select id="specialite" name="specialite" class="form-control">
                        <option value="">Sélectionnez votre spécialité</option>
                        <option value="Ingenierie du Logiciel et Traitement de L'information">
                            Ingénierie du Logiciel et Traitement de l'Information
                        </option>
                        <option value="Systemes d'Information Repartis">
                            Systèmes d'Information Répartis
                        </option>
                        <option value="Technologie de l'Information">
                            Technologie de l'Information
                        </option>
                    </select>
                </div>

                <!-- Professor specific field -->
                <div class="form-group role-specific professor-specific">
                    <h3>Informations enseignant</h3>
                    <div class="form-group">
                        <label for="grade">Grade:</label>
                        <select id="grade" name="grade">
                            <option value="maitre_assistant" <?= isset($_POST['grade']) && $_POST['grade'] === 'maitre_assistant' ? 'selected' : '' ?>>Maître Assistant</option>
                            <option value="assistant" <?= isset($_POST['grade']) && $_POST['grade'] === 'assistant' ? 'selected' : '' ?>>Assistant</option>
                            <option value="professeur" <?= isset($_POST['grade']) && $_POST['grade'] === 'professeur' ? 'selected' : '' ?>>Professeur</option>
                            <option value="docteur" <?= isset($_POST['grade']) && $_POST['grade'] === 'docteur' ? 'selected' : '' ?>>Docteur</option>
                        </select>
                    </div>
                </div>


                <div class="form-group role-specific student-specific professor-specific">
                    <label for="department_id">Département:</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Sélectionnez un département</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['departement_id']; ?>"><?php echo $department['title']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">S'inscrire</button>
                </div>
            </form>
        <?php endif; ?>

        <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous ici</a></p>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');

            roleSelect.addEventListener('change', function() {
                const role = this.value;

                document.querySelectorAll('.role-specific').forEach(field => {
                    field.style.display = 'none';
                });

                if (role === 'student') {
                    document.querySelectorAll('.student-specific').forEach(field => {
                        field.style.display = 'block';
                    });
                } else if (role === 'professor') {
                    document.querySelectorAll('.professor-specific').forEach(field => {
                        field.style.display = 'block';
                    });
                } else if (role === 'admin_bibliotheque') {
                    // No specific fields for admin_bibliotheque
                }
            });

            if (roleSelect.value) {
                roleSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>