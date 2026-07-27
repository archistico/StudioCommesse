<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Client;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttachmentClassification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AttachmentTest extends TestCase
{
    public function testActivityMustBelongToTheSameProject(): void
    {
        $user = (new User())->setUsername('utente')->setDisplayName('Utente');
        $firstProject = (new Project())->setName('Prima')->setClient(new Client())->setResponsible($user);
        $secondProject = (new Project())->setName('Seconda')->setClient(new Client())->setResponsible($user);
        $activity = (new Activity())->setProject($secondProject)->setAssignee($user)->setCreatedBy($user)->setTitle('Attività');
        $attachment = new Attachment(
            $firstProject,
            $activity,
            $user,
            AttachmentClassification::Technical,
            'documento.pdf',
            '2026/07/0123456789abcdef0123456789abcdef.pdf',
            'application/pdf',
            100,
            str_repeat('a', 64),
        );

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($attachment);

        self::assertCount(1, $violations);
        self::assertSame('activity', $violations[0]->getPropertyPath());
    }

    public function testDescriptionIsNormalized(): void
    {
        $user = (new User())->setUsername('utente')->setDisplayName('Utente');
        $project = (new Project())->setName('Commessa')->setClient(new Client())->setResponsible($user);
        $attachment = new Attachment(
            $project,
            null,
            $user,
            AttachmentClassification::Other,
            'nota.txt',
            '2026/07/0123456789abcdef0123456789abcdef.txt',
            'text/plain',
            10,
            str_repeat('b', 64),
            '  Nota utile  ',
        );

        self::assertSame('Nota utile', $attachment->getDescription());
        $attachment->setDescription('   ');
        self::assertNull($attachment->getDescription());
    }
}
