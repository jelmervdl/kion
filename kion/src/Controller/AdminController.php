<?php

namespace App\Controller;

use App\Entity\Page;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Gedmo\Loggable\Entity\LogEntry;

class AdminController extends Controller
{
	/**
	 * @Route("/admin/", name="admin_index")
	 */
	public function index()
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');
		
		$recent_changes = $this->getDoctrine()->getRepository(LogEntry::class)->findBy([], ['loggedAt' => 'desc'], 10);

		return $this->render('admin/index.html.twig', ['recent_changes' => $recent_changes]);
	}
}