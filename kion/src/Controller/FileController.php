<?php

namespace App\Controller;

use App\Entity\File;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Gedmo\Uploadable\UploadableListener;

function files_to_array(array $file_post)
{
	$files = [];

	for ($i = 0; $i < count($file_post['name']); ++$i)
	{
		$files[$i] = [];

		foreach (array_keys($file_post) as $key)
			$files[$i][$key] = $file_post[$key][$i];
	}

	return $files;
}


class FileController extends Controller
{
	/**
	 * @Route("/admin/files/+new", name="file_new")
	 */
	public function new(Request $request, UploadableListener $listener)
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		$manager = $this->getDoctrine()->getManager();

		if (isset($_FILES['files']) && is_array($_FILES['files'])) {
			foreach (files_to_array($_FILES['files']) as $fileInfo) {
				$file = new File();

				// Default to private files
				$file->setPublic(false);

				$listener->addEntityFileInfo($file, $fileInfo);
				$manager->persist($file);
			}

			$manager->flush();
		}

		return $this->redirectToRoute('file_list');
	}

	/**
	 * @Route("admin/files/", name="file_batch_update", methods={"POST"})
	 */
	public function batch_update(Request $request)
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		$manager = $manager = $this->getDoctrine()->getManager();

		$repository = $this->getDoctrine()->getRepository(File::class);

		$files = $repository->findBy(['id' => $request->request->get('file_id', [])]);

		switch ($request->request->get('action')) {
			case 'publish':
				foreach ($files as $file) {
					$file->setPublic(true);
					$manager->persist($file);
				}
				break;

			case 'delete':
				foreach ($files as $file)
					$manager->remove($file);
				break;
		}

		$manager->flush();

		return $this->redirectToRoute('file_list');
	}

	/**
	 * @Route("admin/files/", name="file_list")
	 */
	public function list(Request $request)
	{
		$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this page');

		$repo = $this->getDoctrine()->getRepository(File::class);

		$files = $repo->findAll();

		return $this->render('admin/files/list.html.twig', ['files' => $files]);
	}

	/**
	 * @Route("files/{slug}", name="file_show")
	 */
	public function show($slug)
	{
		$file = $this->getDoctrine()->getRepository(File::class)->findOneBy(['slug' => $slug]);

		if (!$file)
			throw $this->createNotFoundException('File not found');

		if (!$file->getPublic())
			$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to access this file');

		return $this->file($file->getPath(), $file->getName());
	}
}