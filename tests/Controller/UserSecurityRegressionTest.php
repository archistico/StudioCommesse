<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Activity;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserSecurityRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('securityChanges')]
    public function testExistingSessionsAreRevokedAfterSecurityChanges(string $change): void
    {
        $user = $this->createUser('sessione-sicurezza', UserRole::Partner);
        $userId = $user->getId();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/utenti');
        self::assertResponseIsSuccessful();

        $changes = match ($change) {
            'active' => ['active' => 0],
            'role' => ['role' => UserRole::Collaborator->value],
            'username' => ['username' => 'nuovo-identificativo'],
            default => ['password' => self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'Password-nuova-456!')],
        };
        $this->entityManager->getConnection()->update('app_user', $changes, ['id' => $userId]);
        $this->entityManager->clear();

        $this->client->request('GET', '/admin/utenti');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/dashboard');
        self::assertResponseRedirects('/login');
    }

    /** @return iterable<string, array{string}> */
    public static function securityChanges(): iterable
    {
        yield 'disattivazione' => ['active'];
        yield 'retrocessione' => ['role'];
        yield 'cambio nome utente' => ['username'];
        yield 'cambio password' => ['password'];
    }

    public function testClearingTheOptionalUserRateDoesNotCauseAServerError(): void
    {
        $partner = $this->createUser('socio-tariffe', UserRole::Partner);
        $user = $this->createUser('collaboratore-tariffa');
        $user->setDefaultHourlyRateCents(5_000);
        $this->entityManager->flush();
        $userId = $user->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/admin/utenti/'.$userId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'user[defaultHourlyRateCents]' => '',
        ]));

        self::assertResponseRedirects('/admin/utenti');
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame(0, $saved->getDefaultHourlyRateCents());
    }

    public function testNegativeUserRateIsRejectedWithoutOverwritingTheCurrentValue(): void
    {
        $partner = $this->createUser('socio-tariffe-negative', UserRole::Partner);
        $user = $this->createUser('tariffa-negativa');
        $user->setDefaultHourlyRateCents(5_000);
        $this->entityManager->flush();
        $userId = $user->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/admin/utenti/'.$userId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'user[defaultHourlyRateCents]' => '-12,50',
        ]));

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame(5_000, $saved->getDefaultHourlyRateCents());
    }

    public function testDeactivationBlocksWritesFromAnAlreadyOpenForm(): void
    {
        $owner = $this->createUser('responsabile-commessa');
        $user = $this->createUser('sessione-disattivata');
        $project = $this->createProject($this->createCustomer(), $owner);
        $userId = $user->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/attivita/commessa/'.$project->getId().'/nuova');
        $values = $crawler->selectButton('Crea attività')->form(['activity[title]' => 'Scrittura dopo disattivazione'])->getPhpValues();
        $this->entityManager->getConnection()->update('app_user', ['active' => 0], ['id' => $userId]);
        $this->entityManager->clear();

        $this->client->request('POST', '/attivita/commessa/'.$project->getId().'/nuova', $values);

        self::assertResponseRedirects('/login');
        self::assertSame(0, $this->entityManager->getRepository(Activity::class)->count([]));
    }

    public function testNonSecurityProfileChangesKeepTheSessionValid(): void
    {
        $user = $this->createUser('sessione-profilo', UserRole::Partner);
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/utenti');
        self::assertResponseIsSuccessful();
        $this->entityManager->getConnection()->update('app_user', [
            'display_name' => 'Nome aggiornato',
            'default_hourly_rate_cents' => 6_000,
        ], ['id' => $user->getId()]);
        $this->entityManager->clear();

        $this->client->request('GET', '/admin/utenti');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Nome aggiornato');
    }

    public function testInvalidOwnUsernameEditDoesNotCorruptTheAuthenticatedSession(): void
    {
        $partner = $this->createUser('socio-profilo-valido', UserRole::Partner);
        $this->createUser('username-gia-utilizzato');
        $partnerId = $partner->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/admin/utenti/'.$partnerId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'user[username]' => 'username-gia-utilizzato',
        ]));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/admin/utenti');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $partnerId);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame('socio-profilo-valido', $saved->getUsername());
    }

    #[DataProvider('forbiddenOwnChanges')]
    public function testOwnAccountCannotBeDeactivatedOrDemoted(string $field, string|bool $value): void
    {
        $partner = $this->createUser('socio-protetto', UserRole::Partner);
        $this->createUser('altro-socio', UserRole::Partner);
        $partnerId = $partner->getId();
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/utenti/'.$partnerId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'user['.$field.']' => $value,
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Non puoi disattivare o retrocedere il tuo stesso account.');
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $partnerId);
        self::assertInstanceOf(User::class, $saved);
        self::assertTrue($saved->isActive());
        self::assertSame(UserRole::Partner, $saved->getRole());
        $this->client->request('GET', '/admin/utenti');
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string, string|bool}> */
    public static function forbiddenOwnChanges(): iterable
    {
        yield 'disattivazione' => ['active', false];
        yield 'retrocessione' => ['role', UserRole::Collaborator->value];
    }

    public function testSuccessfulOwnCredentialsEditKeepsTheUpdatedSessionValid(): void
    {
        $partner = $this->createUser('socio-credenziali', UserRole::Partner);
        $partnerId = $partner->getId();
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/utenti/'.$partnerId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'user[username]' => 'socio-credenziali-nuove',
            'user[plainPassword]' => 'Password-nuova-789!',
        ]));

        self::assertResponseRedirects('/admin/utenti');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $partnerId);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame('socio-credenziali-nuove', $saved->getUsername());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($saved, 'Password-nuova-789!'));
    }

    public function testReactivationDoesNotRestoreAnAlreadyRevokedSession(): void
    {
        $user = $this->createUser('sessione-riattivata', UserRole::Partner);
        $userId = $user->getId();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/utenti');
        self::assertResponseIsSuccessful();
        $this->entityManager->getConnection()->update('app_user', ['active' => 0], ['id' => $userId]);
        $this->entityManager->clear();
        $this->client->request('GET', '/admin/utenti');
        self::assertResponseRedirects('/login');

        $this->entityManager->getConnection()->update('app_user', ['active' => 1], ['id' => $userId]);
        $this->entityManager->clear();
        $this->client->request('GET', '/admin/utenti');

        self::assertResponseRedirects('/login');
    }

    public function testUserCreationAcceptsAMultibytePasswordAtTheHasherByteLimit(): void
    {
        $partner = $this->createUser('socio-password-valida', UserRole::Partner);
        $this->client->loginUser($partner);
        $password = str_repeat('é', 2_048);
        $crawler = $this->client->request('GET', '/admin/utenti/nuovo');
        $this->client->submit($crawler->selectButton('Crea utente')->form([
            'user[displayName]' => 'Utente limite valido',
            'user[username]' => 'password-limite-valido',
            'user[plainPassword]' => $password,
            'user[defaultHourlyRateCents]' => '',
        ]));

        self::assertResponseRedirects('/admin/utenti');
        $this->entityManager->clear();
        $saved = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'password-limite-valido']);
        self::assertInstanceOf(User::class, $saved);
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($saved, $password));
    }

    #[DataProvider('saveModes')]
    public function testMissingRoleIsRejectedWithoutAServerError(bool $editing): void
    {
        $partner = $this->createUser('socio-ruolo-obbligatorio', UserRole::Partner);
        $user = $editing ? $this->createUser('ruolo-vuoto') : null;
        $userId = $user?->getId();
        $this->client->loginUser($partner);
        $path = $editing ? '/admin/utenti/'.$userId.'/modifica' : '/admin/utenti/nuovo';
        $crawler = $this->client->request('GET', $path);
        $values = $crawler->selectButton($editing ? 'Salva modifiche' : 'Crea utente')->form([
            'user[displayName]' => 'Utente senza ruolo',
            'user[username]' => 'ruolo-vuoto',
            'user[plainPassword]' => 'Password-valida-456!',
        ])->getPhpValues();
        $values['user']['role'] = '';

        $this->client->request('POST', $path, $values);

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        self::assertSame($editing ? 2 : 1, $this->entityManager->getRepository(User::class)->count([]));
    }

    #[DataProvider('saveModes')]
    public function testAnOversizedMultibytePasswordIsRejectedBeforeHashing(bool $editing): void
    {
        $partner = $this->createUser('socio-password', UserRole::Partner);
        $user = $editing ? $this->createUser('password-modificata') : null;
        $userId = $user?->getId();
        $oldHash = $user?->getPassword();
        $this->client->loginUser($partner);
        $path = $editing ? '/admin/utenti/'.$userId.'/modifica' : '/admin/utenti/nuovo';

        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->selectButton($editing ? 'Salva modifiche' : 'Crea utente')->form([
            'user[displayName]' => 'Utente password lunga',
            'user[username]' => $editing ? 'password-modificata' : 'password-troppo-lunga',
            'user[plainPassword]' => str_repeat('é', 2_049),
        ]));

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        if ($editing) {
            $saved = $this->entityManager->find(User::class, $userId);
            self::assertInstanceOf(User::class, $saved);
            self::assertSame($oldHash, $saved->getPassword());
        } else {
            self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['username' => 'password-troppo-lunga']));
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function saveModes(): iterable
    {
        yield 'creazione' => [false];
        yield 'modifica' => [true];
    }
}
