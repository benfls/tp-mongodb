<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();

// Vérifier si Redis est activé
if (!$redis) {
    echo "Redis est désactivé ou inaccessible.";
    exit;
}

// @todo implementez la récupération des données d'une entité et la passer au template
// petite aide : https://github.com/VSG24/mongodb-php-examples
$id = new ObjectId($_GET['id']);
$cacheKey = "book:$id"; // Clé Redis pour ce livre

// Vérifier si le livre est déjà en cache
if ($redis->exists($cacheKey)) {
    $entity = json_decode($redis->get($cacheKey), true);
    //echo "Données chargées depuis le cache Redis.<br>";
} else {
    $entity = (array)$manager->selectCollection('tp')->findOne(['_id' => $id]);
    // Vérifier si on a bien trouvé un document
    if (!$entity) {
        echo "Livre non trouvé.";
        exit;
    }

    // Convertir `_id` en string
    $entity['_id'] = (string) $entity['_id'];

    // Stocker le livre en cache (expire après 30 minutes)
    $redis->setex($cacheKey, 1800, json_encode($entity));
}

// ✅ Ajouter aux derniers livres consultés
$recentBooksKey = "recent_books";
$maxRecent = 5; // On garde en mémoire les 5 derniers livres

// Récupérer la liste actuelle des livres consultés
$recentBooks = json_decode($redis->get($recentBooksKey), true) ?? [];

// Supprimer l'ancien si déjà présent
$recentBooks = array_filter($recentBooks, fn($b) => $b['_id'] !== $entity['_id']);

// Ajouter le livre au début
array_unshift($recentBooks, $entity);

// Garder uniquement les $maxRecent derniers
$recentBooks = array_slice($recentBooks, 0, $maxRecent);

// Sauvegarder la liste mise à jour en cache
$redis->set($recentBooksKey, json_encode($recentBooks));

// render template
try {
    echo $twig->render('get.html.twig', ['entity' => $entity, 'recentBooks' => $recentBooks]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}