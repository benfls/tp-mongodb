import http from 'k6/http';
import { check, group, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '5s', target: 10 },  // Montée en charge
        { duration: '10s', target: 20 },
        { duration: '10s', target: 50 }, // Pic
        { duration: '5s', target: 20 },
        { duration: '5s', target: 10 }   // Retour normal
    ]
};

export default function () {
    let baseUrl = "http://tpmongo-php:80";

    // 1️⃣ Liste des livres (page 1)
    group('Liste des livres', () => {
        let listResponse = http.get(`${baseUrl}/index.php`, { headers: { Accept: "application/json" } });
        check(listResponse, { "Liste chargée": (r) => r.status === 200 });

        // 2️⃣ Page 4
        let page4Response = http.get(`${baseUrl}/index.php?page=4`, { headers: { Accept: "application/json" } });
        check(page4Response, { "Page 4 chargée": (r) => r.status === 200 });

        // 3️⃣ Détail aléatoire
        let bookIdMatch = listResponse.body.match(/get\.php\?id=([a-f0-9]+)/i);
        if (bookIdMatch && bookIdMatch[1]) {
            let bookId = bookIdMatch[1];
            group('Consultation livre existant', () => {
                let detailsResponse = http.get(`${baseUrl}/get.php?id=${bookId}`);
                check(detailsResponse, { "Détail livre chargé": (r) => r.status === 200 });
            });
        }
    });

    // 4️⃣ Retour liste
    group('Retour à la liste', () => {
        let returnToListResponse = http.get(`${baseUrl}/index.php`);
        check(returnToListResponse, { "Retour liste OK": (r) => r.status === 200 });
    });

    // 5️⃣ Ajout d'un livre
    group('Ajout livre', () => {
        let newBook = {
            title: "K6 Test Book",
            author: "Test Author",
            edition: 'La montagne',
            language:'français',
            cote: "ufzhuifg",
            century: 2023
        };
        let addBookResponse = http.post(`${baseUrl}/create.php`, newBook);
        check(addBookResponse, { "Livre ajouté": (r) => r.status === 200 });

        let addedBookId = addBookResponse.body.trim();

        // 6️⃣ Consultation du livre ajouté
        group('Consultation livre ajouté', () => {
            let checkAddedBook = http.get(`${baseUrl}/get.php?id=${addedBookId}`);
            check(checkAddedBook, { "Livre ajouté consulté": (r) => r.status === 200 });
        });

        // 7️⃣ Suppression livre ajouté
        group('Suppression livre ajouté', () => {
            let deleteBookResponse = http.get(`${baseUrl}/delete.php?id=${addedBookId}`);
            check(deleteBookResponse, { "Livre supprimé": (r) => r.status === 200 });
        });
    });

    sleep(1); // Petit temps d'attente entre les utilisateurs
}
