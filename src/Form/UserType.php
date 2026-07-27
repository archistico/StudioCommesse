<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<User> */
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordRequired = (bool) $options['password_required'];

        $passwordConstraints = [new Length(min: 12, max: 4096, minMessage: 'La password deve contenere almeno {{ limit }} caratteri.')];
        if ($passwordRequired) {
            array_unshift($passwordConstraints, new NotBlank(message: 'La password è obbligatoria.'));
        }

        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Nome e cognome',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('username', TextType::class, [
                'label' => 'Nome utente',
                'help' => 'Solo lettere minuscole, numeri, punto, trattino e underscore.',
                'attr' => ['autocomplete' => 'username'],
            ])
            ->add('role', EnumType::class, [
                'label' => 'Ruolo',
                'class' => UserRole::class,
                'choice_label' => static fn (UserRole $role): string => $role->label(),
            ])
            ->add('defaultHourlyRateCents', MoneyType::class, [
                'label' => 'Tariffa oraria standard',
                'currency' => 'EUR',
                'divisor' => 100,
                'scale' => 2,
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $passwordRequired ? 'Password' : 'Nuova password',
                'mapped' => false,
                'required' => $passwordRequired,
                'help' => $passwordRequired
                    ? 'Almeno 12 caratteri.'
                    : 'Lasciare vuoto per mantenere la password corrente.',
                'constraints' => $passwordConstraints,
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Account attivo',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => false,
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
    }
}
