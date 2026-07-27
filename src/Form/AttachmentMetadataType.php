<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Enum\AttachmentClassification;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Attachment> */
final class AttachmentMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Activity> $activityChoices */
        $activityChoices = $options['activity_choices'];

        $builder
            ->add('classification', EnumType::class, [
                'label' => 'Classificazione',
                'class' => AttachmentClassification::class,
                'choice_label' => static fn (AttachmentClassification $classification): string => $classification->label(),
            ])
            ->add('activity', EntityType::class, [
                'label' => 'Attività collegata',
                'class' => Activity::class,
                'choices' => $activityChoices,
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => 'Documento generale della commessa',
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
            'data_class' => Attachment::class,
            'activity_choices' => [],
        ]);
        $resolver->setAllowedTypes('activity_choices', 'array');
    }
}
