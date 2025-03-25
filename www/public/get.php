<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();

// ====== Configuration : Activer ou désactiver le cache ======
$useCache = true; // <<< METS FALSE ICI POUR DÉSACTIVER LE CACHE

// Vérifier si Redis est activé
if ($useCache && !$redis) {
    echo "Redis est désactivé ou inaccessible.";
    exit;
}

// Récupération de l'ID du livre
$id = new ObjectId($_GET['id']);
$entity = null;

// === 1️⃣ : Vérification cache des livres récents ===
if ($useCache) {
    $recentBooksKey = "recent_books";
    $recentBooks = json_decode($redis->get($recentBooksKey), true) ?? [];

    // Vérifier si le livre est dans les livres récents
    foreach ($recentBooks as $book) {
        if ($book['_id'] === (string)$id) {
            $entity = $book;
            break;
        }
    }
}

// === 2️⃣ : Si pas en cache, on récupère depuis MongoDB ===
if (!$entity) {
    $entity = (array)$manager->selectCollection('tp')->findOne(['_id' => $id]);
    if (!$entity) {
        echo "Livre non trouvé.";
        exit;
    }

    $entity['_id'] = (string) $entity['_id'];
}

// === 3️⃣ : Gestion des derniers livres consultés ===
if ($useCache) {
    $maxRecent = 5;

    // Supprimer si déjà présent
    foreach ($recentBooks as $index => $book) {
        if ($book['_id'] === $entity['_id']) {
            unset($recentBooks[$index]);
            break;
        }
    }

    // Ajouter au début
    array_unshift($recentBooks, $entity);

    // Garder les 5 derniers
    $recentBooks = array_slice($recentBooks, 0, $maxRecent);

    // Sauvegarder en cache
    $redis->set($recentBooksKey, json_encode($recentBooks));
}

// === 4️⃣ : Render ===
try {
    echo $twig->render('get.html.twig', [
        'entity' => $entity,
        'recentBooks' => $recentBooks
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}
