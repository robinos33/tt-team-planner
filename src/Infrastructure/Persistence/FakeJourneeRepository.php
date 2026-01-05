<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Infrastructure\Persistence;

use VibeKanban\TableauEquipes\Domain\Entity\Journee;
use VibeKanban\TableauEquipes\Domain\Repository\JourneeRepositoryInterface;

/**
 * Repository with fake data for journées
 * Simulates a championship season with multiple journées
 */
final class FakeJourneeRepository implements JourneeRepositoryInterface
{
    /** @var array<int, Journee> */
    private array $journees = [];

    public function __construct()
    {
        $this->initializeFakeData();
    }

    public function findById(int $id): ?Journee
    {
        return $this->journees[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->journees);
    }

    public function findByNumero(int $numero): ?Journee
    {
        foreach ($this->journees as $journee) {
            if ($journee->getNumero() === $numero) {
                return $journee;
            }
        }
        return null;
    }

    public function findByStatut(string $statut): array
    {
        return array_filter(
            $this->journees,
            fn(Journee $journee) => $journee->getStatut() === $statut
        );
    }

    public function save(Journee $journee): void
    {
        $this->journees[$journee->getId()] = $journee;
    }

    public function delete(int $id): void
    {
        unset($this->journees[$id]);
    }

    /**
     * Initialize fake data - Championship season with multiple journées
     */
    private function initializeFakeData(): void
    {
        $currentDate = new \DateTimeImmutable('2026-09-01');

        // Create journées for a championship season (typically 14-22 journées)
        // Status:
        // - Past journées (1-3): terminée
        // - Current journée (4): validée
        // - Near future (5-6): brouillon
        // - Future (7-18): brouillon

        for ($i = 1; $i <= 18; $i++) {
            // Each journée is typically 2 weeks apart
            $journeeDate = $currentDate->modify('+' . (($i - 1) * 14) . ' days');

            // Determine status based on journée number
            if ($i <= 3) {
                $statut = 'terminée';
            } elseif ($i === 4) {
                $statut = 'validée';
            } else {
                $statut = 'brouillon';
            }

            $journee = new Journee(
                id: $i,
                numero: $i,
                date: $journeeDate,
                statut: $statut
            );

            // Add some sample compositions for completed journées
            if ($statut === 'terminée') {
                // For demonstration, add fake compositions for first 3 équipes
                $journee->setCompositionEquipe(1, [1, 2, 3, 4]);
                $journee->setCompositionEquipe(2, [5, 6, 7, 8]);
                $journee->setCompositionEquipe(3, [9, 10, 11, 12]);
            }

            // Add partial composition for current journée
            if ($statut === 'validée') {
                $journee->setCompositionEquipe(1, [1, 3, 5, 7]);
                $journee->setCompositionEquipe(2, [2, 4, 6, 8]);
            }

            $this->journees[$i] = $journee;
        }
    }
}
