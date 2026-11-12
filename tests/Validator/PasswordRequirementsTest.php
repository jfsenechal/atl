<?php

namespace App\Tests\Validator;

use AcMarche\Mercredi\ResetPassword\Form\ChangePasswordFormType;
use AcMarche\Mercredi\Security\PasswordGenerator;
use AcMarche\Mercredi\Validator\PasswordRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use PHPUnit\Framework\TestCase;

final class PasswordRequirementsTest extends TestCase
{
    public function testValidPasswordIsAccepted(): void
    {
        self::assertCount(0, $this->validate('Abcdefghijk1'));
    }

    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordIsRejected(?string $password): void
    {
        self::assertGreaterThan(0, \count($this->validate($password)));
    }

    public static function invalidPasswords(): iterable
    {
        yield 'empty' => [''];
        yield 'null' => [null];
        yield '11 characters' => ['Abcdefghij1'];
        yield 'no uppercase' => ['abcdefghijk1'];
        yield 'no lowercase' => ['ABCDEFGHIJK1'];
        yield 'no digit' => ['Abcdefghijkl'];
    }

    public function testGeneratedPasswordsSatisfyThePolicy(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $password = PasswordGenerator::generatePassword();
            self::assertCount(0, $this->validate($password), $password);
        }
    }

    public function testChangePasswordFormEnforcesThePolicy(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $weak = $factory->create(ChangePasswordFormType::class);
        $weak->submit(['plainPassword' => ['first' => 'abcdef', 'second' => 'abcdef']]);
        self::assertFalse($weak->isValid());

        $strong = $factory->create(ChangePasswordFormType::class);
        $strong->submit(['plainPassword' => ['first' => 'Abcdefghijk1', 'second' => 'Abcdefghijk1']]);
        self::assertTrue($strong->isValid());
        self::assertSame('Abcdefghijk1', $strong->get('plainPassword')->getData());
    }

    private function validate(?string $password): iterable
    {
        return Validation::createValidator()->validate($password, new PasswordRequirements());
    }
}
