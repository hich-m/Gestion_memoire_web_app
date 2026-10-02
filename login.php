<?php
require_once 'config/db_config.php';

// Gestion des messages et de l'email
$error = $_SESSION['error'] ?? '';
$email = $_SESSION['login_email'] ?? '';
unset($_SESSION['error'], $_SESSION['login_email']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion de Mémoires</title>
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
            <h2>Bienvenue</h2>
            <p>Veuillez vous connecter pour continuer</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="loginLogic.php" method="post">
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                </div>
            </form>

            <p>Vous n'avez pas de compte ? <a href="register.php">S'inscrire</a></p>
        </div>
        <?php require_once 'includes/footer.php'; ?>
</body>

</html>