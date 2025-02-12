<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');
$redis = getRedisClient();

// Définir le nombre de livres par page
$limit = 15;

// Récupérer la page actuelle (par défaut : 1)
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;

// Calculer combien d'éléments à sauter
$skip = ($page - 1) * $limit;

// Récupérer le terme de recherche (s'il existe)
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Clé Redis unique pour stocker la dernière recherche
$cacheKey = "last_search";

// Vérifier si la recherche est en cache
if (!empty($searchQuery) && $redis && $redis->exists($cacheKey)) {
    $cachedData = json_decode($redis->get($cacheKey), true);

    // Vérifier si la recherche actuelle correspond à celle en cache
    if ($cachedData['searchQuery'] === $searchQuery && $cachedData['page'] === $page) {
        echo "Données chargées depuis le cache Redis.";

        echo $twig->render('index.html.twig', [
            'list' => $cachedData['list'],
            'page' => $page,
            'totalPages' => $cachedData['totalPages'],
            'searchQuery' => $searchQuery
        ]);
        exit;
    }
}

// Construire le filtre de recherche
$filter = [];
if (!empty($searchQuery)) {
    $filter = [
        '$or' => [
            ['titre' => ['$regex' => $searchQuery, '$options' => 'i']], // Recherche insensible à la casse
            ['auteur' => ['$regex' => $searchQuery, '$options' => 'i']],
            ['_id' => ['$regex' => $searchQuery, '$options' => 'i']]
        ]
    ];
}

// Récupérer les documents avec filtre et pagination
$cursor = $collection->find($filter, [
    'limit' => $limit,
    'skip'  => $skip
]);

// Convertir en tableau
$list = iterator_to_array($cursor);

// 🔥 Correction : Convertir `_id` en string pour éviter un tableau de taille 1
foreach ($list as &$document) {
    if (isset($document['_id']) && is_object($document['_id'])) {
        $document['_id'] = (string) $document['_id']; // Convertir en string
    }
}

unset($document); // Éviter des bugs de référence

// Récupérer le nombre total de documents correspondant à la recherche
$totalDocuments = $collection->countDocuments($filter);
$totalPages = ceil($totalDocuments / $limit);

// Stocker en cache Redis (10 minutes)
if ($redis && !empty($searchQuery)) {
    $cacheData = [
        'searchQuery' => $searchQuery,
        'page' => $page,
        'list' => $list,
        'totalPages' => $totalPages
    ];
    $redis->setex($cacheKey, 600, json_encode($cacheData));
}

// Affichage avec Twig
try {
    echo $twig->render('index.html.twig', [
        'list' => $list,
        'page' => $page,
        'totalPages' => $totalPages,
        'searchQuery' => $searchQuery
    ]);
} catch (LoaderError | RuntimeError | SyntaxError $e) {
    echo "Erreur Twig : " . $e->getMessage();
}
?>
