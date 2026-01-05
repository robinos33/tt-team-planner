<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Infrastructure\Persistence;

use VibeKanban\TableauEquipes\Domain\Entity\Joueur;
use VibeKanban\TableauEquipes\Domain\Repository\JoueurRepositoryInterface;

/**
 * Repository with fake data for joueurs
 * Simulates approximately 200 joueurs for testing
 */
final class FakeJoueurRepository implements JoueurRepositoryInterface
{
    /** @var array<int, Joueur> */
    private array $joueurs = [];

    public function __construct()
    {
        $this->initializeFakeData();
    }

    public function findById(int $id): ?Joueur
    {
        return $this->joueurs[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->joueurs);
    }

    public function findByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->joueurs[$id])) {
                $result[$id] = $this->joueurs[$id];
            }
        }
        return $result;
    }

    public function findByClassementRange(int $min, int $max): array
    {
        return array_filter(
            $this->joueurs,
            fn(Joueur $joueur) => $joueur->getClassement() >= $min && $joueur->getClassement() <= $max
        );
    }

    public function save(Joueur $joueur): void
    {
        $this->joueurs[$joueur->getId()] = $joueur;
    }

    public function delete(int $id): void
    {
        unset($this->joueurs[$id]);
    }

    /**
     * Initialize fake data - approximately 200 joueurs
     */
    private function initializeFakeData(): void
    {
        $noms = [
            'Dupont', 'Martin', 'Bernard', 'Thomas', 'Robert', 'Petit', 'Richard', 'Durand',
            'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'David',
            'Bertrand', 'Roux', 'Vincent', 'Fournier', 'Morel', 'Girard', 'Andre', 'Lefevre',
            'Mercier', 'Dupuis', 'Lambert', 'Bonnet', 'Francois', 'Martinez', 'Legrand', 'Garnier',
            'Faure', 'Rousseau', 'Blanc', 'Guerin', 'Muller', 'Henry', 'Roussel', 'Nicolas',
            'Perrin', 'Morin', 'Mathieu', 'Clement', 'Gauthier', 'Dumont', 'Lopez', 'Fontaine',
            'Chevalier', 'Robin', 'Masson', 'Sanchez', 'Gerard', 'Nguyen', 'Boyer', 'Denis',
            'Lemaire', 'Duval', 'Joly', 'Gautier', 'Roger', 'Roy', 'Noel', 'Meyer'
        ];

        $prenoms = [
            'Jean', 'Pierre', 'Michel', 'Andre', 'Philippe', 'Alain', 'Jacques', 'Bernard',
            'Patrick', 'Christian', 'Claude', 'Francois', 'Daniel', 'Gerard', 'Rene', 'Marcel',
            'Louis', 'Paul', 'Robert', 'Georges', 'Marc', 'Henri', 'Yves', 'Julien',
            'Nicolas', 'Alexandre', 'Thomas', 'Antoine', 'Maxime', 'Lucas', 'Hugo', 'Nathan',
            'Marie', 'Sophie', 'Isabelle', 'Catherine', 'Nathalie', 'Sylvie', 'Christine', 'Martine',
            'Monique', 'Annie', 'Francoise', 'Patricia', 'Nicole', 'Brigitte', 'Veronique', 'Julie',
            'Camille', 'Laura', 'Emma', 'Lea', 'Chloe', 'Manon', 'Sarah', 'Clara'
        ];

        // Generate approximately 200 joueurs with varying classements
        $id = 1;
        for ($i = 0; $i < 200; $i++) {
            $nom = $noms[array_rand($noms)];
            $prenom = $prenoms[array_rand($prenoms)];

            // Distribution of classements (points FFTT):
            // - 20% beginners (500-999)
            // - 40% intermediates (1000-1499)
            // - 30% advanced (1500-1999)
            // - 10% experts (2000-3000)
            $rand = mt_rand(1, 100);
            if ($rand <= 20) {
                $classement = mt_rand(500, 999);
            } elseif ($rand <= 60) {
                $classement = mt_rand(1000, 1499);
            } elseif ($rand <= 90) {
                $classement = mt_rand(1500, 1999);
            } else {
                $classement = mt_rand(2000, 3000);
            }

            $licence = sprintf('%07d', $id + 1000000);

            try {
                $this->joueurs[$id] = new Joueur(
                    id: $id,
                    nom: $nom . $id, // Add ID to make names unique
                    prenom: $prenom,
                    classement: $classement,
                    licence: $licence
                );
                $id++;
            } catch (\Exception $e) {
                // Skip invalid data
                continue;
            }
        }
    }
}
