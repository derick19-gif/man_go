<?php
// core/Utils.php

declare(strict_types=1);

class Utils 
{
    /**
     * Vrifie si un domaine de site web a au moins 12 mois via RDAP
     */
    public static function isDomainOlderThan12Months(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        
        // Nettoyer le sous-domaine (ex: www.exemple.com -> exemple.com)
        $domainParts = explode('.', $host);
        if (count($domainParts) > 2) {
            $host = implode('.', array_slice($domainParts, -2));
        }

        $rdapUrl = "https://rdap.org/domain/" . urlencode($host);
        
        $ch = curl_init($rdapUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);
        if (!isset($data['events'])) {
            return false;
        }

        foreach ($data['events'] as $event) {
            if (($event['eventAction'] ?? '') === 'registration') {
                $registrationDate = new DateTime($event['eventDate']);
                $now = new DateTime();
                $interval = $now->diff($registrationDate);

                return $interval->y >= 1;
            }
        }

        return false;
    }
}