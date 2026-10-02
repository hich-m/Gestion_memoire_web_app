<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

date_default_timezone_set('Africa/Algiers');

// Add this at the top of the file to get the base URL
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$appPath = "/gestion_memoire_web_app/";
$fullBaseUrl = $baseUrl . $appPath;

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['professor']);

// Récupération de l'ID de professor
$professorAdmin = $_SESSION['user_id'];

// Récupération des informations de admin_bibliotheque
try {
    $stmt = $pdo->prepare("
        SELECT p.id AS teacher_id, u.first_name, u.last_name, a.email, p.grade, d.title AS department_name 
        FROM professor p 
        JOIN user u ON p.user_id = u.id 
        JOIN account a ON u.id = a.id_user 
        LEFT JOIN departement d ON p.department_id = d.departement_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$professorAdmin]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception("No admin found");
    }

    $nomComplet = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
    $email = htmlspecialchars($admin['email']);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = "Error retrieving information";
    header('Location: login.php');
    exit();
}


//  Récupérer l'ID du professeur à partir du user_id
$stmt = $pdo->prepare("SELECT id FROM professor WHERE user_id = ?");
$stmt->execute([$professorAdmin]);
$professor = $stmt->fetch();

$professorId = $professor['id'];

// REQUETES POUR RECUPERER DES INFORMATIONS DES MEMOIRES SOUMIS 
$stmt = $pdo->prepare("SELECT 
    th.id AS id_these,
    g.code AS code_groupe,
    th.submitted_at AS date_soumission,
    p.title AS titre_projet,
    th.original_filename AS nom_fichier_original,
    th.file_path AS fichier_memoire,
    pv.file_path_pv AS fichier_pv,
    th.status AS status,
    r.role AS role,
    GROUP_CONCAT(CONCAT(u_etud.first_name, ' ', u_etud.last_name) SEPARATOR ', ') AS etudiants
FROM project p
JOIN role r ON r.id_project = p.id
JOIN professor prof ON r.professor_id = prof.id
JOIN groupe g ON g.id_project = p.id
JOIN student s ON s.id_groupe = g.id
JOIN user u_etud ON s.user_id = u_etud.id
JOIN pv_thesis pv ON pv.id_project = p.id
JOIN thesis th ON th.id_pv = pv.id
JOIN (
    SELECT g2.id AS groupe_id, MAX(th2.submitted_at) AS last_submission
    FROM thesis th2
    JOIN pv_thesis pv2 ON th2.id_pv = pv2.id
    JOIN project p2 ON pv2.id_project = p2.id
    JOIN groupe g2 ON g2.id_project = p2.id
    GROUP BY g2.id
) AS latest_thesis ON g.id = latest_thesis.groupe_id AND th.submitted_at = latest_thesis.last_submission
WHERE prof.id = ?
AND r.role = 'examiner'
AND th.is_deleted = 0
GROUP BY th.id, g.code, th.submitted_at, p.title, th.file_path, pv.file_path_pv, th.status
ORDER BY th.submitted_at DESC, p.title, g.code;");
$stmt->execute([$professorId]);

$thesis = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professeur - Président du jury</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        .submenu {
            display: none;
            flex-direction: column;
            padding-left: 20px;
            background-color: #2f3e50;
        }

        .submenu-item {
            font-size: 14px;
            color: #ecf0f1;
            padding: 8px 15px;
            text-decoration: none;
        }

        .submenu-item:hover {
            background-color: #3c4a5a;
        }
        
        /* Styles for personal info in sidebar */
        .sidebar-personal-info {
            padding: 15px;
            margin-bottom: 20px;
            background-color: #2c3e50;
            border-radius: 5px;
            color: #ecf0f1;
        }
        
        .sidebar-personal-info h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #3498db;
            border-bottom: 1px solid #3498db;
            padding-bottom: 5px;
        }
        
        .info-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: bold;
            color: #bdc3c7;
            display: inline-block;
            margin-right: 5px;
        }
        
        .info-value {
            color: #ecf0f1;
            word-break: break-word;
        }
    </style>
</head>

