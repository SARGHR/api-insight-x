# API Insight X — Analyseur d’API REST

Projet universitaire de programmation web backend (L3 Informatique, Lyon 2), réalisé en binôme. Cette édition reprend le code original avec des corrections de configuration et de sécurité.

## Fonctionnalités
- Requête HTTPS vers une API publique, avec cURL
- Analyse de réponse JSON, temps de réponse, code HTTP et score heuristique
- Conseils et simulations indicatives (non tests de charge réels)
- Historique des analyses dans MariaDB et espace administrateur

## Technologies
PHP POO, MVC, TinyButStrong, cURL, MariaDB, PDO, HTML/CSS.

## Installation Linux (Debian / Penguin)

```bash
sudo apt update
sudo apt install php-cli php-mysql php-curl mariadb-server
sudo service mariadb start
sudo mariadb < setup.sql
```

`setup.sql` peut nécessiter des droits administrateur pour la création de la base. Créer ensuite un utilisateur MariaDB dédié et lui accorder uniquement les droits nécessaires sur `api_insight`.

Démarrer avec des variables d'environnement (ne jamais publier les vraies valeurs) :

```bash
export DB_HOST=127.0.0.1 DB_NAME=api_insight DB_USER=api_user
export DB_PASSWORD='votre_mot_de_passe_local'
export ADMIN_USER=admin_api
export ADMIN_PASSWORD_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' 'votre_mot_de_passe_admin')"
php -S localhost:8000
```

Ouvrir http://localhost:8000. Les URL analysées doivent être publiques et en HTTPS. Les URL locales, privées et non résolues sont refusées. Ne pas exposer cette application sur Internet sans protections supplémentaires (DNS rebinding, limites de requêtes et authentification).

## Crédits
Projet original en binôme : Sarah Ghouar (logique backend, cURL, scoring, PDO, routeur, SQL) et Kane Faye Moustapha (interface, intégration, contrôleurs). Ne pas publier de secrets ni de données personnelles.
