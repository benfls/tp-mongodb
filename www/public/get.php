<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();

if ($redis) {
    $redis->set('message', 'Hello Redis!');
    echo $redis->get('message'); // Affichera "Hello Redis!"
} else {
    echo "Redis est désactivé ou inaccessible.";
}

// @todo implementez la récupération des données d'une entité et la passer au template
// petite aide : https://github.com/VSG24/mongodb-php-examples
$id = new ObjectId($_GET['id']);
$entity = (array) $manager->selectCollection('tp')->findOne(['_id' => $id]);
// render template
try {
    echo $twig->render('get.html.twig', ['entity' => $entity]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}