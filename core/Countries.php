<?php
// core/Countries.php

class Countries
{
    /**
     * Génère dynamiquement l'emoji du drapeau à partir du code ISO (ex: TG => 🇹🇬)
     */
    public static function getFlagEmoji(string $iso): string
    {
        $iso = strtoupper(trim($iso));
        if (strlen($iso) !== 2) {
            return '🌐';
        }

        // Conversion ISO 3166-1 alpha-2 vers Regional Indicator Symbols Unicode
        $firstChar  = mb_chr(127397 + ord($iso[0]), 'UTF-8');
        $secondChar = mb_chr(127397 + ord($iso[1]), 'UTF-8');

        return $firstChar . $secondChar;
    }

    /**
     * Dictionnaire complet des pays indexé par code ISO (ISO 3166-1 alpha-2)
     */
    public static function getAll(): array
    {
        $countries = [
            // Afrique de l'Ouest & Centrale
            'TG' => ['name' => 'Togo', 'dial_code' => '+228'],
            'BJ' => ['name' => 'Bénin', 'dial_code' => '+229'],
            'CI' => ['name' => "Côte d'Ivoire", 'dial_code' => '+225'],
            'GH' => ['name' => 'Ghana', 'dial_code' => '+233'],
            'BF' => ['name' => 'Burkina Faso', 'dial_code' => '+226'],
            'NG' => ['name' => 'Nigéria', 'dial_code' => '+234'],
            'SN' => ['name' => 'Sénégal', 'dial_code' => '+221'],
            'ML' => ['name' => 'Mali', 'dial_code' => '+223'],
            'NE' => ['name' => 'Niger', 'dial_code' => '+227'],
            'CM' => ['name' => 'Cameroun', 'dial_code' => '+237'],
            'GA' => ['name' => 'Gabon', 'dial_code' => '+241'],
            'CG' => ['name' => 'Congo-Brazzaville', 'dial_code' => '+242'],
            'CD' => ['name' => 'RDC (Congo-Kinshasa)', 'dial_code' => '+243'],
            'GN' => ['name' => 'Guinée', 'dial_code' => '+224'],
            'LR' => ['name' => 'Liberia', 'dial_code' => '+231'],
            'SL' => ['name' => 'Sierra Leone', 'dial_code' => '+232'],
            'GM' => ['name' => 'Gambie', 'dial_code' => '+220'],
            'GW' => ['name' => 'Guinée-Bissau', 'dial_code' => '+245'],
            'MR' => ['name' => 'Mauritanie', 'dial_code' => '+222'],
            'TD' => ['name' => 'Tchad', 'dial_code' => '+235'],
            'CF' => ['name' => 'Rép. Centrafricaine', 'dial_code' => '+236'],
            'GQ' => ['name' => 'Guinée équatoriale', 'dial_code' => '+240'],

            // Reste de l'Afrique
            'ZA' => ['name' => 'Afrique du Sud', 'dial_code' => '+27'],
            'DZ' => ['name' => 'Algérie', 'dial_code' => '+213'],
            'AO' => ['name' => 'Angola', 'dial_code' => '+244'],
            'BW' => ['name' => 'Botswana', 'dial_code' => '+267'],
            'BI' => ['name' => 'Burundi', 'dial_code' => '+257'],
            'CV' => ['name' => 'Cap-Vert', 'dial_code' => '+238'],
            'KM' => ['name' => 'Comores', 'dial_code' => '+269'],
            'DJ' => ['name' => 'Djibouti', 'dial_code' => '+253'],
            'EG' => ['name' => 'Égypte', 'dial_code' => '+20'],
            'ER' => ['name' => 'Érythrée', 'dial_code' => '+291'],
            'ET' => ['name' => 'Éthiopie', 'dial_code' => '+251'],
            'KE' => ['name' => 'Kenya', 'dial_code' => '+254'],
            'LS' => ['name' => 'Lesotho', 'dial_code' => '+266'],
            'LY' => ['name' => 'Libye', 'dial_code' => '+218'],
            'MG' => ['name' => 'Madagascar', 'dial_code' => '+261'],
            'MW' => ['name' => 'Malawi', 'dial_code' => '+265'],
            'MA' => ['name' => 'Maroc', 'dial_code' => '+212'],
            'MU' => ['name' => 'Maurice', 'dial_code' => '+230'],
            'MZ' => ['name' => 'Mozambique', 'dial_code' => '+258'],
            'NA' => ['name' => 'Namibie', 'dial_code' => '+264'],
            'RW' => ['name' => 'Rwanda', 'dial_code' => '+250'],
            'ST' => ['name' => 'Sao Tomé-et-Principe', 'dial_code' => '+239'],
            'SC' => ['name' => 'Seychelles', 'dial_code' => '+248'],
            'SO' => ['name' => 'Somalie', 'dial_code' => '+252'],
            'SS' => ['name' => 'Soudan du Sud', 'dial_code' => '+211'],
            'SD' => ['name' => 'Soudan', 'dial_code' => '+249'],
            'SZ' => ['name' => 'Eswatini', 'dial_code' => '+268'],
            'TZ' => ['name' => 'Tanzanie', 'dial_code' => '+255'],
            'TN' => ['name' => 'Tunisie', 'dial_code' => '+216'],
            'UG' => ['name' => 'Ouganda', 'dial_code' => '+256'],
            'ZM' => ['name' => 'Zambie', 'dial_code' => '+260'],
            'ZW' => ['name' => 'Zimbabwe', 'dial_code' => '+263'],

            // Europe
            'FR' => ['name' => 'France', 'dial_code' => '+33'],
            'BE' => ['name' => 'Belgique', 'dial_code' => '+32'],
            'CH' => ['name' => 'Suisse', 'dial_code' => '+41'],
            'DE' => ['name' => 'Allemagne', 'dial_code' => '+49'],
            'GB' => ['name' => 'Royaume-Uni', 'dial_code' => '+44'],
            'IT' => ['name' => 'Italie', 'dial_code' => '+39'],
            'ES' => ['name' => 'Espagne', 'dial_code' => '+34'],
            'PT' => ['name' => 'Portugal', 'dial_code' => '+351'],
            'NL' => ['name' => 'Pays-Bas', 'dial_code' => '+31'],
            'AT' => ['name' => 'Autriche', 'dial_code' => '+43'],
            'SE' => ['name' => 'Suède', 'dial_code' => '+46'],
            'NO' => ['name' => 'Norvège', 'dial_code' => '+47'],
            'DK' => ['name' => 'Danemark', 'dial_code' => '+45'],
            'FI' => ['name' => 'Finlande', 'dial_code' => '+358'],
            'IE' => ['name' => 'Irlande', 'dial_code' => '+353'],
            'PL' => ['name' => 'Pologne', 'dial_code' => '+48'],
            'RU' => ['name' => 'Russie', 'dial_code' => '+7'],
            'UA' => ['name' => 'Ukraine', 'dial_code' => '+380'],
            'TR' => ['name' => 'Turquie', 'dial_code' => '+90'],
            'GR' => ['name' => 'Grèce', 'dial_code' => '+30'],

            // Amériques
            'US' => ['name' => 'États-Unis', 'dial_code' => '+1'],
            'CA' => ['name' => 'Canada', 'dial_code' => '+1'],
            'BR' => ['name' => 'Brésil', 'dial_code' => '+55'],
            'MX' => ['name' => 'Mexique', 'dial_code' => '+52'],
            'AR' => ['name' => 'Argentine', 'dial_code' => '+54'],
            'CO' => ['name' => 'Colombie', 'dial_code' => '+57'],
            'CL' => ['name' => 'Chili', 'dial_code' => '+56'],
            'PE' => ['name' => 'Pérou', 'dial_code' => '+51'],
            'HT' => ['name' => 'Haïti', 'dial_code' => '+509'],

            // Asie & Moyen-Orient
            'CN' => ['name' => 'Chine', 'dial_code' => '+86'],
            'IN' => ['name' => 'Inde', 'dial_code' => '+91'],
            'JP' => ['name' => 'Japon', 'dial_code' => '+81'],
            'AE' => ['name' => 'Émirats Arabes Unis', 'dial_code' => '+971'],
            'SA' => ['name' => 'Arabie Saoudite', 'dial_code' => '+966'],
            'QA' => ['name' => 'Qatar', 'dial_code' => '+974'],
            'KR' => ['name' => 'Corée du Sud', 'dial_code' => '+82'],
            'SG' => ['name' => 'Singapour', 'dial_code' => '+65'],
            'MY' => ['name' => 'Malaisie', 'dial_code' => '+60'],
            'TH' => ['name' => 'Thaïlande', 'dial_code' => '+66'],
            'VN' => ['name' => 'Viêt Nam', 'dial_code' => '+84'],
            'ID' => ['name' => 'Indonésie', 'dial_code' => '+62'],
            'PK' => ['name' => 'Pakistan', 'dial_code' => '+92'],
            'BD' => ['name' => 'Bangladesh', 'dial_code' => '+880'],
            'IL' => ['name' => 'Israël', 'dial_code' => '+972'],
            'LB' => ['name' => 'Liban', 'dial_code' => '+961'],

            // Océanie
            'AU' => ['name' => 'Australie', 'dial_code' => '+61'],
            'NZ' => ['name' => 'Nouvelle-Zélande', 'dial_code' => '+64'],
        ];

        // Injecte la clé 'flag' avec l'emoji généré dynamiquement
        foreach ($countries as $iso => &$data) {
            $data['flag'] = self::getFlagEmoji($iso);
        }

        return $countries;
    }

