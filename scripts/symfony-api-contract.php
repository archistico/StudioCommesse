<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use App\Security\ActiveUserChecker;
use App\Security\Voter\ProjectVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;

$autoloadPath = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Dipendenze Composer non installate: vendor/autoload.php mancante.\n");
    exit(1);
}

require $autoloadPath;

$errors = [];

if (!interface_exists(PasswordUpgraderInterface::class)) {
    $errors[] = sprintf('Interfaccia Symfony mancante: %s.', PasswordUpgraderInterface::class);
} elseif (!is_subclass_of(UserRepository::class, PasswordUpgraderInterface::class)) {
    $errors[] = sprintf('%s deve implementare %s.', UserRepository::class, PasswordUpgraderInterface::class);
} else {
    $method = new ReflectionMethod(UserRepository::class, 'upgradePassword');
    $parameters = $method->getParameters();

    if (2 !== count($parameters)) {
        $errors[] = 'UserRepository::upgradePassword() deve dichiarare esattamente due parametri.';
    } else {
        $firstType = $parameters[0]->getType();
        $secondType = $parameters[1]->getType();

        if (!$firstType instanceof ReflectionNamedType || PasswordAuthenticatedUserInterface::class !== $firstType->getName()) {
            $errors[] = sprintf(
                'Il primo parametro di UserRepository::upgradePassword() deve essere %s.',
                PasswordAuthenticatedUserInterface::class,
            );
        }

        if (!$secondType instanceof ReflectionNamedType || 'string' !== $secondType->getName()) {
            $errors[] = 'Il secondo parametro di UserRepository::upgradePassword() deve essere string.';
        }
    }

    $returnType = $method->getReturnType();
    if (!$returnType instanceof ReflectionNamedType || 'void' !== $returnType->getName()) {
        $errors[] = 'UserRepository::upgradePassword() deve restituire void.';
    }
}

if (!is_subclass_of(ActiveUserChecker::class, UserCheckerInterface::class)) {
    $errors[] = sprintf('%s deve implementare %s.', ActiveUserChecker::class, UserCheckerInterface::class);
} else {
    $method = new ReflectionMethod(ActiveUserChecker::class, 'checkPostAuth');
    $parameters = $method->getParameters();

    if (2 !== count($parameters)) {
        $errors[] = 'ActiveUserChecker::checkPostAuth() deve dichiarare due parametri.';
    } else {
        $tokenType = $parameters[1]->getType();
        if (
            !$tokenType instanceof ReflectionNamedType
            || TokenInterface::class !== $tokenType->getName()
            || !$tokenType->allowsNull()
            || !$parameters[1]->isDefaultValueAvailable()
            || null !== $parameters[1]->getDefaultValue()
        ) {
            $errors[] = sprintf(
                'Il token di ActiveUserChecker::checkPostAuth() deve essere ?%s con valore predefinito null.',
                TokenInterface::class,
            );
        }
    }
}


if (!is_subclass_of(ProjectVoter::class, Voter::class)) {
    $errors[] = sprintf('%s deve estendere %s.', ProjectVoter::class, Voter::class);
} else {
    $method = new ReflectionMethod(ProjectVoter::class, 'voteOnAttribute');
    $parameters = $method->getParameters();

    if (4 !== count($parameters)) {
        $errors[] = 'ProjectVoter::voteOnAttribute() deve dichiarare quattro parametri in Symfony 8.1.';
    } else {
        $voteType = $parameters[3]->getType();
        if (
            !$voteType instanceof ReflectionNamedType
            || Vote::class !== $voteType->getName()
            || !$voteType->allowsNull()
            || !$parameters[3]->isDefaultValueAvailable()
            || null !== $parameters[3]->getDefaultValue()
        ) {
            $errors[] = sprintf(
                'Il quarto parametro di ProjectVoter::voteOnAttribute() deve essere ?%s con valore predefinito null.',
                Vote::class,
            );
        }
    }
}

if ([] !== $errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "Contratti API Symfony 8.1 verificati.\n");
