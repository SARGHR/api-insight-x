<?php
// Configuration locale via variables d’environnement (aucun secret dans Git).
$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'api_insight';
$login = getenv('DB_USER') ?: 'api_user';
$password = getenv('DB_PASSWORD') ?: '';
