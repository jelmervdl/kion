<?php

namespace App\Controller;

use App\Entity\Page;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends Controller
{
	/**
	 * @Route("/admin/pages/", name="admin_page_list")
	 */
	public function list_pages()
	{
		$pages = $this->getDoctrine()->getRepository(Page::class)->findAll();
		return $this->render('admin/pages/list.html.twig', ['pages' => $pages]);
	}
	 
	/**
	 * @Route("/admin/pages/{id}", name="admin_page_show")
	 */
	public function edit_page($id)
	{
		$page = $this->getDoctrine()->getRepository(Page::class)->find($id);

		if (!$page)
			throw $this->createNotFoundException('Page not found');

		return $this->render('admin/pages/edit.html.twig', ['page' => $page]);
	}
}