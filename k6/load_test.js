import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '5s', target: 5 },  // Montée en charge jusqu'à 5 utilisateurs
        { duration: '10s', target: 10 }, // 10 utilisateurs actifs
        { duration: '10s', target: 50 }, // Pic de charge avec 50 utilisateurs
        { duration: '5s', target: 10 }, // Descente progressive
        { duration: '5s', target: 5 }   // Retour à la normale
    ]
};

export default function () {
    let baseUrl = "http://tpmongo-php:80";

    // 🔹 1. Affichage de la liste des livres (Page 1)
    let listResponse = http.get(`${baseUrl}/index.php`, { headers: { Accepts: "application/json" } });
    check(listResponse, { "Liste des livres chargée": (r) => r.status === 200 });

    // 🔹 2. Affichage de la page 30
    let page30Response = http.get(`${baseUrl}/index.php?page=4`, { headers: { Accepts: "application/json" } });
    check(page30Response, { "Page 30 affichée": (r) => r.status === 200 });

    // 🔹 3. Consultation des détails d’un livre (On prend un ID aléatoire)
    let bookIdMatch = listResponse.body.match(/get\.php\?id=([a-f0-9]+)/i);
    if (bookIdMatch && bookIdMatch[1]) {
        let bookId = bookIdMatch[1]; // 🆔 ID du livre récupéré
        // Charger les détails du livre avec cet ID
        let detailsResponse = http.get(`${baseUrl}/get.php?id=${bookId}`);
        check(detailsResponse, { "Détails du livre chargés": (r) => r.status === 200 });
    }

    // 🔹 4. Retour à la liste
    let returnToListResponse = http.get(`${baseUrl}/index.php`);
    check(returnToListResponse, { "Retour à la liste réussi": (r) => r.status === 200 });

    // 🔹 5. Ajout d’un livre (POST request)
    let newBook = {
        title: "K6 Test Book",
        author: "Test Author",
        edition: 'La montagne',
        language:'français',
        cote: "ufzhuifg",
        century: 2023
    };
    let addBookResponse = http.post(`${baseUrl}/create.php`, newBook);
    check(addBookResponse, { "Livre ajouté avec succès": (r) => r.status === 200 });

    let addedBookId = addBookResponse.body;
    console.log('id: ', addedBookId);

    // 🔹 6. Consultation du livre ajouté
    let checkAddedBook = http.get(`${baseUrl}/get.php?id=${addedBookId}`);
    check(checkAddedBook, { "Consultation du livre ajouté réussie": (r) => r.status === 200 });

    // 🔹 7. Suppression du livre
    let deleteBookResponse = http.get(`${baseUrl}/delete.php?id=${addedBookId}`);
    check(deleteBookResponse, { "Livre supprimé avec succès": (r) => r.status === 200 });
}
