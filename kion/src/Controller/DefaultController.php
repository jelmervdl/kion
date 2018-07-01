<?php

namespace App\Controller;

use App\Entity\Page;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
	/**
	 * @Route("/", name="homepage")
	 * @Route("/{name}", name="page_show")
	 */
	public function show($name = 'homepage')
	{
		$page = $this->getDoctrine()->getRepository(Page::class)->findOneBy(['name' => $name]);

		if (!$page)
			throw $this->createNotFoundException('Homepage is missing');

		if (!$page->getPublic())
			$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page!');

		return $this->render('index.html.twig', [
			'page' => $page
		]);
	}

	public function menu()
	{
		$menu_items = $this->getDoctrine()->getRepository(Menu::class)->findAll();

		return $this->render('menu.html.twig', ['items' => $menu_items]);
	}
}