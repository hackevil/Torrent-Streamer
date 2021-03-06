<?php

namespace Homesoft\Bundle\TorrentStreamerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Homesoft\Bundle\TorrentStreamerBundle\Utils\T411RestClient;
use Homesoft\Bundle\TorrentStreamerBundle\Form\Type\UploadTorrentFileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Homesoft\Bundle\TorrentStreamerBundle\Utils\CPasBienExtractor;

class DefaultController extends Controller
{

    /**
     * @return T411RestClient
     */
    private function getT411RestClient()
    {
        /**
         * @var T411RestClient $result
         */
        return $this->get('homesoft_torrent_streamer.t411_rest_client');
    }

    /**
     * @return CPasBienExtractor
     */
    private function getCPasBienExtractor()
    {
        /**
         * @return CPasBienExtractor
         */
        return $this->get('homesoft_torrent_streamer.cpasbien_extractor');
    }

    private function getUploadTorrentFileForm()
    {
        return $this->createForm(new UploadTorrentFileType(), null, array('csrf_protection' => false));
    }

    public function indexAction()
    {
        $torrentUploadForm = $this->getUploadTorrentFileForm();
        return $this->render('HomesoftTorrentStreamerBundle:Default:index.html.twig', array('formUploadTorrent' => $torrentUploadForm->createView()));
    }

    public function searchAction(Request $request)
    {
        $searchTags = $request->request->get('search');
        $t411Result = $this->getT411RestClient()->search($searchTags);
        $cpasbienResult = $this->getCPasBienExtractor()->search($searchTags);
        $response = array(
            'torrentsView'  => $this->renderView(   'HomesoftTorrentStreamerBundle:Default:search-result.html.twig',
                                                    array(
                                                        't411Torrents'      => $t411Result->torrents,
                                                        'cpasbienTorrents'  => $cpasbienResult
                                                    )),
            'result'        => $t411Result,
            'resultCount'   => $t411Result->total + count($cpasbienResult),
        );
        return new JsonResponse($response);
    }

    public function playTorrentFileAction(Request $request)
    {
        $file = $_FILES['torrentFile'];
        $type = $_POST['torrentFileType'];
        $filename = $_POST['torrentFileName'];

        $uploadedFile = new UploadedFile($file['tmp_name'], $filename, $type);

        $moveTo = $this->get('kernel')->getRootDir() . '/cache/' . $this->get('kernel')->getEnvironment();
        $uploadedFile->move($moveTo, $uploadedFile->getClientOriginalName());

        $streamerService = $this->get('homesoft_torrent_streamer.torrent_streamer');
        $response = array(
            'status'    => true,
            'url'       => $streamerService->startStreamer($moveTo . '/' . $uploadedFile->getClientOriginalName())
        );

        return new JsonResponse($response);
    }

    public function playT411TorrentFileAction($torrentId)
    {
        $torrentPath = $this->getT411RestClient()->downloadTorrent($torrentId);
        $streamerService = $this->get('homesoft_torrent_streamer.torrent_streamer');
        $response = array(
            'status'        => true,
            'url'           => $streamerService->startStreamer($torrentPath),
        );
        return new JsonResponse($response);
    }

    public function playCPasBienTorrentFileAction(Request $request)
    {
        $url = $request->request->get('cpasbienUrl');
        if(empty($url))
            throw new \Exception('L\'url n\'est pas trouvable.');
        $torrentPath = $this->getCPasBienExtractor()->downloadTorrentFile($url);
        $streamerService = $this->get('homesoft_torrent_streamer.torrent_streamer');
        $response = array(
            'status'        => true,
            'url'           => $streamerService->startStreamer($torrentPath),
        );
        return new JsonResponse($response);
    }
}
