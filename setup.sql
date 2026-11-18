-- setup.sql
-- Script de création de la base de données API Insight X
-- À importer dans phpMyAdmin via l'onglet "Importer"

-- Table des analyses enregistrees
CREATE TABLE IF NOT EXISTS scan (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    url        VARCHAR(500) NOT NULL,
    http_code  INT          NOT NULL,
    score      INT          NOT NULL,
    summary    TEXT,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);
