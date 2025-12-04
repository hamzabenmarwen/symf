<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(private LoggerInterface $logger) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $utilisateurRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiredAt(new \DateTimeImmutable('+1 hour'));

                $em->flush();

                // Send reset email
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], 0);
                $notificationService->sendPasswordResetEmail($user, $resetUrl);

                $this->logger->info('Password reset requested', ['email' => $email]);
                $this->addFlash('success', 'If an account exists with this email, you will receive a password reset link.');
            } else {
                // Don't reveal if email exists (security best practice)
                $this->logger->warning('Password reset requested for non-existent email', ['email' => $email]);
                $this->addFlash('success', 'If an account exists with this email, you will receive a password reset link.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route(path: '/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $utilisateurRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('danger', 'Invalid or expired reset link.');
            $this->logger->warning('Invalid password reset attempt', ['token' => substr($token, 0, 10)]);
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $plainPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('password_confirm');

            if (!$plainPassword || !$confirmPassword) {
                $this->addFlash('danger', 'Please fill in all fields.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('danger', 'Passwords do not match.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if (strlen($plainPassword) < 8) {
                $this->addFlash('danger', 'Password must be at least 8 characters.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            // Check password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $plainPassword)) {
                $this->addFlash('danger', 'Password must contain uppercase, lowercase, and numbers.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiredAt(null);

            $em->flush();

            $this->logger->info('Password reset successfully', ['user' => $user->getEmail()]);
            $this->addFlash('success', 'Your password has been reset successfully. You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}
