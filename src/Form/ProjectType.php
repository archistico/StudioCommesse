<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Project> */
final class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $builder->getData();
        $currentClientId = $project instanceof Project ? $project->getClient()?->getId() : null;
        $currentResponsibleId = $project instanceof Project ? $project->getResponsible()?->getId() : null;
        $allowAdministration = (bool) $options['allow_administration'];

        $builder->add('name', TextType::class, ['label' => 'Nome della commessa']);

        if ($allowAdministration) {
            $builder
                ->add('client', EntityType::class, [
                    'label' => 'Cliente',
                    'class' => Client::class,
                    'choice_label' => 'name',
                    'query_builder' => static fn (ClientRepository $repository): QueryBuilder => $repository->createSelectableQueryBuilder($currentClientId),
                    'placeholder' => 'Seleziona il cliente',
                ])
                ->add('responsible', EntityType::class, [
                    'label' => 'Responsabile',
                    'class' => User::class,
                    'choice_label' => 'displayName',
                    'query_builder' => static fn (UserRepository $repository): QueryBuilder => $repository->createSelectableQueryBuilder($currentResponsibleId),
                    'placeholder' => 'Seleziona il responsabile',
                ]);
        }

        $builder
            ->add('status', EnumType::class, [
                'label' => 'Stato',
                'class' => ProjectStatus::class,
                'choice_label' => static fn (ProjectStatus $status): string => $status->label(),
            ])
            ->add('priority', EnumType::class, [
                'label' => 'Priorità',
                'class' => ProjectPriority::class,
                'choice_label' => static fn (ProjectPriority $priority): string => $priority->label(),
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Data effettiva di inizio',
                'required' => false,
                'help' => 'Se vuota, viene impostata quando la commessa passa “In corso”.',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Data prevista di fine',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descrizione',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('waitingReason', TextareaType::class, [
                'label' => 'Motivo dell’attesa',
                'required' => false,
                'help' => 'Viene conservato soltanto quando lo stato è “In attesa”.',
                'attr' => ['rows' => 3],
            ]);

        if ($allowAdministration) {
            $builder
                ->add('estimatedAmountCents', MoneyType::class, [
                    'label' => 'Preventivo',
                    'currency' => 'EUR',
                    'divisor' => 100,
                    'scale' => 2,
                    'required' => false,
                    'help' => 'Importo a corpo preventivato per la commessa.',
                ])
                ->add('defaultHourlyRateCents', MoneyType::class, [
                    'label' => 'Tariffa oraria della commessa',
                    'currency' => 'EUR',
                    'divisor' => 100,
                    'scale' => 2,
                    'required' => false,
                    'help' => 'Usata quando l’attività non ha una tariffa più specifica.',
                ]);
        }

        $builder->add('privateNote', TextareaType::class, [
            'label' => 'Nota riservata',
            'required' => false,
            'help' => 'Visibile soltanto ai soci e al responsabile della commessa.',
            'attr' => ['rows' => 4],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'allow_administration' => false,
        ]);
        $resolver->setAllowedTypes('allow_administration', 'bool');
    }
}
