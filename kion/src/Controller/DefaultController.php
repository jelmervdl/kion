<?php

namespace App\Controller;

use App\Entity\Page;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
	public function menu()
	{
		$menu_items = $this->getDoctrine()->getRepository(Menu::class)->findAll();

		return $this->render('menu.html.twig', ['items' => $menu_items]);
	}

	/**
	 * @Route("/", name="homepage")
	 */
	public function homepage()
	{
		return $this->forward('App\Controller\PageController::show', [
			'name' => 'homepage'
		]);
	}
}