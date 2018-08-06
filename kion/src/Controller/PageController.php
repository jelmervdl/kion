<?php

namespace App\Controller;

use App\Entity\Page;
use App\Form\PageType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends Controller
{
	/**
	 * @Route("/admin/pages/", name="page_list")
	 */
	public function list()
	{
		$pages = $this->getDoctrine()->getRepository(Page::class)->findAll();
		return $this->render('admin/pages/list.html.twig', ['pages' => $pages]);
	}
	 
	/**
	 * @Route("/{slug}", name="page_show")
	 */
	public function show($slug)
	{
		$page = $this->getDoctrine()->getRepository(Page::class)->findOneBy(['slug' => $slug]);

		if (!$page)
			throw $this->createNotFoundException('Page not found');

		if (!$page->getPublic())
			$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		return $this->render('index.html.twig', [
			'page' => $page
		]);
	}

	/**
	 * @Route("/admin/pages/+new", name="page_new")
	 */
	public function new(Request $request)
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		$page = new Page();

		$form = $this->createForm(PageType::class, $page);

		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			// Mark changes
			$this->getDoctrine()->getManager()->persist($page);

			// Save them to disk
			$this->getDoctrine()->getManager()->flush();
			
			// Return to the page list
			return $this->redirectToRoute('page_list');
		}

		return $this->render('admin/pages/new.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	 * @Route("/admin/pages/{slug}", name="page_edit")
	 */
	public function edit(Request $request, $slug)
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		$page = $this->getDoctrine()->getRepository(Page::class)->findOneBy(['slug' => $slug]);

		if (!$page)
			throw $this->createNotFoundException('Page not found');

		$form = $this->createForm(PageType::class, $page);

		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			// Mark changes
			$this->getDoctrine()->getManager()->persist($page);

			// Save them to disk
			$this->getDoctrine()->getManager()->flush();
			
			// Return to the page list
			return $this->redirectToRoute('page_list');
		}

		return $this->render('admin/pages/edit.html.twig', [
			'page' => $page,
			'form' => $form->createView()
		]);
	}	
}