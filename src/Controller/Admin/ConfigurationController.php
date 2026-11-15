<?php

namespace App\Controller\Admin;

use App\Entity\AppConfig;
use App\Form\AppConfigType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/config')]
#[IsGranted('ROLE_ADMIN')]
class ConfigurationController extends AbstractController
{
    #[Route('', name: 'admin_config')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $config = $em->getRepository(AppConfig::class)->find('default');
        
        if (!$config) {
            $config = new AppConfig();
            $config->setId('default');
            $em->persist($config);
            $em->flush();
        }

        $form = $this->createForm(AppConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La configuration de la plateforme a été mise à jour avec succès.');
            return $this->redirectToRoute('admin_config');
        }

        return $this->render('admin/config/index.html.twig', [
            'config' => $config,
            'form' => $form->createView(),
        ]);
    }
}
