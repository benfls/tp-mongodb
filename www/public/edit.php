<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

// Vérification de l'ID dans l'URL
if (!isset($_GET['id']) || !preg_match('/^[a-f0-9]{24}$/i', $_GET['id'])) {
    echo "ID non valide ou absent.";
    exit;
}

$id = new ObjectId($_GET['id']);
$entity = $manager->selectCollection('tp')->findOne(['_id' => $id]);

if (!$entity) {
    echo "Aucun document trouvé avec cet ID.";
    exit;
}

// Affichage du formulaire avec Twig
try {
    echo $twig->render('edit.html.twig', ['entity' => (array)$entity]);
} catch (LoaderError | RuntimeError | SyntaxError $e) {
    echo "Erreur Twig : " . $e->getMessage();
}
