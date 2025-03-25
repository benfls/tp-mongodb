<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\ObjectId;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');
$redis = getRedisClient(); // Décommenté ici

// Définir le nombre de livres par page
$limit = 15;

// Récupérer la page actuelle (par défaut : 1)
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;

// Récupérer les termes de recherche
$searchTitre = isset($_GET['searchTitre']) ? trim($_GET['searchTitre']) : '';
$searchAuteur = isset($_GET['searchAuteur']) ? trim($_GET['searchAuteur']) : '';

// Si c'est la première page et aucune recherche, vérifier le cache
if ($page === 1 && $searchTitre === '' && $searchAuteur === '') {
    $cacheKey = 'homepage_cache';

    // Vérifier si cache existe
    $cachedPage = $redis->get($cacheKey);
    if ($cachedPage) {
        echo $cachedPage;
        exit; // Terminer ici pour ne pas exécuter inutilement le reste
    }
}

// Appeler la fonction de recherche
$bookIds = search($searchAuteur, $searchTitre);

$bookIds = array_map(fn($id) => new ObjectId($id), $bookIds);

// 🔍 Construire le filtre de recherche
$filter = [];
if (!empty($bookIds)) {
    $filter = ['_id' => ['$in' => $bookIds]];
}

// 🔄 Récupérer les documents avec filtre et pagination
$cursor = $collection->find($filter);

$list = iterator_to_array($cursor);

// ✅ Convertir `_id` en string pour éviter les erreurs avec Twig
foreach ($list as &$document) {
    if (isset($document['_id']) && is_object($document['_id'])) {
        $document['_id'] = (string) $document['_id'];
    }
}
unset($document);

// 🔢 Calculer le nombre total de pages
$totalDocuments = count($list);
$totalPages = ceil($totalDocuments / $limit);

// 🎨 Affichage avec Twig
try {
    $renderedPage = $twig->render('index.html.twig', [
        'list' => array_slice($list, ($page - 1) * $limit, $limit),
        'page' => $page,
        'totalPages' => $totalPages,
        'searchTitre' => $searchTitre,
        'searchAuteur' => $searchAuteur
    ]);

    echo $renderedPage;

    // Si c'est la première page sans recherche, mettre en cache
    if ($page === 1 && $searchTitre === '' && $searchAuteur === '') {
        $redis->setex($cacheKey, 300, $renderedPage); // Cache 5 minutes (300 sec), tu peux ajuster
    }

} catch (LoaderError | RuntimeError | SyntaxError $e) {
    echo "Erreur Twig : " . $e->getMessage();
}

?>
