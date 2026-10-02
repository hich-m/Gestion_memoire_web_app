<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Vérification d'authentification et de rôle
checkAuth(['admin_library']);

// Récupération de l'ID de l'admin_bibliotheque
$admin_id = $_SESSION['user_id'];

// Récupération des informations de admin_bibliotheque
try {
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, a.email FROM user u JOIN account a ON u.id = a.id_user WHERE u.id = ?
    ");
    $stmt->execute([$admin_id]);
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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin Bibliothèque</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/adminstyle.css">
    <style>
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
    <!-- Modal Quitus -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Gestion du Quitus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="handle_quitus.php" id="quitusForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_etudiant" id="id_etudiant">
                        <div class="form-group">
                            <label for="actionChoice">Sélectionnez une action</label>
                            <select class="form-control" name="action_choice" id="actionChoice" required>
                                <option value="" disabled selected>--Sélectionner une option--</option>
                                <option value="generate_quitus">Valider le Quitus</option>
                                <option value="send_remark">Envoyer une remarque</option>
                            </select>
                        </div>
                        <div id="remarkDiv" style="display:none;">
                            <label for="remark">Remarque</label>
                            <textarea class="form-control" name="remark" id="remark" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Valider</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </form>
            </div>
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
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="quitus.php" class="nav-item <?= $currentPage == 'quitus.php' ? 'active' : '' ?>">
                    <i class="fas fa-archive"></i>
                    <span>Gestion des Quitus</span>
                </a>
                <a href="../logout.php" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>
        <!-- Zone de contenu principal -->
        <div class="main-content">
            <div class="content-content">
                <?php displayFlashMessage(); ?>
                <!-- Recherche et filtre -->
                <div class="card project-card">
                    <div class="card-header">
                        <h3>Rechercher un étudiant</h3>
                    </div>
                    <div class="card-body">
                        <input type="text" id="searchInput" placeholder="Recherche par matricule, nom ou prénom..." class="form-control">
                        <br>
                        <select id="docStatusFilter" class="form-select">
                            <option value="">Tous</option>
                            <option value="approved">Approuvé</option>
                            <option value="rejected">Rejeté</option>
                            <option value="pending">En attente</option>
                        </select>
                        <br>
                        <button id="resetFilters" class="btn btn-outline-secondary w-100">Réinitialiser</button>
                    </div>
                </div>
                <!-- Tableau des étudiants -->
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Groupe</th>
                            <th>Statut du mémoire</th>
                            <th>Statut du Quitus</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pagination settings
                        $resultsPerPage = 10;
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $startLimit = ($page - 1) * $resultsPerPage;

                        // Get students with their document and quitus status
                        $sql = "SELECT  s.id AS id_student, s.matricule, u.first_name AS prenom, 
                                                         u.last_name AS nom,
                                                         g.code AS groupe,
                                                         th.status AS doc_status,
                                                         s.quitus_status AS quitus_status
                                                     FROM student s
                                                     JOIN user u ON s.user_id = u.id
                                                     JOIN groupe g ON g.id = s.id_groupe 
                                                     JOIN project p ON g.id_project = p.id
                                                    JOIN pv_thesis pv ON pv.id_project = p.id
                                                    JOIN thesis th ON th.id_pv = pv.id
                                                     LIMIT :startLimit, :resultsPerPage";

                        $stmt = $pdo->prepare($sql);
                        $stmt->bindValue(':startLimit', $startLimit, PDO::PARAM_INT);
                        $stmt->bindValue(':resultsPerPage', $resultsPerPage, PDO::PARAM_INT);
                        $stmt->execute();

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $canGenerate = ($row['doc_status'] === 'approved');
                            $quitusStatus = $row['quitus_status'] ?? 'en_attente';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['matricule']) ?></td>
                                <td><?= htmlspecialchars($row['nom']) ?></td>
                                <td><?= htmlspecialchars($row['prenom']) ?></td>
                                <td><?= htmlspecialchars($row['groupe'] ?? 'N/A') ?></td>
                                <td data-docstatus="<?= $row['doc_status'] ?? 'non_soumis' ?>">
                                    <?php if ($row['doc_status']): ?>
                                        <span class="status-badge status-<?= $row['doc_status'] ?>">
                                            <?= ucfirst($row['doc_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non soumis</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $quitusStatus ?>">
                                        <?= str_replace('_', ' ', ucfirst($quitusStatus)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($canGenerate && $quitusStatus !== 'ready'): ?>
                                        <button type="button" class="btn btn-sm btn-primary openQuitusModal"
                                            data-id="<?= htmlspecialchars($row['id_student']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#exampleModal">
                                            Gérer
                                        </button>
                                    <?php elseif ($quitusStatus === 'ready'): ?>
                                        <span class="text-success" title="Le quitus est prêt. Aucune action supplémentaire.">
                                            Quitus prêt
                                        </span>
                                    <?php else: ?>
                                        <span class="disabled-button">Non disponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <!-- Pagination -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        // Get total count
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM student");
                        $countStmt->execute();
                        $totalResults = $countStmt->fetchColumn(); // méthode PDO correcte
                        $totalPages = ceil($totalResults / $resultsPerPage);

                        // Previous button
                        if ($page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . '">Précédent</a></li>';
                        }

                        // Page numbers
                        for ($i = 1; $i <= $totalPages; $i++) {
                            $active = $i === $page ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                        }

                        // Next button
                        if ($page < $totalPages) {
                            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . '">Suivant</a></li>';
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const exampleModal = document.getElementById('exampleModal');
            const actionChoice = document.getElementById("actionChoice");
            const remarkDiv = document.getElementById("remarkDiv");
            const idInput = document.getElementById('id_etudiant');
            exampleModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const studentId = button.getAttribute('data-id');
                idInput.value = studentId;
                actionChoice.value = "";
                remarkDiv.style.display = "none";
            });
            actionChoice.addEventListener("change", function() {
                remarkDiv.style.display = (this.value === "send_remark") ? "block" : "none";
            });
        });
        // Recherche
        $('#searchInput').keyup(function() {
            const searchText = $(this).val().toLowerCase();
            $('#studentTable tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.includes(searchText));
            });
        });
        // Filtre par statut
        document.getElementById('docStatusFilter').addEventListener('change', function() {
            const selectedStatus = this.value.toLowerCase();
            document.querySelectorAll('#studentTable tbody tr').forEach(row => {
                const rowStatus = row.querySelector('td[data-docstatus]').getAttribute('data-docstatus').toLowerCase();
                if (!selectedStatus || rowStatus === selectedStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        // Réinitialiser les filtres
        $('#resetFilters').click(function() {
            $('#searchInput').val('');
            $('#statusFilter').val('');
            $('#studentTable tbody tr').show();
        })
    </script>
</body>

</html>
