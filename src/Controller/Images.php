<?php

declare(strict_types=1);

namespace Bolt\Redactor\Controller;

use Bolt\Configuration\Config;
use Bolt\Controller\Backend\Async\AsyncZoneInterface;
use Bolt\Controller\CsrfTrait;
use Bolt\Redactor\RedactorConfig;
use Bolt\Twig\TextExtension;
use Bolt\Utils\ThumbnailHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tightenco\Collect\Support\Collection;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class Images implements AsyncZoneInterface
{
    use CsrfTrait;

    /** @var Config */
    private $config;

    /** @var Request */
    private $request;

    /** @var ThumbnailHelper */
    private $thumbnailHelper;

    /** @var redactorConfig */
    private $redactorConfig;

    public function __construct(Config $config, CsrfTokenManagerInterface $csrfTokenManager, RequestStack $requestStack, UrlGeneratorInterface $urlGenerator, ThumbnailHelper $thumbnailHelper, RedactorConfig $redactorConfig)
    {
        $this->config = $config;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->thumbnailHelper = $thumbnailHelper;
        $this->redactorConfig = $redactorConfig;
    }

    /**
     * @Route("/redactor_images", name="bolt_redactor_images", methods={"GET"})
     */
    public function getImagesList(Request $request): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_redactor');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', 'files');
        $type = $this->request->query->get('type', '');

        $path = $this->config->getPath($locationName, true);

        $files = $this->getImageFilesIndex($path, $type);

        return new JsonResponse($files);
    }

    private function getImageFilesIndex(string $path, string $type): Collection
    {
        $glob = '*.{' . implode(',', $this->config->getMediaTypes()->toArray()) . '}';

        $files = [];

        foreach ($this->findFiles($path, $glob) as $file) {
            $files[] = [
                'thumb' => $this->thumbnailHelper->path($file->getRelativePathname(), 400, 300, null, null, 'crop'),
                'url' => $thumbnail = '/thumbs/' . $this->redactorConfig->getConfig()['image']['thumbnail'] . '/' . $file->getRelativePathname(),
            ];
        }

        return new Collection($files);
    }

    /**
     * @Route("/redactor_files", name="bolt_redactor_files", methods={"GET"})
     */
    public function getFilesList(Request $request): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_redactor');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', 'files');
        $type = $this->request->query->get('type', '');

        $path = $this->config->getPath($locationName, true);

        $files = $this->getFilesIndex($path, $type);

        return new JsonResponse($files);
    }

    private function getFilesIndex(string $path, string $type): Collection
    {
        $fileTypes = $this->config->getFileTypes()->toArray();
        $glob = '*.{' . implode(',', $fileTypes) . '}';

        $files = [];

        $textExtenion = new TextExtension();

        foreach ($this->findFiles($path, $glob) as $file) {
            $files[] = [
                'title' => $file->getRelativePathname(),
                'url' => '/files/' . $file->getRelativePathname(),
                'size' => $textExtenion->formatBytes($file->getSize(), 1),
            ];
        }

        return new Collection($files);
    }

    private function findFiles(string $path, ?string $glob = null): Finder
    {
        $finder = new Finder();
        $finder->in($path)->depth('< 3')->sortByType()->files();

        if ($glob) {
            $finder->name($glob);
        }

        return $finder;
    }
}
