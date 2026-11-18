<?php


class ScanManager {

    private $pdo; // Id

    function __construct($param_pdo) {
        $this->pdo = $param_pdo;
    }


    public function saveScan($url, $httpCode, $score, $summary) {
        $res = $this->pdo->prepare(
            "INSERT INTO scan (url, http_code, score, summary, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $res->execute([$url, $httpCode, $score, $summary]);
    }


    public function getAllScans() {
        $res = $this->pdo->prepare(
            "SELECT * FROM scan ORDER BY created_at DESC"
        );
        $res->execute();
        return $res->fetchAll();
    }

    public function getScanById($id) {
        $res = $this->pdo->prepare(
            "SELECT * FROM scan WHERE id = ?"
        );
        $res->execute([$id]);
        return $res->fetch();
    }

    public function deleteScan($id) {
        $res = $this->pdo->prepare(
            "DELETE FROM scan WHERE id = ?"
        );
        $res->execute([$id]);
    }

    public function getRecentScans($limit = 10) {
        $res = $this->pdo->prepare(
            "SELECT * FROM scan ORDER BY created_at DESC LIMIT ?"
        );
        $res->execute([$limit]);
        return $res->fetchAll();
    }
}
