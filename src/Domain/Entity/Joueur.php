<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Entity;

/**
 * Entité Joueur - Représente un joueur de tennis de table
 */
final class Joueur
{
    /**
     * @param int $id Identifiant unique du joueur
     * @param string $nom Nom du joueur
     * @param string $prenom Prénom du joueur
     * @param int $classement Classement du joueur (points FFTT)
     * @param string $licence Numéro de licence FFTT
     */
    public function __construct(
        private readonly int $id,
        private string $nom,
        private string $prenom,
        private int $classement,
        private readonly string $licence
    ) {
        $this->validateNom($nom);
        $this->validatePrenom($prenom);
        $this->validateClassement($classement);
        $this->validateLicence($licence);
    }

    /**
     * Get joueur ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get joueur nom
     */
    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * Get joueur prenom
     */
    public function getPrenom(): string
    {
        return $this->prenom;
    }

    /**
     * Get joueur full name
     */
    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Get joueur classement
     */
    public function getClassement(): int
    {
        return $this->classement;
    }

    /**
     * Get joueur licence
     */
    public function getLicence(): string
    {
        return $this->licence;
    }

    /**
     * Update classement
     */
    public function updateClassement(int $nouveauClassement): void
    {
        $this->validateClassement($nouveauClassement);
        $this->classement = $nouveauClassement;
    }

    /**
     * Update nom
     */
    public function updateNom(string $nouveauNom): void
    {
        $this->validateNom($nouveauNom);
        $this->nom = $nouveauNom;
    }

    /**
     * Update prenom
     */
    public function updatePrenom(string $nouveauPrenom): void
    {
        $this->validatePrenom($nouveauPrenom);
        $this->prenom = $nouveauPrenom;
    }

    /**
     * Validate nom
     */
    private function validateNom(string $nom): void
    {
        if (trim($nom) === '') {
            throw new \InvalidArgumentException('Le nom ne peut pas être vide');
        }

        if (strlen($nom) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères');
        }
    }

    /**
     * Validate prenom
     */
    private function validatePrenom(string $prenom): void
    {
        if (trim($prenom) === '') {
            throw new \InvalidArgumentException('Le prénom ne peut pas être vide');
        }

        if (strlen($prenom) > 100) {
            throw new \InvalidArgumentException('Le prénom ne peut pas dépasser 100 caractères');
        }
    }

    /**
     * Validate classement
     */
    private function validateClassement(int $classement): void
    {
        if ($classement < 0) {
            throw new \InvalidArgumentException('Le classement ne peut pas être négatif');
        }

        if ($classement > 4000) {
            throw new \InvalidArgumentException('Le classement ne peut pas dépasser 4000 points');
        }
    }

    /**
     * Validate licence
     */
    private function validateLicence(string $licence): void
    {
        if (trim($licence) === '') {
            throw new \InvalidArgumentException('Le numéro de licence ne peut pas être vide');
        }

        // Format licence FFTT: 7 chiffres
        if (!preg_match('/^\d{7}$/', $licence)) {
            throw new \InvalidArgumentException('Le numéro de licence doit contenir exactement 7 chiffres');
        }
    }

    /**
     * Compare two joueurs (for sorting by classement)
     */
    public function compareClassement(Joueur $other): int
    {
        return $other->classement <=> $this->classement; // Descending order
    }
}
