<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TimeEntry;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TimeEntry> */
final class TimeEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startedAt', DateTimeType::class, [
                'label' => 'Inizio',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => true,
            ])
            ->add('endedAt', DateTimeType::class, [
                'label' => 'Fine',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Lavoro svolto',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('billable', CheckboxType::class, [
                'label' => 'Fatturabile',
                'required' => false,
            ]);

        foreach (['startedAt' => 'L’inizio è obbligatorio.', 'endedAt' => 'La fine è obbligatoria.'] as $field => $message) {
            $builder->get($field)->addModelTransformer(new CallbackTransformer(
                static fn (?DateTimeImmutable $value): ?DateTimeImmutable => $value,
                static fn (?DateTimeImmutable $value): DateTimeImmutable => $value ?? throw new TransformationFailedException(
                    invalidMessage: $message,
                ),
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TimeEntry::class]);
    }
}
