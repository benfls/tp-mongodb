<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;

$dm = getMongoDbManager(); // Récupération du gestionnaire MongoDB

// Vérifier si la requête est en POST et si l'ID est valide
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && preg_match('/^[a-f0-9]{24}$/i', $_POST['id'])) {
    try {
        $id = new ObjectId($_POST['id']);

        // Préparer les nouvelles données à mettre à jour
        $updateData = [
            'titre' => $_POST['title'],
            'auteur' => $_POST['author'],
            'siecle' => $_POST['century'],
            'edition' => $_POST['edition'],
            'langue' => $_POST['language'],
            'cote' => $_POST['cote']
        ];

        // Exécuter la mise à jour
        $result = $dm->selectCollection('tp')->updateOne(
            ['_id' => $id], // Condition pour trouver le bon document
            ['$set' => $updateData] // Mise à jour des champs
        );

        if ($result->getModifiedCount() > 0) {
            echo "Document mis à jour avec succès.";
        } else {
            echo "Aucune modification apportée.";
        }
    } catch (Exception $e) {
        echo "Erreur lors de la mise à jour : " . $e->getMessage();
        exit;
    }
} else {
    echo "Données invalides.";
    exit;
}

// Redirection vers la liste après modification
header("Location: list.php");
exit;
