<?php

include_once __DIR__.'/../init.php';

$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');

// Initialiser le client Elasticsearch
$client = getElasticClient();
$books = $collection->find()->toArray();

/// Indexer chaque livre dans Elasticsearch
foreach ($books as $book) {
    $id = (string)$book['_id']; // Utiliser l'ID MongoDB comme ID Elasticsearch
    unset($book['_id']); // Retirer le champ _id du corps du document

    $params = [
        'index' => 'books',
        'id'    => $id,
        'body'  => $book
    ];

    // Indexer le document dans Elasticsearch
    $response = $client->index($params);
    echo "Indexed book with ID: " . $response['_id'] . "\n";
}

echo "Indexation terminée.\n";
return 1;
?>

