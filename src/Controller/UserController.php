<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use App\Service\UserAccountGuard;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utenti')]
#[IsGranted('ROLE_PARTNER')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nuovo', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        AuditedTransaction $transaction,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['password_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (!is_string($plainPassword) || '' === $plainPassword) {
                throw new \LogicException('La password validata deve essere una stringa non vuota.');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $actor = $this->requireCurrentUser();
            $transaction->execute(
                static function () use ($userRepository, $user): User {
                    $userRepository->save($user, false);

                    return $user;
                },
                static fn (User $saved): AuditRecord => new AuditRecord(
                    AuditAction::UserCreated,
                    $actor->getUserIdentifier(),
                    User::class,
                    $saved->getId(),
                    [
                        'username' => $saved->getUsername(),
                        'role' => $saved->getRole()->value,
                        'active' => $saved->isActive(),
                    ],
                    $request->getClientIp(),
                ),
            );

            $this->addFlash('success', 'Utente creato correttamente.');

            return $this->redirectToRoute('app_user_index');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nuovo utente',
            'submit_label' => 'Crea utente',
        ], new Response(status: $status));
    }

    #[Route('/{id}/modifica', name: 'app_user_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        UserRepository $userRepository,
        ProjectRepository $projectRepository,
        UserPasswordHasherInterface $passwordHasher,
        UserAccountGuard $guard,
        AuditedTransaction $transaction,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $previousRole = $user->getRole();
        $previouslyActive = $user->isActive();
        $previousUsername = $user->getUsername();
        $sessionUser = $this->requireCurrentUser();
        $originalSessionUser = $sessionUser->getId() === $user->getId() ? clone $sessionUser : null;

        $form = $this->createForm(UserType::class, $user, ['password_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actor = $this->requireCurrentUser();
            $guardError = $guard->validateUpdate(
                $actor,
                $previousRole,
                $previouslyActive,
                $user,
                $userRepository->countActiveByRole(UserRole::Partner),
            );

            if (null !== $guardError) {
                $form->addError(new FormError($guardError));
            } elseif ($previouslyActive && !$user->isActive() && $projectRepository->countNonArchivedForResponsible($user) > 0) {
                $form->addError(new FormError('L’utente è responsabile di commesse non archiviate e non può essere disattivato.'));
            } else {
                $plainPassword = $form->get('plainPassword')->getData();
                $passwordChanged = is_string($plainPassword) && '' !== $plainPassword;
                if ($passwordChanged) {
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                }

                $transaction->execute(
                    static function () use ($userRepository, $user): User {
                        $userRepository->save($user, false);

                        return $user;
                    },
                    static fn (User $saved): AuditRecord => new AuditRecord(
                        AuditAction::UserUpdated,
                        $actor->getUserIdentifier(),
                        User::class,
                        $saved->getId(),
                        [
                            'previous_username' => $previousUsername,
                            'username' => $saved->getUsername(),
                            'previous_role' => $previousRole->value,
                            'role' => $saved->getRole()->value,
                            'previous_active' => $previouslyActive,
                            'active' => $saved->isActive(),
                            'password_changed' => $passwordChanged,
                        ],
                        $request->getClientIp(),
                    ),
                );

                $this->addFlash('success', 'Utente aggiornato correttamente.');

                return $this->redirectToRoute('app_user_index');
            }
        }

        if ($form->isSubmitted() && null !== $originalSessionUser) {
            $tokenStorage->getToken()?->setUser($originalSessionUser);
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'page_title' => 'Modifica utente',
            'submit_label' => 'Salva modifiche',
            'edited_user' => $user,
        ], new Response(status: $status));
    }

    #[Route('/{id}/elimina', name: 'app_user_delete', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function delete(
        User $user,
        Request $request,
        UserRepository $userRepository,
        UserAccountGuard $guard,
        AuditedTransaction $transaction,
    ): Response {
        $actor = $this->requireCurrentUser();
        if ($request->isMethod('POST') && !$this->isCsrfTokenValid('delete_user_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }
        $links = $userRepository->countLinkedRecords($user);
        $error = $guard->validateDeletion($actor, $user, $userRepository->countActiveByRole(UserRole::Partner));
        if (null === $error && array_sum($links) > 0) {
            $error = 'L’utente ha dati collegati e non può essere eliminato. Puoi disattivarlo dalla scheda utente.';
        }

        if ($request->isMethod('POST') && null === $error) {
            $id = $user->getId();
            $username = $user->getUsername();
            try {
                $transaction->execute(
                    static fn () => $userRepository->remove($user),
                    static fn (): AuditRecord => new AuditRecord(
                        AuditAction::UserDeleted,
                        $actor->getUserIdentifier(),
                        User::class,
                        $id,
                        ['username' => $username],
                        $request->getClientIp(),
                    ),
                );
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('danger', 'L’utente ha nuovi dati collegati e non può essere eliminato.');

                return $this->redirectToRoute('app_user_index');
            }
            $this->addFlash('success', 'Utente eliminato correttamente.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/delete.html.twig', [
            'target_user' => $user,
            'links' => $links,
            'deletion_error' => $error,
        ], new Response(status: $request->isMethod('POST') ? Response::HTTP_CONFLICT : Response::HTTP_OK));
    }

    private function requireCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
