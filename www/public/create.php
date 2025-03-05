<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

// petite aide : https://github.com/VSG24/mongodb-php-examples

if (!empty($_POST)) {
    $user = ['titre' => $_POST['title'],
        'auteur' => $_POST['author'],
        'siecle' => $_POST['century'],
        'edition' => $_POST['edition'],
        'langue' => $_POST['language'],
        'cote' => $_POST['cote']];

    $result = $manager->selectCollection('tp')->insertOne($user);
    $insertedId = $result->getInsertedId();

    // Retourner l'ID du document inséré au format JSON
    header('Content-Type: application/json');
    echo $insertedId;
    exit;
} else {
// render template
    try {
        echo $twig->render('create.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}

