<?php


class ApiAnalyzer {

    private $url;
    private $response;
    private $httpCode;
    private $responseTime;
    private $data;

    // Const
    function __construct($param_url) {
        $this->url          = $param_url;
        $this->response     = "";
        $this->httpCode     = 0;
        $this->responseTime = 0;
        $this->data         = null;
    }
	private function getPublicIp() {
    $host = parse_url($this->url, PHP_URL_HOST);

    if (!$host) {
        throw new RuntimeException("URL invalide.");
    }

    $records = dns_get_record($host, DNS_A | DNS_AAAA);

    if ($records === false || empty($records)) {
        throw new RuntimeException("Domaine inaccessible.");
    }

    $ips = [];

    foreach ($records as $record) {
        $ip = $record["ip"] ?? $record["ipv6"] ?? null;

        if (!$ip || !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            throw new RuntimeException("Adresse IP non publique.");
        }

        $ips[] = $ip;
    }

    return $ips[0];
}

    public function fetchApi() {
	$ip = $this->getPublicIp();
	$host = parse_url($this->url, PHP_URL_HOST);
	$port = parse_url($this->url, PHP_URL_PORT) ?: 443;

	if ($port != 443) {
    	    throw new RuntimeException("Seul le port HTTPS 443 est autorisé.");
	}
       $ch = curl_init();
	curl_setopt($ch, CURLOPT_RESOLVE, [
		    $host . ":443:" . $ip
	]);
	curl_setopt($ch, CURLOPT_PROXY, "");
	curl_setopt($ch, CURLOPT_NOPROXY, "*");
        curl_setopt($ch, CURLOPT_URL,            $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_USERAGENT, "APIInsightX/1.1");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);

