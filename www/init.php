<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use MongoDB\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Predis\Client;

// env configuration
(Dotenv\Dotenv::createImmutable(__DIR__))->load();

function getTwig(): Environment
{
    // twig configuration
    return new Environment(new FilesystemLoader('../templates'));
}

function getMongoDbManager(): Database
{
    $client = new MongoDB\Client("mongodb://{$_ENV['MDB_USER']}:{$_ENV['MDB_PASS']}@{$_ENV['MDB_SRV']}:{$_ENV['MDB_PORT']}");
    return $client->selectDatabase($_ENV['MDB_DB']);
}

function getRedisClient()
{
    // Vérifier si Redis est activé
    if ($_ENV['REDIS_ENABLE'] !== 'true') {
        return null;
    }

    try {
        // Initialiser et retourner le client Redis
        $redis = new Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'],
            'port'   => $_ENV['REDIS_PORT']
        ]);

        return $redis;

    } catch (Exception $e) {
        error_log("Erreur de connexion à Redis : " . $e->getMessage());
        return null;
    }
}

function getElasticClient()
{
    // Vérifier si Redis est activé
    if ($_ENV['ELASTIC_ENABLE'] !== 'true') {
        return null;
    }

    try {

        // Initialiser et retourner le client Elasticsearch
        $client = ClientBuilder::create()
            ->setHosts(["{$_ENV['ELASTIC_HOST']}:{$_ENV['ELASTIC_PORT']}"])
            ->build();

        return $client;

    } catch (Exception $e) {
        error_log("Erreur de connexion à Elasticsearch : " . $e->getMessage());
        return null;
    }
}

// Rechercher les livres dans Elasticsearch

function search($searchAuteur, $searchTitre)
{
    $list = [];
    if ($_ENV['ELASTIC_ENABLE'] === 'true') {
        $client = getElasticClient();

        $params = [
            'index' => 'books',
            'body'  => [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'match' => [
                                    'titre' => [
                                        'query' => $searchTitre ?? "",
                                        'fuzziness' => 'AUTO',
                                    ]
                                ]
                            ],
                            [
                                'match' => [
                                    'auteur' => [
                                        'query' => $searchAuteur ?? "",
                                        'fuzziness' => 'AUTO',
                                    ]
                                ]
                            ],
                            [
                                'match' => [
                                    'bookdId' => [
                                        'query' => "",
                                        'fuzziness' => 'AUTO',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $client->search($params);
        $bookIds = [];
        foreach ($response['hits']['hits'] as $hit) {
            if (isset($hit['_id'])) {
                $bookIds[] = $hit['_id'];
            }
        }
        $list = $bookIds;
    } else {
        $manager = getMongoDbManager();
        $filtre = [];

        if ($searchTitre !== "") {
            $filtre['titre'] = ['$regex' => $searchTitre, '$options' => 'i'];
        }
        if ($searchAuteur !== "") {
            $filtre['auteur'] = ['$regex' => $searchAuteur, '$options' => 'i'];
        }

        $list = $manager->selectCollection('tp')->find($filtre)->toArray();
        $list = array_map(
            function ($document) {
                return [
                    ...$document,
                    '_id' => (string)$document['_id']
                ];
            },
            $list
        );
    }
    return $list;
}
