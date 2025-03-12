<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\ObjectId;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');
#$redis = getRedisClient();

// Définir le nombre de livres par page
$limit = 15;

// Récupérer la page actuelle (par défaut : 1)
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;

// Récupérer les termes de recherche
$searchTitre = isset($_GET['searchTitre']) ? trim($_GET['searchTitre']) : '';
$searchAuteur = isset($_GET['searchAuteur']) ? trim($_GET['searchAuteur']) : '';

//echo "Page actuelle : " . $page . "\n"; // Debug
//echo "Titre de recherche : " . $searchTitre . "\n"; // Debug
//echo "Auteur de recherche : " . $searchAuteur . "\n"; // Debug


// Appeler la fonction de recherche
$bookIds = search($searchAuteur, $searchTitre);

//echo "Liste des documents récupérés : ";
//print_r($bookIds); // Debug
$bookIds = array_map(fn($id) => new ObjectId($id), $bookIds);

// 🔍 Construire le filtre de recherche
$filter = [];
if (!empty($bookIds)) {
    $filter = ['_id' => ['$in' => $bookIds]];
}

//echo "Filtre génerer : ";
//print_r($filter);
// Debug
// 🔄 Récupérer les documents avec filtre et pagination
$cursor = $collection->find($filter);

$list = iterator_to_array($cursor);

//echo "Liste des documents récupérés : ";
//print_r($list); // Debug

// ✅ Convertir `_id` en string pour éviter les erreurs avec Twig
foreach ($list as &$document) {
    if (isset($document['_id']) && is_object($document['_id'])) {
        $document['_id'] = (string) $document['_id'];
    }
}
unset($document); // Éviter les bugs de référence

// 🔢 Calculer le nombre total de pages
$totalDocuments = count($list);
$totalPages = ceil($totalDocuments / $limit);

//echo "Nombre total de pages : " . $totalPages . "\n"; // Debug

// 🎨 Affichage avec Twig
try {
    echo $twig->render('index.html.twig', [
        'list' => array_slice($list, ($page - 1) * $limit, $limit),
        'page' => $page,
        'totalPages' => $totalPages,
        'searchTitre' => $searchTitre,
        'searchAuteur' => $searchAuteur
    ]);
} catch (LoaderError | RuntimeError | SyntaxError $e) {
    echo "Erreur Twig : " . $e->getMessage();
}

?>
