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

    $manager->selectCollection('tp')->insertOne($user);
    // Redirection vers la liste après 2 secondes
    echo "Document ajouter avec succès.";

    header("Refresh:2; url=list.php");
    exit;
    // @todo coder l'enregistrement d'un nouveau livre en lisant le contenu de $_POST
} else {
// render template
    try {
        echo $twig->render('create.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}

