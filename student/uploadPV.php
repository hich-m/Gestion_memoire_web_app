<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session
initSession();

// Vérification d'authentification et de rôle
checkAuth(['student']);

// ID étudiant connecté
$student_id = $_SESSION['user_id'];

// Vérifier s’il a déjà un PV
$stmt = $pdo->prepare("
    SELECT pv.id, pv.file_path_pv 
    FROM pv_thesis pv
    JOIN project p ON pv.id_project = p.id
    JOIN groupe g ON p.id = g.id_project
    JOIN student s ON g.id = s.id_groupe
    WHERE s.user_id = ?
");
$stmt->execute([$student_id]);
$thesis = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étudiant - Dépôt PV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        /* Table styling */
        .file-upload-table {
            width: 100%;
            margin: 20px 0;
        }

        .file-upload-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .file-upload-table td {
            padding: 20px;
            text-align: center;
            border: 2px dashed #ccc;
            border-radius: 5px;
        }

        /* Drop zone styling */
        .drop-zone {
            cursor: pointer;
            transition: all 0.3s;
        }

        .drop-zone:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .drop-zone i {
            font-size: 2em;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .drop-zone p {
            margin: 5px 0;
            color: #6c757d;
        }

        /* File input styling */
        #fileInput {
            display: none;
        }

        .browse-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            margin-top: 10px;
        }

        /* File preview styling */
        .file-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .file-preview i {
            font-size: 1.5em;
            color: #d93025;
        }

        .file-info {
            flex-grow: 1;
            text-align: left;
        }

        .delete-btn {
            background-color: transparent;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }

        .delete-btn:hover {
            text-decoration: underline;
        }

        /* Upload button styling */
        .btn-upload {
            background-color: #007bff;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .btn-upload:hover {
            background-color: #0069d9;
        }

        /* Highlight when dragging */
        .highlight {
            border-color: #007bff;
            background-color: #e7f1ff;
        }

        .alert {
            padding: 12px 20px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }

        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
    </style>

</head>

<body>
    <div class="app-container">
        <!-- Navigation latérale -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/img/logo_without backg.png" alt="Logo" width="70" height="50">
                <h3 class="app-name">Gestion de Projets Universitaires</h3>
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['user']['last_name'] ?? '') ?></span>
                        <?php if (($_SESSION['user']['role'] ?? '') === 'student' && isset($_SESSION['user']['details']['matricule'])): ?>
                            <span class="user-matricule"><?= htmlspecialchars($_SESSION['user']['details']['matricule']) ?></span>
                        <?php endif; ?>
                        <span class="user-role"><?= ucfirst($_SESSION['user']['role'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="uploadPV.php" class="nav-item <?= $currentPage == 'uploadPV.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer la fiche d'évaluation</span>
                </a>
                <a href="upload.php" class="nav-item <?= $currentPage == 'upload.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer le mémoire</span>
                </a>
                <a href="status.php" class="nav-item <?= $currentPage == 'status.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Statut des soumissions</span>
                </a>
                <a href="../logout.php" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>
        <!-- Zone de contenu principal -->
        <div class="main-content">
            <div class="content-content">
                <div class="header">
                    <h1>Déposer la fiche d'évaluation (PV)</h1>
                    <?php if (isset($_SESSION['upload_success']) && $_SESSION['upload_success']): ?>
                        <div style="color: green; margin: 10px 0;"> <?= $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?> </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['upload_error']) && $_SESSION['upload_error']): ?>
                        <div style="color: red; margin: 10px 0;"> <?= $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?> </div>
                    <?php endif; ?>
                </div>
                <div class="upload-container">
                    <?php if ($thesis && !empty($thesis['file_path_pv'])): ?>
                        <div class="upload-card">
                            <p class="alert alert-success">
                                Vous avez déjà déposé votre fiche d'évaluation.<br>
                                <strong>Nom du fichier :</strong> <?= htmlspecialchars(basename($thesis['file_path_pv'])) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="upload-card">
                            <form action="upload_pv.php" method="POST" enctype="multipart/form-data">
                                <div class="file-upload-table">
                                    <table>
                                        <tr>
                                            <td class="drop-zone" id="dropZone">
                                                <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                                <p>Glissez-déposez votre fichier PDF ici</p>
                                                <p>ou</p>
                                                <input type="file" name="pv_file" id="fileInput" accept=".pdf" required hidden>
                                                <label for="fileInput" class="browse-btn">Choisir un fichier</label>
                                            </td>
                                        </tr>
                                        <tr id="fileRow" style="display:none;">
                                            <td>
                                                <div class="file-preview">
                                                    <i class="fas fa-file-pdf"></i>
                                                    <div class="file-info">
                                                        <span id="fileNameDisplay"></span>
                                                        <button type="button" id="clearSelection" class="delete-btn">
                                                            <i class="fas fa-times"></i> Supprimer
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <button type="submit" class="btn-upload">Déposer la fiche d'évaluation</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
    <script>
        const fileInput = document.getElementById('fileInput');
        const fileRow = document.getElementById('fileRow');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const clearSelection = document.getElementById('clearSelection');
        const dropZone = document.getElementById('dropZone');

        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                fileNameDisplay.textContent = fileInput.files[0].name;
                fileRow.style.display = 'flex';
            }
        });

        clearSelection.addEventListener('click', function() {
            fileInput.value = '';
            fileRow.style.display = 'none';
        });

        // Drag and drop support
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.style.borderColor = '#1d4ed8';
        });

        dropZone.addEventListener('dragleave', function() {
            dropZone.style.borderColor = '#4a90e2';
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            fileInput.files = e.dataTransfer.files;
            fileNameDisplay.textContent = fileInput.files[0].name;
            fileRow.style.display = 'flex';
        });
    </script>

</body>

</html>
