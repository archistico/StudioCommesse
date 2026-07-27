<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\ActivityPriority;
use App\Enum\ActivityStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Activity> */
final class ActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activity = $builder->getData();
        $currentAssigneeId = $activity instanceof Activity ? $activity->getAssignee()?->getId() : null;

        $builder
            ->add('title', TextType::class, ['label' => 'Attività'])
            ->add('assignee', EntityType::class, [
                'label' => 'Assegnatario',
                'class' => User::class,
                'choice_label' => 'displayName',
                'query_builder' => static fn (UserRepository $repository): QueryBuilder => $repository->createSelectableQueryBuilder($currentAssigneeId),
            ])
            ->add('status', EnumType::class, [
                'label' => 'Stato',
                'class' => ActivityStatus::class,
                'choice_label' => static fn (ActivityStatus $status): string => $status->label(),
            ])
            ->add('priority', EnumType::class, [
                'label' => 'Priorità',
                'class' => ActivityPriority::class,
                'choice_label' => static fn (ActivityPriority $priority): string => $priority->label(),
            ])
            ->add('progressPercent', IntegerType::class, [
                'label' => 'Avanzamento %',
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('initialEstimatedMinutes', IntegerType::class, [
                'label' => 'Stima iniziale (minuti)',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('remainingEstimatedMinutes', IntegerType::class, [
                'label' => 'Stima residua (minuti)',
                'required' => false,
                'attr' => ['min' => 0],
            ]);

        if ((bool) $options['allow_financial']) {
            $builder->add('hourlyRateOverrideCents', MoneyType::class, [
                'label' => 'Tariffa oraria specifica',
                'currency' => 'EUR',
                'divisor' => 100,
                'scale' => 2,
                'required' => false,
                'help' => 'Ha precedenza sulla tariffa della commessa e del collaboratore.',
            ]);
        }

        $builder
            ->add('startAt', DateTimeType::class, [
                'label' => 'Inizio',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueAt', DateTimeType::class, [
                'label' => 'Scadenza',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descrizione',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
            'allow_financial' => false,
        ]);
        $resolver->setAllowedTypes('allow_financial', 'bool');
    }
}
