<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VibeKanban\TableauEquipes\Domain\Entity\Joueur;

final class JoueurTest extends TestCase
{
    public function testCreateJoueur(): void
    {
        $joueur = new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );

        $this->assertSame(1, $joueur->getId());
        $this->assertSame('Dupont', $joueur->getNom());
        $this->assertSame('Jean', $joueur->getPrenom());
        $this->assertSame(1500, $joueur->getClassement());
        $this->assertSame('1234567', $joueur->getLicence());
    }

    public function testGetFullName(): void
    {
        $joueur = new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );

        $this->assertSame('Jean Dupont', $joueur->getFullName());
    }

    public function testUpdateClassement(): void
    {
        $joueur = new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );

        $joueur->updateClassement(1600);
        $this->assertSame(1600, $joueur->getClassement());
    }

    public function testUpdateNom(): void
    {
        $joueur = new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );

        $joueur->updateNom('Martin');
        $this->assertSame('Martin', $joueur->getNom());
    }

    public function testUpdatePrenom(): void
    {
        $joueur = new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );

        $joueur->updatePrenom('Pierre');
        $this->assertSame('Pierre', $joueur->getPrenom());
    }

    public function testNomCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom ne peut pas être vide');

        new Joueur(
            id: 1,
            nom: '',
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );
    }

    public function testNomCannotBeTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom ne peut pas dépasser 100 caractères');

        new Joueur(
            id: 1,
            nom: str_repeat('a', 101),
            prenom: 'Jean',
            classement: 1500,
            licence: '1234567'
        );
    }

    public function testPrenomCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom ne peut pas être vide');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: '',
            classement: 1500,
            licence: '1234567'
        );
    }

    public function testPrenomCannotBeTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom ne peut pas dépasser 100 caractères');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: str_repeat('a', 101),
            classement: 1500,
            licence: '1234567'
        );
    }

    public function testClassementCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le classement ne peut pas être négatif');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: -1,
            licence: '1234567'
        );
    }

    public function testClassementCannotExceed4000(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le classement ne peut pas dépasser 4000 points');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 4001,
            licence: '1234567'
        );
    }

    public function testLicenceCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de licence ne peut pas être vide');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: ''
        );
    }

    public function testLicenceMustBe7Digits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de licence doit contenir exactement 7 chiffres');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: '123456' // Only 6 digits
        );
    }

    public function testLicenceMustBeNumeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de licence doit contenir exactement 7 chiffres');

        new Joueur(
            id: 1,
            nom: 'Dupont',
            prenom: 'Jean',
            classement: 1500,
            licence: 'ABC1234'
        );
    }

    public function testCompareClassement(): void
    {
        $joueur1 = new Joueur(1, 'Dupont', 'Jean', 1500, '1234567');
        $joueur2 = new Joueur(2, 'Martin', 'Pierre', 1600, '2345678');
        $joueur3 = new Joueur(3, 'Durand', 'Paul', 1500, '3456789');

        // joueur2 > joueur1 (higher classement should come first in descending order)
        // When comparing joueur1 with joueur2 (1500 vs 1600):
        // Result is $other->classement <=> $this->classement = 1600 <=> 1500 = 1 (positive)
        $this->assertGreaterThan(0, $joueur1->compareClassement($joueur2));
        $this->assertLessThan(0, $joueur2->compareClassement($joueur1));

        // joueur1 == joueur3 (same classement)
        $this->assertSame(0, $joueur1->compareClassement($joueur3));
    }

    public function testUpdateClassementWithInvalidValue(): void
    {
        $joueur = new Joueur(1, 'Dupont', 'Jean', 1500, '1234567');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le classement ne peut pas être négatif');

        $joueur->updateClassement(-1);
    }

    public function testUpdateNomWithInvalidValue(): void
    {
        $joueur = new Joueur(1, 'Dupont', 'Jean', 1500, '1234567');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom ne peut pas être vide');

        $joueur->updateNom('');
    }

    public function testUpdatePrenomWithInvalidValue(): void
    {
        $joueur = new Joueur(1, 'Dupont', 'Jean', 1500, '1234567');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom ne peut pas être vide');

        $joueur->updatePrenom('');
    }
}
