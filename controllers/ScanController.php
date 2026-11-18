<?php


class ScanController {

    private $scanManager;

    function __construct($param_scanManager) {
        $this->scanManager = $param_scanManager;
    }

    public function runAnalysis($url) {

        $analyzer = new ApiAnalyzer($url);
        $analyzer->fetchApi();


        $result = [
            "url"              => $url,
            "http_code"        => $analyzer->getHttpCode(),
            "response_time_ms" => $analyzer->getResponseTimeMs(),
            "response_size"    => $analyzer->getResponseSize(),
            "raw_response"     => $analyzer->getRawResponse(),
            "api_type"         => $analyzer->detectApiType(),
            "resource_type"    => $analyzer->detectResourceType(),
            "structure"        => $analyzer->detectStructure(),
            "item_count"       => $analyzer->getItemCount(),
            "tree"             => $analyzer->buildStructureTree(),
            "score"            => $analyzer->computeScore(),
            "score_performance"=> $analyzer->scorePerformance(),
            "score_security"   => $analyzer->scoreSecurity(),
            "score_structure"  => $analyzer->scoreStructure(),
            "warnings"         => $analyzer->getWarnings(),
            "recommendations"  => $analyzer->getRecommendations(),
            "simulations"      => $analyzer->getSimulations(),
        ];


        $this->scanManager->saveScan(
            $url,
            $result["http_code"],
            $result["score"],
            json_encode([
                "api_type"    => $result["api_type"],
                "structure"   => $result["structure"],
                "warnings"    => count($result["warnings"]),
                "recs"        => count($result["recommendations"]),
            ])
        );

        return $result;
    }


    public function getHistory() {
        return $this->scanManager->getAllScans();
    }


    public function deleteScan($id) {
        $this->scanManager->deleteScan($id);
    }
}
