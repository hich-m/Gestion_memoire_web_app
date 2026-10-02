<?php
require_once '../config/db_config.php';

$id = $_GET['id'];  // Récupère l'ID de l'utilisateur

try {
    // Préparer la requête pour récupérer les informations de l'utilisateur
    $sql = "SELECT * FROM user WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Vérifier si l'utilisateur existe
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($user);  // Retourne les informations de l'utilisateur sous forme de JSON
    } else {
        echo json_encode(['error' => 'Utilisateur non trouvé']);  // Si l'utilisateur n'existe pas
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des données : ' . $e->getMessage()]);
}
