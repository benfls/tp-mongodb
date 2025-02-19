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

// Récupérer le terme de recherche
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// 🔥 Générer une clé de cache unique qui inclut la recherche
$searchKey = empty($searchQuery) ? "all" : md5($searchQuery); // Clé de recherche unique
$pageCacheKey = "page_cache_{$searchKey}_{$page}"; // Clé pour la page et la recherche
$cacheListKey = "cached_pages"; // Liste FIFO des pages en cache

// 📌 Vérifier si la page est en cache Redis
if ($redis && $redis->exists($pageCacheKey)) {
    $cachedData = json_decode($redis->get($pageCacheKey), true);

    //echo "Données chargées depuis le cache Redis.";

    echo $twig->render('index.html.twig', [
        'list' => $cachedData['list'],
        'page' => $page,
        'totalPages' => $cachedData['totalPages'],
        'searchQuery' => $searchQuery
    ]);
    exit;
}

// 🔍 Construire le filtre de recherche
$filter = [];
if (!empty($searchQuery)) {
    $filter = [
        '$or' => [
            ['titre' => ['$regex' => $searchQuery, '$options' => 'i']],
            ['auteur' => ['$regex' => $searchQuery, '$options' => 'i']],
            ['_id' => ['$regex' => $searchQuery, '$options' => 'i']]
        ]
    ];
}

// 🔄 Récupérer les documents avec filtre et pagination
$cursor = $collection->find($filter, [
    'limit' => $limit,
    'skip'  => ($page - 1) * $limit
]);

$list = iterator_to_array($cursor);

// ✅ Convertir `_id` en string pour éviter les erreurs avec Twig
foreach ($list as &$document) {
    if (isset($document['_id']) && is_object($document['_id'])) {
        $document['_id'] = (string) $document['_id'];
    }
}
unset($document); // Éviter les bugs de référence

// 🔢 Calculer le nombre total de pages
$totalDocuments = $collection->countDocuments($filter);
$totalPages = ceil($totalDocuments / $limit);

// 🔥 Stocker la page dans Redis (10 minutes)
if ($redis) {
    $cacheData = [
        'list' => $list,
        'page' => $page,
        'totalPages' => $totalPages
    ];

    $redis->setex($pageCacheKey, 600, json_encode($cacheData)); // Stocke la page avec la recherche

    // Ajouter la page actuelle à la liste FIFO
    $redis->lpush($cacheListKey, $pageCacheKey);

    // Si plus de 3 pages sont stockées, supprimer l'ancienne
    if ($redis->llen($cacheListKey) > 3) {
        $oldestPage = $redis->rpop($cacheListKey);
        if ($oldestPage) {
            $redis->del($oldestPage);
        }
    }
}

// 🎨 Affichage avec Twig
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
