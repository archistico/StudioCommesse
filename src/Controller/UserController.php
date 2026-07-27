<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\UserAccountGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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
        AuditLogger $auditLogger,
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
            $userRepository->save($user, true);

            $actor = $this->requireCurrentUser();
            $auditLogger->log(
                AuditAction::UserCreated,
                $actor->getUserIdentifier(),
                User::class,
                $user->getId(),
                [
                    'username' => $user->getUsername(),
                    'role' => $user->getRole()->value,
                    'active' => $user->isActive(),
                ],
                $request->getClientIp(),
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
        AuditLogger $auditLogger,
    ): Response {
        $previousRole = $user->getRole();
        $previouslyActive = $user->isActive();
        $previousUsername = $user->getUsername();

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

                $userRepository->save($user, true);

                $auditLogger->log(
                    AuditAction::UserUpdated,
                    $actor->getUserIdentifier(),
                    User::class,
                    $user->getId(),
                    [
                        'previous_username' => $previousUsername,
                        'username' => $user->getUsername(),
                        'previous_role' => $previousRole->value,
                        'role' => $user->getRole()->value,
                        'previous_active' => $previouslyActive,
                        'active' => $user->isActive(),
                        'password_changed' => $passwordChanged,
                    ],
                    $request->getClientIp(),
                );

                $this->addFlash('success', 'Utente aggiornato correttamente.');

                return $this->redirectToRoute('app_user_index');
            }
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'page_title' => 'Modifica utente',
            'submit_label' => 'Salva modifiche',
            'edited_user' => $user,
        ], new Response(status: $status));
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
