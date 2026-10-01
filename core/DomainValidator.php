<?php
namespace App\Core;

class DomainValidator {
    /**
     * Nettoie l'URL pour ne garder que le domaine pur
     */
    public static function extractDomain($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;
        $host = preg_replace('/^www\./', '', $host);
        return strtolower(trim($host));
    }

    /**
     * Interroge l'API mondiale RDAP pour trouver la date de création du domaine
     */
    public static function checkDomainAge($url, $requiredDays = 365) {
        $domain = self::extractDomain($url);
        
        $apiUrl = "https://rdap.org/domain/" . urlencode($domain);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/rdap+json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return [
                'success' => false, 
                'message' => "Impossible d'analyser l'âge du site '$domain'. Assurez-vous que le lien est valide."
            ];
        }

        $data = json_decode($response, true);
        $creationDate = null;

        if (!empty($data['events'])) {
            foreach ($data['events'] as $event) {
                if (isset($event['eventAction']) && strtolower($event['eventAction']) === 'registration') {
                    $creationDate = $event['eventDate'];
                    break;
                }
            }
        }

        if (!$creationDate) {
            return [
                'success' => false, 
                'message' => "Les informations de création de '$domain' sont masquées ou illisibles."
            ];
        }

        $createdTimestamp = strtotime($creationDate);
        $currentTimestamp = time();
        $ageInDays = floor(($currentTimestamp - $createdTimestamp) / (60 * 60 * 24));

        if ($ageInDays >= $requiredDays) {
            return [
                'success' => true,
                'domain' => $domain,
                'age_days' => $ageInDays,
                'creation_date' => date('d/m/Y', $createdTimestamp),
                'message' => "Domaine validé ($ageInDays jours d'ancienneté)."
            ];
        } else {
            return [
                'success' => false,
                'domain' => $domain,
                'age_days' => $ageInDays,
                'creation_date' => date('d/m/Y', $createdTimestamp),
                'message' => "Refusé : Ce site n'a que $ageInDays jours. MAN GO exige une ancienneté minimale de $requiredDays jours (1 an) pour protéger ses utilisateurs contre la fraude."
            ];
        }
    }
}