    /**
     * Récupère un pays par son code ISO (ex: 'TG', 'fr')
     * 
     * @param string $iso Code ISO du pays
     * @return array|null Le tableau associatif du pays ou null s'il n'existe pas
     */
    public static function getByIso(string $iso): ?array
    {
        $code = strtoupper(trim($iso));
        $countries = self::getAll();

        return $countries[$code] ?? null;
    }

    /**
     * Génère les balises <option> HTML pour un formulaire <select>
     * 
     * @param string|null $selectedDialCode L'indicatif sélectionné (ex: '+228')
     * @return string Balises HTML <option>
     */
    public static function renderSelectOptions(?string $selectedDialCode = '+228'): string
    {
        $options = '';
        $selectedDialCode = trim($selectedDialCode ?? '+228');

        foreach (self::getAll() as $iso => $country) {
            $isSelected = ($country['dial_code'] === $selectedDialCode) ? 'selected' : '';
            $label = htmlspecialchars("{$country['flag']} {$country['name']} ({$country['dial_code']})");
            $value = htmlspecialchars($country['dial_code']);
            
            $options .= "<option value=\"{$value}\" {$isSelected}>{$label}</option>\n";
        }

        return $options;
    }

    /**
     * Formate un numéro au format E.164 (ex: +22890123456)
     * 
     * @param string $dialCode Indicatif du pays (ex: '+228')
     * @param string $localNumber Numéro local (ex: '90123456' ou '090123456')
     * @return string Numéro formaté au format international
     */
    public static function formatPhone(string $dialCode, string $localNumber): string
    {
        // Supprime tout ce qui n'est pas un chiffre dans le numéro local
        $cleanNumber = preg_replace('/[^0-9]/', '', $localNumber);

        // Supprime le premier zéro si présent (ex: 090123456 => 90123456)
        if (strpos($cleanNumber, '0') === 0) {
            $cleanNumber = substr($cleanNumber, 1);
        }

        if (empty($cleanNumber)) {
            return '';
        }

        // Nettoyage et formatage de l'indicatif
        $cleanDialCode = (strpos($dialCode, '+') === 0) ? $dialCode : '+' . preg_replace('/[^0-9]/', '', $dialCode);

        return $cleanDialCode . $cleanNumber;
    }
}