        $start           = microtime(true);
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
    	static $total = 0;
  	$total += strlen($data);
   	if ($total > 2 * 1024 * 1024) {    return 0;	    }
    	$this->response .= $data;
   	 return strlen($data);
		});
        $result = curl_exec($ch);
        $this->responseTime = round(microtime(true) - $start, 3);
        $this->httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result === false) {
            throw new RuntimeException("Impossible de contacter cette API.");
        }

        // decodage JSON si possible
        if ($this->response !== false) {
            $this->data = json_decode($this->response, true);
        }
    }


    public function getHttpCode() {
        return $this->httpCode;
    }


    public function getResponseTimeMs() {
        return round($this->responseTime * 1000);
    }


    public function getResponseSize() {
        return strlen($this->response);
    }


    public function getRawResponse() {
        return substr($this->response, 0, 2000);
    }


    public function detectResourceType() {
        $parts = explode("/", rtrim($this->url, "/"));
        $last  = strtolower(end($parts));
        $types = ["users","posts","comments","products","todos",
                  "articles","orders","customers","employees"];
        foreach ($types as $t) {
            if (strpos($last, $t) !== false) return $t;
        }
        return $last ?: "ressource inconnue";
    }


    public function detectStructure() {
        if (is_array($this->data)) {
            if (isset($this->data[0])) return "Liste (tableau)";
            return "Objet unique";
        }
        return "Structure non JSON";
    }

    public function buildStructureTree() {
        if ($this->data === null) return [];
        $sample = is_array($this->data) && isset($this->data[0])
                  ? $this->data[0]
                  : $this->data;
        $tree = [];
        if (is_array($sample)) {
            foreach ($sample as $key => $val) {
                $tree[$key] = is_array($val) ? "(objet imbriqué)" : gettype($val);
            }
        }
        return $tree;
    }

    public function computeScore() {
        $score = 100;
        if ($this->httpCode >= 400)      $score -= 40;
        if ($this->responseTime > 1.0)   $score -= 20;
        if ($this->responseTime > 0.5)   $score -= 10;
        if ($this->getResponseSize() > 10000) $score -= 10;
        if (!$this->hasAuth())           $score -= 15;
        if (!$this->hasPagination())     $score -= 5;
        return max(0, $score);
    }

    public function scorePerformance() {
        $s = 100;
        if ($this->responseTime > 1.0)   $s -= 40;
        elseif ($this->responseTime > 0.5) $s -= 20;
        if ($this->getResponseSize() > 10000) $s -= 20;
        if ($this->httpCode >= 500) $s -= 40;
        return max(0, $s);
    }

    public function scoreSecurity() {
        $s = 100;
        if (!$this->hasAuth())   $s -= 60;
        if (strpos($this->url, "https://") === false) $s -= 40;
        return max(0, $s);
    }

    public function scoreStructure() {
        $s = 100;
        if ($this->data === null)             $s -= 50;
        if (!$this->hasPagination())          $s -= 20;
        if ($this->detectStructure() === "Structure non JSON") $s -= 30;
        return max(0, $s);
    }

    public function getWarnings() {
        $warnings = [];
        if ($this->httpCode >= 400)
            $warnings[] = "Erreur HTTP " . $this->httpCode . " détectée";
        if ($this->responseTime > 1.0)
            $warnings[] = "Temps de réponse élevé (" . $this->getResponseTimeMs() . " ms) → risque de timeout";
        if ($this->getResponseSize() > 10000)
            $warnings[] = "Réponse volumineuse (" . $this->getResponseSize() . " octets) → pagination recommandée";
        if (is_array($this->data) && isset($this->data[0]) && count($this->data) > 20)
            $warnings[] = "Liste de " . count($this->data) . " éléments sans pagination détectée";
        if (!$this->hasAuth())
            $warnings[] = "Aucune authentification détectée (API publique)";
        if (strpos($this->url, "https://") === false)
            $warnings[] = "Connexion non sécurisée (HTTP)";
        return $warnings;
    }

    public function getRecommendations() {
        $recs = [];
        if (!$this->hasPagination())
            $recs[] = "Ajouter la pagination : ?page=1&limit=10";
        if (!$this->hasAuth())
            $recs[] = "Sécuriser l'accès avec un token (Bearer) ou une clé API";
        if ($this->responseTime > 0.5)
            $recs[] = "Optimiser le serveur ou activer un cache (Redis, Memcached)";
        if ($this->getResponseSize() > 10000)
            $recs[] = "Réduire la taille des réponses (projection des champs)";
        if (strpos($this->url, "https://") === false)
            $recs[] = "Passer en HTTPS pour chiffrer les échanges";
        return $recs;
    }

    public function getSimulations() {
        $sims = [];
        if (is_array($this->data) && isset($this->data[0])) {
            $n = count($this->data);
            $sims[] = "Si " . ($n * 50) . " utilisateurs simultanés → réponse estimée : "
                    . ($this->getResponseTimeMs() * 3) . " ms (×3)";
            if ($this->getResponseSize() > 5000)
                $sims[] = "Si chargé 1 000 fois/heure → " .
                          round($this->getResponseSize() * 1000 / 1024 / 1024, 1) . " Mo/h de bande passante";
        }
        if ($this->responseTime > 0.5)
            $sims[] = "Si serveur surchargé → timeout possible dès 3× la charge actuelle";
        if (!$this->hasAuth())
            $sims[] = "Sans authentification → n'importe qui peut lire ou scraper ces données";
        if (empty($sims))
            $sims[] = "API légère : simulation de charge sans risque immédiat détecté";
        return $sims;
    }

    public function detectApiType() {
        if ($this->data !== null) return "REST / JSON";
        if (strpos($this->response, "<") !== false) return "Probable XML / HTML";
        return "Inconnu";
    }

    public function getItemCount() {
        if (is_array($this->data) && isset($this->data[0]))
            return count($this->data);
        return null;
    }



    private function hasPagination() {
        return (strpos($this->url, "page") !== false
             || strpos($this->url, "limit") !== false
             || strpos($this->url, "offset") !== false);
    }


    private function hasAuth() {
        return (strpos($this->url, "token")  !== false
             || strpos($this->url, "key")    !== false
             || strpos($this->url, "apikey") !== false
             || strpos($this->url, "auth")   !== false);
    }
}
