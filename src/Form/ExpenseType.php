<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Expense;
use App\Entity\Project;
use App\Repository\ActivityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Expense> */
final class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'];
        if (!$project instanceof Project) {
            throw new \LogicException('La commessa è obbligatoria per il form spesa.');
        }

        $builder
            ->add('spentOn', DateType::class, [
                'label' => 'Data',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('activity', EntityType::class, [
                'label' => 'Attività',
                'class' => Activity::class,
                'choice_label' => 'title',
                'query_builder' => static fn (ActivityRepository $repository): QueryBuilder => $repository->createForProjectQueryBuilder($project),
                'placeholder' => 'Nessuna attività specifica',
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categoria',
                'choices' => array_combine(Expense::CATEGORIES, Expense::CATEGORIES),
            ])
            ->add('description', TextType::class, ['label' => 'Descrizione'])
            ->add('amountCents', MoneyType::class, [
                'label' => 'Importo',
                'currency' => 'EUR',
                'divisor' => 100,
                'scale' => 2,
            ])
            ->add('reimbursable', CheckboxType::class, [
                'label' => 'Rimborsabile dal cliente',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Expense::class]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
