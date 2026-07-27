<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Payment> */
final class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('paidOn', DateType::class, [
                'label' => 'Data incasso',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amountCents', MoneyType::class, [
                'label' => 'Importo',
                'currency' => 'EUR',
                'divisor' => 100,
                'scale' => 2,
            ])
            ->add('description', TextType::class, [
                'label' => 'Descrizione',
                'required' => false,
                'help' => 'Esempio: acconto, saldo, stato avanzamento lavori.',
            ])
            ->add('method', ChoiceType::class, [
                'label' => 'Metodo',
                'choices' => array_combine(Payment::METHODS, Payment::METHODS),
            ])
            ->add('reference', TextType::class, [
                'label' => 'Riferimento',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Payment::class]);
    }
}
