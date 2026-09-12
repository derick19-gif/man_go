<?php
// core/Professions.php

class Professions
{
    public static function getAll(): array
    {
        return [
            'Administration & Juridique' => [
                'Assistant Comptable & Gestionnaire',
                'Avocat & Conseiller Juridique',
                'Fiscaliste & Auditeur financier',
                'Notaire & Huissier',
                'Secrétaire de direction & Assistant(e)'
            ],
            'Santé & Bien-être' => [
                'Infirmier & Soignant à domicile',
                'Kinésithérapeute & Masseur',
                'Médecin Généraliste & Spécialiste',
                'Pharmacien & Herboriste',
                'Psychologue & Coach de vie'
            ],
            'Éducation & Formation' => [
                'Formateur en ligne & Tuteur',
                'Instituteur & Enseignant',
                'Professeur particulier (Langues, Maths...)',
                'Éducateur spécialisé'
            ],
            'Services à la personne & Foyer' => [
                'Cuisinier(ère) à domicile & Traiteur',
                'Gardien & Agent de sécurité',
                'Jardinier & Paysagiste',
                'Ménagère & Agent d\'entretien',
                'Nounou & Baby-sitter'
            ],
            'Commerce, Vente & E-commerce' => [
                'Commerçant & Boutiquier',
                'E-commerçant & Dropshipper',
                'Importateur & Grossiste',
                'Commercial & Agent d\'apport d\'affaires'
            ],
            'Artisanat, BTP & Technique' => [
                'Architecte & Ingénieur Bâtiment',
                'Carreleur, Maçon & Plombier',
                'Électricien & Frigoriste',
                'Fermier, Agriculteur & Agro-entrepreneur',
                'Mécanicien & Technicien auto',
                'Menuisier & Ébéniste'
            ],
            'Numérique, Tech & Création' => [
                'Développeur Web & Mobile',
                'Graphiste, Designer & Illustrateur',
                'Monteur Vidéo & Monteur de contenu',
                'Community Manager & Marketeur Digital'
            ]
        ];
    }

    public static function renderOptions(): string
    {
        $html = '';
        foreach (self::getAll() as $category => $professions) {
            $html .= "<optgroup label=\"{$category}\">\n";
            foreach ($professions as $prof) {
                $html .= "<option value=\"{$prof}\">{$prof}</option>\n";
            }
            $html .= "</optgroup>\n";
        }
        return $html;
    }
}