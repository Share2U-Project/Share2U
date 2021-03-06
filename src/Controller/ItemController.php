<?php

namespace Controller;

use Form\ItemForm;
use GuzzleHttp\Client;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Model\Item;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ItemController extends Controller
{
    public function showAction(Request $request, Application $app, $itemId)
    {
        $itemRepo = self::getEntityManager($app)->getRepository(Item::class);

        $item = $itemRepo->find($itemId);
        if ($item === null){
            throw new NotFoundHttpException('item not found');
        }

        $client = new Client();
        $headers = ['user-key' => ' 2d156ee0f911a8d4d7d0984c5ceff1ca ', 'Accept' => 'application/json'];
        $requestAjax = new \GuzzleHttp\Psr7\Request('GET', 'https://api-2445582011268.apicast.io/games/'.$item->getIgdbId()  , $headers);
        $response = $client->send($requestAjax, ['timeout' => 2]);

        return $app['twig']->render('item.html.twig',
            [
                'item' => $item->toArray(),
                'itemIgdb' => \GuzzleHttp\json_decode($response->getBody()->getContents())[0]
            ]
        );
    }

    public function addAction(Request $request, Application $app)
    {
        // Get serivces
        $entityManager = self::getEntityManager($app);
        $formFactory = self::getFormFactory($app);

        $item = new Item();

        $itemForm = $formFactory->create(ItemForm::class, $item, ['standalone' => true]);

        $itemForm->handleRequest($request);

        if ($itemForm->isSubmitted() && $itemForm->isValid()) {
            $item->setActive(true);
            $now = new \DateTime();
            $item->setOwner(self::getAuthorizedUser($app));
            $item->setInsertedAt($now);
            $item->setUpdatedAt($now);
            $entityManager->persist($item);
            $entityManager->flush();

            // Redidect to the dashboard
            return $app->redirect($app['url_generator']->generate('dashboard'));
        }

        return $app['twig']->render('addItem.html.twig',
            [
                'itemForm' => $itemForm->createView()
            ]
        );
    }

    public function editAction(Request $request, Application $app, $itemId)
    {
        // Get serivces
        $entityManager = self::getEntityManager($app);
        $formFactory = self::getFormFactory($app);

        $item = $entityManager->getRepository(Item::class)->find($itemId);

        $itemForm = $formFactory->create(ItemForm::class, $item, ['standalone' => true]);

        $itemForm->handleRequest($request);

        if ($itemForm->isSubmitted() && $itemForm->isValid()) {
            $now = new \DateTime();
            $item->setUpdatedAt($now);
            $entityManager->flush();

            // Redidect to the dashboard
            return $app->redirect($app['url_generator']->generate('dashboard'));
        }

        return $app['twig']->render('editItem.html.twig',
            [
                'itemForm' => $itemForm->createView(),
                'item' => $item->toArray()
            ]
        );
    }

    public function searchAction(Request $request, Application $app)
    {
        $entityManager = self::getEntityManager($app);
        $itemRepo = $entityManager->getRepository(Item::class);

        // Get the user
        $user = self::getAuthorizedUser($app);
        $searchString = $request->query->get('searchString');
        $page = empty($request->query->get('page')) ? 1 : (int) $request->query->get('page');
        $limit = 5;
        if ( (($page-1) * $limit) < 0 )
        {
            $offset = 0;
        }else
            {
            $offset = ($page-1) * $limit;
        }
        $nbPage = round(count($itemRepo->searchOthersItems($searchString, $user)) / $limit);

        return $app['twig']->render('search.html.twig', [
            'items' => $itemRepo->searchOthersItems($searchString, $user, $offset, $limit),
            'searchString' => $searchString,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'nbPage' => $nbPage
        ]);
    }

    public function deleteAction(Request $request, Application $app, $itemId)
    {
        $entityManager = self::getEntityManager($app);
        $itemRepo = $entityManager->getRepository(Item::class);
        $item = $itemRepo->find($itemId);
        $user = self::getAuthorizedUser($app);
        if ($item !== null){
            $ownerOk = $item->getOwner() === $user;
            if ( $ownerOk ){
                $item->setActive(false);
                $entityManager->flush();
                return $app->json(
                    [
                        'code' => 1,
                        'message' => 'item delete'
                    ]
                );
            }
            return $app->json(
                [
                    'code' => 0,
                    'message' => 'bad owner'
                ]
            );
        }
        return $app->json(
            [
                'code' => 0,
                'message' => 'item not found'
            ]
        );
    }
}