<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VibeKanban\TableauEquipes\Domain\Entity\Equipe;
use VibeKanban\TableauEquipes\Domain\Entity\Joueur;

final class EquipeTest extends TestCase
{
    private function createJoueur(int $id, string $nom, int $classement): Joueur
    {
        return new Joueur($id, $nom, 'Jean', $classement, sprintf('%07d', $id));
    }

    public function testCreateEquipe(): void
    {
        $equipe = new Equipe(
            id: 1,
            nom: 'Équipe Senior A',
            division: 1
        );

        $this->assertSame(1, $equipe->getId());
        $this->assertSame('Équipe Senior A', $equipe->getNom());
        $this->assertSame(1, $equipe->getDivision());
        $this->assertSame(4, $equipe->getMaxJoueurs());
        $this->assertSame(0, $equipe->getNombreJoueurs());
        $this->assertEmpty($equipe->getJoueurs());
    }

    public function testCreateEquipeWithCustomMaxJoueurs(): void
    {
        $equipe = new Equipe(
            id: 1,
            nom: 'Équipe Senior A',
            division: 1,
            maxJoueurs: 6
        );

        $this->assertSame(6, $equipe->getMaxJoueurs());
    }

    public function testAddJoueur(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);
        $joueur = $this->createJoueur(1, 'Dupont', 1500);

        $equipe->addJoueur($joueur);

        $this->assertSame(1, $equipe->getNombreJoueurs());
        $this->assertTrue($equipe->hasJoueur(1));
        $this->assertFalse($equipe->isFull());
    }

    public function testAddMultipleJoueurs(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);
        $joueur3 = $this->createJoueur(3, 'Durand', 1400);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);
        $equipe->addJoueur($joueur3);

        $this->assertSame(3, $equipe->getNombreJoueurs());
        $this->assertFalse($equipe->isFull());
    }

    public function testEquipeIsFull(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1, maxJoueurs: 2);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);

        $equipe->addJoueur($joueur1);
        $this->assertFalse($equipe->isFull());

        $equipe->addJoueur($joueur2);
        $this->assertTrue($equipe->isFull());
    }

    public function testCannotAddJoueurWhenFull(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1, maxJoueurs: 2);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);
        $joueur3 = $this->createJoueur(3, 'Durand', 1400);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('L\'équipe "Équipe 1" est complète (2 joueurs maximum)');

        $equipe->addJoueur($joueur3);
    }

    public function testCannotAddSameJoueurTwice(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);
        $joueur = $this->createJoueur(1, 'Dupont', 1500);

        $equipe->addJoueur($joueur);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le joueur "Jean Dupont" est déjà dans l\'équipe "Équipe 1"');

        $equipe->addJoueur($joueur);
    }

    public function testRemoveJoueur(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);
        $joueur = $this->createJoueur(1, 'Dupont', 1500);

        $equipe->addJoueur($joueur);
        $this->assertTrue($equipe->hasJoueur(1));

        $equipe->removeJoueur(1);
        $this->assertFalse($equipe->hasJoueur(1));
        $this->assertSame(0, $equipe->getNombreJoueurs());
    }

    public function testCannotRemoveNonExistentJoueur(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le joueur avec l\'ID 99 n\'est pas dans l\'équipe "Équipe 1"');

        $equipe->removeJoueur(99);
    }

    public function testGetJoueursSortedByClassement(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1800);
        $joueur3 = $this->createJoueur(3, 'Durand', 1200);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);
        $equipe->addJoueur($joueur3);

        $sorted = $equipe->getJoueursSortedByClassement();

        $this->assertCount(3, $sorted);
        $this->assertSame(1800, $sorted[0]->getClassement());
        $this->assertSame(1500, $sorted[1]->getClassement());
        $this->assertSame(1200, $sorted[2]->getClassement());
    }

    public function testGetTotalPoints(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);
        $joueur3 = $this->createJoueur(3, 'Durand', 1400);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);
        $equipe->addJoueur($joueur3);

        $this->assertSame(4500, $equipe->getTotalPoints());
    }

    public function testGetAverageClassement(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);

        $this->assertSame(1550.0, $equipe->getAverageClassement());
    }

    public function testGetAverageClassementWhenEmpty(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $this->assertSame(0.0, $equipe->getAverageClassement());
    }

    public function testUpdateNom(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $equipe->updateNom('Nouvelle Équipe');
        $this->assertSame('Nouvelle Équipe', $equipe->getNom());
    }

    public function testUpdateDivision(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $equipe->updateDivision(2);
        $this->assertSame(2, $equipe->getDivision());
    }

    public function testClearJoueurs(): void
    {
        $equipe = new Equipe(1, 'Équipe 1', 1);

        $joueur1 = $this->createJoueur(1, 'Dupont', 1500);
        $joueur2 = $this->createJoueur(2, 'Martin', 1600);

        $equipe->addJoueur($joueur1);
        $equipe->addJoueur($joueur2);
        $this->assertSame(2, $equipe->getNombreJoueurs());

        $equipe->clearJoueurs();
        $this->assertSame(0, $equipe->getNombreJoueurs());
        $this->assertEmpty($equipe->getJoueurs());
    }

    public function testNomCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'équipe ne peut pas être vide');

        new Equipe(1, '', 1);
    }

    public function testNomCannotBeTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'équipe ne peut pas dépasser 100 caractères');

        new Equipe(1, str_repeat('a', 101), 1);
    }

    public function testDivisionMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La division doit être supérieure ou égale à 1');

        new Equipe(1, 'Équipe 1', 0);
    }

    public function testMaxJoueursMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre maximum de joueurs doit être supérieur ou égal à 1');

        new Equipe(1, 'Équipe 1', 1, maxJoueurs: 0);
    }

    public function testMaxJoueursCannotExceed10(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre maximum de joueurs ne peut pas dépasser 10');

        new Equipe(1, 'Équipe 1', 1, maxJoueurs: 11);
    }
}