<body>
    <!-- Modal Examiner -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="reviewForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reviewModalLabel">Évaluer le mémoire</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div id="reviewMessage" class="text-success" style="display: none;"></div>
                        <input type="hidden" name="id_these" id="review-id-these">
                        <div class="mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="">--Sélectionner--</option>
                                <option value="APPROVED">Approuvé</option>
                                <option value="MODIFICATIONREQUEST">Demande de modification</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Retour</label>
                            <textarea name="feedback" id="feedback" rows="4" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

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

            <!-- Informations personnelles dans la sidebar -->
            <div class="sidebar-personal-info">
                <h4>Informations personnelles</h4>
                <div class="info-item">
                    <span class="info-label">Nom:</span>
                    <span class="info-value"><?= $nomComplet ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= $email ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Grade:</span>
                    <span class="info-value"><?= $admin['grade'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Département:</span>
                    <span class="info-value"><?= $admin['department_name'] ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="#" class="toggle-submenu nav-item <?= $currentPage == 'student_details.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Groupes</span>
                    <i class="fas fa-caret-down submenu-icon" style="margin-left:auto;"></i>
                </a>
                <div class="submenu">
                    <a href="encadrant.php" class="nav-item submenu-item<?= $currentPage == 'encadrant.php' ? ' active' : '' ?>">Encadrant</a>
                    <a href="rapporteur.php" class="nav-item submenu-item<?= $currentPage == 'rapporteur.php' ? ' active' : '' ?>">Rapporteur</a>
                    <a href="examinateur.php" class="nav-item submenu-item<?= $currentPage == 'examinateur.php' ? ' active' : '' ?>">Président du jury</a>
                </div>
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <div class="main-content">
            <!-- Carte des soumissions -->
            <div class="card">
                <div class="card-header">
                    <h2>Mémoires Déposés</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($thesis)): ?>
                        <p>Aucune soumission en attente.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Groupe</th>
                                    <th>Étudiants</th>
                                    <th>Projet</th>
                                    <th>Soumis le</th>
                                    <th>Statut</th>
                                    <th>Votre Rôle</th>
                                    <th>Mémoire</th>
                                    <th>PV</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($thesis as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['code_groupe'] ?? 'Individuel') ?></td>
                                        <td><?= htmlspecialchars($s['etudiants']) ?></td>
                                        <td><?= htmlspecialchars($s['titre_projet']) ?></td>
                                        <td><?= formatDate($s['date_soumission']) ?></td>
                                        <td><?= htmlspecialchars($s['status']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($s['role'])) ?></td>
                                        <!-- Lien vers le mémoire -->
                                        <td>
                                            <?php if (!empty($s['fichier_memoire'])): ?>
                                                <?php
                                                // Get the base URL
                                                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                                                $appPath = "/gestion_memoire_web_app/";
                                                
                                                // Get the file path from the database
                                                $filePath = $s['fichier_memoire'];
                                                
                                                // Create the full URL
                                                $fileUrl = $baseUrl . $appPath . $filePath;
                                                ?>
                                                <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-primary btn-sm">Voir Mémoire</a>
                                            <?php else: ?>
                                                <span class="text-muted">Non disponible</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Lien vers le PV -->
                                        <td>
                                            <?php if (!empty($s['fichier_pv'])): ?>
                                                <?php
                                                // Make sure the path is correct
                                                $pvPath = $s['fichier_pv'];
                                                // Remove any leading slashes or "uploads/" if it's duplicated
                                                $pvPath = ltrim($pvPath, '/');
                                                // If the path doesn't include "uploads/" at the beginning, add it
                                                if (strpos($pvPath, 'uploads/') !== 0) {
                                                    $pvPath = 'uploads/' . $pvPath;
                                                }
                                                ?>
                                                <a href="<?= $fullBaseUrl . htmlspecialchars($pvPath) ?>" target="_blank" class="btn btn-info btn-sm">
                                                    PV
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Non disponible</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($s['status'] === 'approved'): ?>
                                                <span title="Mémoire déjà approuvé" data-bs-toggle="tooltip">
                                                    <button class="btn btn-sm btn-secondary" disabled>
                                                        Examiner
                                                    </button>
                                                </span>
                                            <?php else: ?>
                                                <button
                                                    class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#reviewModal"
                                                    data-id="<?= $s['id_these'] ?>">
                                                    Examiner
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id_these'];
        $status = $_POST['status'];
        $feedback = $_POST['feedback'];

        $stmt = $pdo->prepare("UPDATE thesis SET status = ?, feedback = ? WHERE id = ?");
        $stmt->execute([$status, $feedback, $id]);

        header('Location: examinateur.php'); // ou autre page
        exit;
    }
    ?>
    </div>

    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <script>
        // GÉRER LE SOUS-MENU
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.querySelector('.toggle-submenu');
            const submenu = document.querySelector('.submenu');
            // Afficher automatiquement si un lien est actif
            if (submenu.querySelector('.active')) {
                submenu.style.display = 'flex';
            }
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                submenu.style.display = submenu.style.display === 'flex' ? 'none' : 'flex';
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            const reviewModal = document.getElementById('reviewModal');
            const reviewForm = document.getElementById('reviewForm');
            const reviewMessage = document.getElementById('reviewMessage');
            const idTheseInput = document.getElementById('review-id-these');
            
            // Set thesis ID when modal is opened
            reviewModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const thesisId = button.getAttribute('data-id');
                idTheseInput.value = thesisId;
            });
            
            // Handle form submission
            reviewForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(reviewForm);
                
                // Show loading state
                const submitBtn = reviewForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi...';
                submitBtn.disabled = true;
                
                fetch('save_review.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau');
                    }
                    return response.text();
                })
                .then(result => {
                    reviewMessage.style.display = 'block';
                    reviewMessage.classList.remove('text-danger');
                    reviewMessage.classList.add('text-success');
                    reviewMessage.textContent = 'Évaluation enregistrée avec succès ! Un email a été envoyé à l\'étudiant.';
                    
                    // Reset form
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(reviewModal);
                        modal.hide();
                        window.location.reload();
                    }, 1500);
                })
                .catch(error => {
                    reviewMessage.style.display = 'block';
                    reviewMessage.classList.remove('text-success');
                    reviewMessage.classList.add('text-danger');
                    reviewMessage.textContent = 'Une erreur est survenue: ' + error.message;
                    
                    // Reset button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        });
        // Activer les tooltips Bootstrap sur tous les éléments avec un "title"
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function(el) {
                return new bootstrap.Tooltip(el);
            });
        });
    </script>
</body>

</html>
