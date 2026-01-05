<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VibeKanban\TableauEquipes\Domain\Entity\Journee;

final class JourneeTest extends TestCase
{
    private \DateTimeImmutable $date;

    protected function setUp(): void
    {
        $this->date = new \DateTimeImmutable('2026-03-15');
    }

    public function testCreateJournee(): void
    {
        $journee = new Journee(
            id: 1,
            numero: 5,
            date: $this->date
        );

        $this->assertSame(1, $journee->getId());
        $this->assertSame(5, $journee->getNumero());
        $this->assertSame($this->date, $journee->getDate());
        $this->assertSame('brouillon', $journee->getStatut());
        $this->assertEmpty($journee->getCompositions());
    }

    public function testCreateJourneeWithStatut(): void
    {
        $journee = new Journee(
            id: 1,
            numero: 5,
            date: $this->date,
            statut: 'validée'
        );

        $this->assertSame('validée', $journee->getStatut());
    }

    public function testSetCompositionEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->setCompositionEquipe(1, [10, 20, 30, 40]);

        $this->assertTrue($journee->hasCompositionEquipe(1));
        $this->assertSame([10, 20, 30, 40], $journee->getCompositionEquipe(1));
    }

    public function testGetCompositionEquipeWhenNotSet(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $this->assertFalse($journee->hasCompositionEquipe(1));
        $this->assertEmpty($journee->getCompositionEquipe(1));
    }

    public function testAddJoueurToEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->addJoueurToEquipe(1, 10);
        $journee->addJoueurToEquipe(1, 20);

        $this->assertSame([10, 20], $journee->getCompositionEquipe(1));
    }

    public function testCannotAddSameJoueurTwiceToEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->addJoueurToEquipe(1, 10);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le joueur 10 est déjà dans l\'équipe 1 pour cette journée');

        $journee->addJoueurToEquipe(1, 10);
    }

    public function testRemoveJoueurFromEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->setCompositionEquipe(1, [10, 20, 30]);
        $journee->removeJoueurFromEquipe(1, 20);

        $this->assertSame([10, 30], $journee->getCompositionEquipe(1));
    }

    public function testRemoveJoueurFromNonExistentEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        // Should not throw exception
        $journee->removeJoueurFromEquipe(99, 10);

        $this->assertFalse($journee->hasCompositionEquipe(99));
    }

    public function testClearCompositionEquipe(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->setCompositionEquipe(1, [10, 20, 30]);
        $this->assertTrue($journee->hasCompositionEquipe(1));

        $journee->clearCompositionEquipe(1);
        $this->assertFalse($journee->hasCompositionEquipe(1));
    }

    public function testClearAllCompositions(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->setCompositionEquipe(1, [10, 20]);
        $journee->setCompositionEquipe(2, [30, 40]);

        $this->assertCount(2, $journee->getCompositions());

        $journee->clearAllCompositions();
        $this->assertEmpty($journee->getCompositions());
    }

    public function testUpdateNumero(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->updateNumero(10);
        $this->assertSame(10, $journee->getNumero());
    }

    public function testUpdateDate(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $newDate = new \DateTimeImmutable('2026-04-20');
        $journee->updateDate($newDate);

        $this->assertSame($newDate, $journee->getDate());
    }

    public function testUpdateStatut(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->updateStatut('validée');
        $this->assertSame('validée', $journee->getStatut());
    }

    public function testValider(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->valider();
        $this->assertSame('validée', $journee->getStatut());
    }

    public function testTerminer(): void
    {
        $journee = new Journee(1, 5, $this->date, 'validée');

        $journee->terminer();
        $this->assertSame('terminée', $journee->getStatut());
    }

    public function testCannotTerminerIfNotValidee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'brouillon');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Seule une journée validée peut être terminée');

        $journee->terminer();
    }

    public function testRetourBrouillon(): void
    {
        $journee = new Journee(1, 5, $this->date, 'validée');

        $journee->retourBrouillon();
        $this->assertSame('brouillon', $journee->getStatut());
    }

    public function testCannotRetourBrouillonIfTerminee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de repasser en brouillon une journée terminée');

        $journee->retourBrouillon();
    }

    public function testIsModifiable(): void
    {
        $journeeBrouillon = new Journee(1, 5, $this->date, 'brouillon');
        $journeeValidee = new Journee(2, 6, $this->date, 'validée');
        $journeeTerminee = new Journee(3, 7, $this->date, 'terminée');

        $this->assertTrue($journeeBrouillon->isModifiable());
        $this->assertTrue($journeeValidee->isModifiable());
        $this->assertFalse($journeeTerminee->isModifiable());
    }

    public function testCannotModifyTermineeComposition(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de modifier une journée terminée');

        $journee->setCompositionEquipe(1, [10, 20]);
    }

    public function testCannotAddJoueurToTerminee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de modifier une journée terminée');

        $journee->addJoueurToEquipe(1, 10);
    }

    public function testCannotRemoveJoueurFromTerminee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de modifier une journée terminée');

        $journee->removeJoueurFromEquipe(1, 10);
    }

    public function testCannotClearCompositionOfTerminee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de modifier une journée terminée');

        $journee->clearCompositionEquipe(1);
    }

    public function testCannotClearAllCompositionsOfTerminee(): void
    {
        $journee = new Journee(1, 5, $this->date, 'terminée');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de modifier une journée terminée');

        $journee->clearAllCompositions();
    }

    public function testNumeroMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de journée doit être supérieur ou égal à 1');

        new Journee(1, 0, $this->date);
    }

    public function testNumeroCannotExceed100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de journée ne peut pas dépasser 100');

        new Journee(1, 101, $this->date);
    }

    public function testStatutMustBeValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être l\'un des suivants : brouillon, validée, terminée');

        new Journee(1, 5, $this->date, 'invalid');
    }

    public function testMultipleEquipesCompositions(): void
    {
        $journee = new Journee(1, 5, $this->date);

        $journee->setCompositionEquipe(1, [10, 20, 30, 40]);
        $journee->setCompositionEquipe(2, [50, 60, 70, 80]);
        $journee->setCompositionEquipe(3, [90, 100, 110, 120]);

        $compositions = $journee->getCompositions();

        $this->assertCount(3, $compositions);
        $this->assertSame([10, 20, 30, 40], $compositions[1]);
        $this->assertSame([50, 60, 70, 80], $compositions[2]);
        $this->assertSame([90, 100, 110, 120], $compositions[3]);
    }
}
