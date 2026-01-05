<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Entity;

/**
 * Entité Journee - Représente une journée de championnat
 */
final class Journee
{
    /** @var array<int, array<int, int>> Composition des équipes [equipeId => [joueurId1, joueurId2, ...]] */
    private array $compositions = [];

    /**
     * @param int $id Identifiant unique de la journée
     * @param int $numero Numéro de la journée (1, 2, 3, etc.)
     * @param \DateTimeImmutable $date Date de la journée
     * @param string $statut Statut de la journée (brouillon, validée, terminée)
     */
    public function __construct(
        private readonly int $id,
        private int $numero,
        private \DateTimeImmutable $date,
        private string $statut = 'brouillon'
    ) {
        $this->validateNumero($numero);
        $this->validateStatut($statut);
    }

    /**
     * Get journée ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get journée numero
     */
    public function getNumero(): int
    {
        return $this->numero;
    }

    /**
     * Get journée date
     */
    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Get journée statut
     */
    public function getStatut(): string
    {
        return $this->statut;
    }

    /**
     * Get compositions
     * @return array<int, array<int, int>>
     */
    public function getCompositions(): array
    {
        return $this->compositions;
    }

    /**
     * Get composition for specific équipe
     * @return array<int, int>
     */
    public function getCompositionEquipe(int $equipeId): array
    {
        return $this->compositions[$equipeId] ?? [];
    }

    /**
     * Check if équipe has a composition
     */
    public function hasCompositionEquipe(int $equipeId): bool
    {
        return isset($this->compositions[$equipeId]) && !empty($this->compositions[$equipeId]);
    }

    /**
     * Set composition for équipe
     * @param int $equipeId
     * @param array<int, int> $joueurIds
     */
    public function setCompositionEquipe(int $equipeId, array $joueurIds): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de modifier une journée terminée');
        }

        $this->compositions[$equipeId] = array_values($joueurIds);
    }

    /**
     * Add joueur to équipe composition
     */
    public function addJoueurToEquipe(int $equipeId, int $joueurId): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de modifier une journée terminée');
        }

        if (!isset($this->compositions[$equipeId])) {
            $this->compositions[$equipeId] = [];
        }

        if (in_array($joueurId, $this->compositions[$equipeId], true)) {
            throw new \DomainException(
                sprintf('Le joueur %d est déjà dans l\'équipe %d pour cette journée', $joueurId, $equipeId)
            );
        }

        $this->compositions[$equipeId][] = $joueurId;
    }

    /**
     * Remove joueur from équipe composition
     */
    public function removeJoueurFromEquipe(int $equipeId, int $joueurId): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de modifier une journée terminée');
        }

        if (!isset($this->compositions[$equipeId])) {
            return;
        }

        $key = array_search($joueurId, $this->compositions[$equipeId], true);
        if ($key !== false) {
            unset($this->compositions[$equipeId][$key]);
            $this->compositions[$equipeId] = array_values($this->compositions[$equipeId]);
        }
    }

    /**
     * Clear composition for équipe
     */
    public function clearCompositionEquipe(int $equipeId): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de modifier une journée terminée');
        }

        unset($this->compositions[$equipeId]);
    }

    /**
     * Clear all compositions
     */
    public function clearAllCompositions(): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de modifier une journée terminée');
        }

        $this->compositions = [];
    }

    /**
     * Update numero
     */
    public function updateNumero(int $nouveauNumero): void
    {
        $this->validateNumero($nouveauNumero);
        $this->numero = $nouveauNumero;
    }

    /**
     * Update date
     */
    public function updateDate(\DateTimeImmutable $nouvelleDate): void
    {
        $this->date = $nouvelleDate;
    }

    /**
     * Update statut
     */
    public function updateStatut(string $nouveauStatut): void
    {
        $this->validateStatut($nouveauStatut);
        $this->statut = $nouveauStatut;
    }

    /**
     * Mark as validée
     */
    public function valider(): void
    {
        $this->updateStatut('validée');
    }

    /**
     * Mark as terminée
     */
    public function terminer(): void
    {
        if ($this->statut !== 'validée') {
            throw new \DomainException('Seule une journée validée peut être terminée');
        }

        $this->updateStatut('terminée');
    }

    /**
     * Mark as brouillon
     */
    public function retourBrouillon(): void
    {
        if ($this->statut === 'terminée') {
            throw new \DomainException('Impossible de repasser en brouillon une journée terminée');
        }

        $this->updateStatut('brouillon');
    }

    /**
     * Check if journée is modifiable
     */
    public function isModifiable(): bool
    {
        return $this->statut !== 'terminée';
    }

    /**
     * Validate numero
     */
    private function validateNumero(int $numero): void
    {
        if ($numero < 1) {
            throw new \InvalidArgumentException('Le numéro de journée doit être supérieur ou égal à 1');
        }

        if ($numero > 100) {
            throw new \InvalidArgumentException('Le numéro de journée ne peut pas dépasser 100');
        }
    }

    /**
     * Validate statut
     */
    private function validateStatut(string $statut): void
    {
        $statutsValides = ['brouillon', 'validée', 'terminée'];

        if (!in_array($statut, $statutsValides, true)) {
            throw new \InvalidArgumentException(
                sprintf('Le statut doit être l\'un des suivants : %s', implode(', ', $statutsValides))
            );
        }
    }
}
