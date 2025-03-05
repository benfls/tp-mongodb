<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;

$manager = getMongoDbManager();

if (isset($_GET['id']) && preg_match('/^[a-f0-9]{24}$/i', $_GET['id'])) {
    try {
        $id = new ObjectId($_GET['id']);
        $result = $manager->selectCollection('tp')->deleteOne(['_id' => $id]);
        #$result = $manager->selectCollection('tp')->deleteMany(['titre' => null]);
        if ($result->getDeletedCount() > 0) {
            echo "Document supprimé avec succès.";
        } else {
            echo "Aucun document trouvé avec cet ID.";
        }
    } catch (Exception $e) {
        echo "Erreur lors de la suppression : " . $e->getMessage();
        exit;
    }
} else {
    echo "ID non valide ou absent.";
    exit;
}

// Redirection vers la liste après 2 secondes
header("Refresh:2; url=list.php");
exit;
