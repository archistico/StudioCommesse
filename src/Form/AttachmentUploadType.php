<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Enum\AttachmentClassification;
use App\Model\AttachmentUpload;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<AttachmentUpload> */
final class AttachmentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Activity> $activityChoices */
        $activityChoices = $options['activity_choices'];

        $builder
            ->add('file', FileType::class, [
                'label' => 'File',
                'help' => 'Massimo 10 MiB. Formati: PDF, PNG, JPG, WEBP, TXT, CSV, DOCX e XLSX.',
                'constraints' => [new Assert\NotNull(message: 'Selezionare un file.')],
            ])
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
                'attr' => ['rows' => 3],
                'constraints' => [new Assert\Length(max: 2000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AttachmentUpload::class,
            'activity_choices' => [],
        ]);
        $resolver->setAllowedTypes('activity_choices', 'array');
    }
}
