<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Entity;

/**
 * Entité Equipe - Représente une équipe de tennis de table
 */
final class Equipe
{
    /** @var array<int, Joueur> */
    private array $joueurs = [];

    /**
     * @param int $id Identifiant unique de l'équipe
     * @param string $nom Nom de l'équipe (ex: "Équipe 1", "Équipe Senior A")
     * @param int $division Division/championnat de l'équipe
     * @param int $maxJoueurs Nombre maximum de joueurs autorisés (par défaut 4)
     */
    public function __construct(
        private readonly int $id,
        private string $nom,
        private int $division,
        private readonly int $maxJoueurs = 4
    ) {
        $this->validateNom($nom);
        $this->validateDivision($division);
        $this->validateMaxJoueurs($maxJoueurs);
    }

    /**
     * Get équipe ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get équipe nom
     */
    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * Get équipe division
     */
    public function getDivision(): int
    {
        return $this->division;
    }

    /**
     * Get max joueurs
     */
    public function getMaxJoueurs(): int
    {
        return $this->maxJoueurs;
    }

    /**
     * Get joueurs
     * @return array<int, Joueur>
     */
    public function getJoueurs(): array
    {
        return $this->joueurs;
    }

    /**
     * Get nombre de joueurs actuels
     */
    public function getNombreJoueurs(): int
    {
        return count($this->joueurs);
    }

    /**
     * Check if équipe is full
     */
    public function isFull(): bool
    {
        return $this->getNombreJoueurs() >= $this->maxJoueurs;
    }

    /**
     * Add joueur to équipe
     */
    public function addJoueur(Joueur $joueur): void
    {
        if ($this->isFull()) {
            throw new \DomainException(
                sprintf('L\'équipe "%s" est complète (%d joueurs maximum)', $this->nom, $this->maxJoueurs)
            );
        }

        if ($this->hasJoueur($joueur->getId())) {
            throw new \DomainException(
                sprintf('Le joueur "%s" est déjà dans l\'équipe "%s"', $joueur->getFullName(), $this->nom)
            );
        }

        $this->joueurs[$joueur->getId()] = $joueur;
    }

    /**
     * Remove joueur from équipe
     */
    public function removeJoueur(int $joueurId): void
    {
        if (!$this->hasJoueur($joueurId)) {
            throw new \DomainException(
                sprintf('Le joueur avec l\'ID %d n\'est pas dans l\'équipe "%s"', $joueurId, $this->nom)
            );
        }

        unset($this->joueurs[$joueurId]);
    }

    /**
     * Check if équipe has joueur
     */
    public function hasJoueur(int $joueurId): bool
    {
        return isset($this->joueurs[$joueurId]);
    }

    /**
     * Get joueurs sorted by classement (descending)
     * @return array<int, Joueur>
     */
    public function getJoueursSortedByClassement(): array
    {
        $joueurs = $this->joueurs;
        usort($joueurs, fn(Joueur $a, Joueur $b) => $a->compareClassement($b));
        return $joueurs;
    }

    /**
     * Get total points of équipe (sum of all joueurs classement)
     */
    public function getTotalPoints(): int
    {
        return array_reduce(
            $this->joueurs,
            fn(int $total, Joueur $joueur) => $total + $joueur->getClassement(),
            0
        );
    }

    /**
     * Get average classement of équipe
     */
    public function getAverageClassement(): float
    {
        if ($this->getNombreJoueurs() === 0) {
            return 0.0;
        }

        return $this->getTotalPoints() / $this->getNombreJoueurs();
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
     * Update division
     */
    public function updateDivision(int $nouvelleDivision): void
    {
        $this->validateDivision($nouvelleDivision);
        $this->division = $nouvelleDivision;
    }

    /**
     * Clear all joueurs
     */
    public function clearJoueurs(): void
    {
        $this->joueurs = [];
    }

    /**
     * Validate nom
     */
    private function validateNom(string $nom): void
    {
        if (trim($nom) === '') {
            throw new \InvalidArgumentException('Le nom de l\'équipe ne peut pas être vide');
        }

        if (strlen($nom) > 100) {
            throw new \InvalidArgumentException('Le nom de l\'équipe ne peut pas dépasser 100 caractères');
        }
    }

    /**
     * Validate division
     */
    private function validateDivision(int $division): void
    {
        if ($division < 1) {
            throw new \InvalidArgumentException('La division doit être supérieure ou égale à 1');
        }
    }

    /**
     * Validate max joueurs
     */
    private function validateMaxJoueurs(int $maxJoueurs): void
    {
        if ($maxJoueurs < 1) {
            throw new \InvalidArgumentException('Le nombre maximum de joueurs doit être supérieur ou égal à 1');
        }

        if ($maxJoueurs > 10) {
            throw new \InvalidArgumentException('Le nombre maximum de joueurs ne peut pas dépasser 10');
        }
    }
}
