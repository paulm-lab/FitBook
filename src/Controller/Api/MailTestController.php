<?php
declare(strict_types=1);
namespace App\Controller\Api;
use App\Service\FitBookMailer; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\DependencyInjection\Attribute\Autowire; use Symfony\Component\DependencyInjection\Attribute\When; use Symfony\Component\HttpFoundation\JsonResponse; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\Mailer\Exception\TransportExceptionInterface; use Symfony\Component\Routing\Attribute\Route;
#[When(env: 'dev')]
#[Route('/api/dev/mail-test')]
final class MailTestController extends AbstractController
{
 public function __construct(#[Autowire('%env(string:MAIL_TEST_TOKEN)%')]private readonly string $testToken,private readonly FitBookMailer $mailer){}
 #[Route('',name:'api_dev_mail_test',methods:['POST'])]
 public function send(Request $request):JsonResponse
 {
  if($this->testToken==='')return $this->json(['message'=>'Configurez MAIL_TEST_TOKEN dans .env.local pour activer ce test.'],503);if(!hash_equals($this->testToken,(string)$request->headers->get('X-FitBook-Test-Token','')))return $this->json(['message'=>'Jeton de test absent ou invalide.'],403);
  try{$data=$request->toArray();}catch(\Throwable){return $this->json(['message'=>'Le corps doit être un JSON valide.'],400);}$email=trim((string)($data['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return $this->json(['message'=>'Fournissez une adresse e-mail valide.'],400);
  try{$this->mailer->sendTest($email);}catch(TransportExceptionInterface){return $this->json(['message'=>'Envoi impossible. Vérifiez MAILER_DSN et l’accès SMTP Mailtrap.'],502);}return $this->json(['message'=>'E-mail transmis au transport SMTP. Vérifiez sa réception dans la Sandbox Mailtrap.']);
 }
}