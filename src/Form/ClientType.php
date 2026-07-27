<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Client> */
final class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nome o ragione sociale',
                'attr' => ['autocomplete' => 'organization'],
            ])
            ->add('contactPerson', TextType::class, [
                'label' => 'Referente',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Telefono',
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Indirizzo',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('taxCode', TextType::class, [
                'label' => 'Codice fiscale',
                'required' => false,
            ])
            ->add('vatNumber', TextType::class, [
                'label' => 'Partita IVA',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Note operative',
                'required' => false,
                'help' => 'Non inserire qui dati economici o informazioni riservate.',
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Client::class]);
    }
}
