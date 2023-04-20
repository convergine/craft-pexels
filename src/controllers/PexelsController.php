<?php
namespace convergine\pexels\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;
use convergine\pexels\PexelsPlugin;

class PexelsController extends Controller
{

    public function actionSearchPexels(){
        /* search Pexels action */
        $this->requireAcceptsJson();

        $request = Craft::$app->request;
        $query = $request->getRequiredParam('query');
        $page = max((int)$request->getParam('page', 1), 1);

        $params = [
            'orientation' => $request->getParam('orientation', ''),
            'size' => $request->getParam('size', ''),
            'color' => $request->getParam('color', '')
        ];

        $url = $this->_apiUrlHelper($query, $page, $params['orientation'], $params['size'], $params['color']);

        $settings = PexelsPlugin::$plugin->settings;
        $apiKey = $settings->apiKey;
        $context = stream_context_create([
            'http' => ['header' => "Authorization: {$apiKey}\r\n"]
        ]);

        $pexelsData = @json_decode(@file_get_contents($url, false, $context), true);
        $pexelsData = array_merge($pexelsData, $params, ['query' => $query, 'page' => $page]);

        $success = isset($pexelsData['photos']);

        return $this->asJson(['success' => $success, 'data' => $pexelsData]);
    }


    public function actionDownload() {
		/* download Pexels photo action */

		$this->requireAcceptsJson();

		$url = Craft::$app->request->getRequiredParam('url');
		$target = Craft::$app->request->getRequiredParam('target');

        $folderId = $volumeId = null;
        $uploadLocation = PexelsPlugin::getInstance()->getSettings()->uploadLocation;

        if ($uploadLocation) {
            foreach (explode('/', $uploadLocation) as $location) {
                $locParts = explode(':', $location);
                if ($locParts[0] == 'volume' || $locParts[0] == 'volumeId') {
                    $volumeId = $locParts[1] ?? null;
                }
                if ($locParts[0] == 'folder' || $locParts[0] == 'folderId') {
                    $folderId = $locParts[1] ?? null;
                }
            }
        }

		if ($folderId) {

			/* find the folder, either by id or by uid */
            $folder = Craft::$app->assets->findFolder([(is_numeric($folderId) ? 'id' : 'uid') => $folderId]);
            if (!$folder) {
                throw new \Error('Folder not found: ' . $folderId);
            }
            $volume = $folder->volume;

		} elseif ($volumeId) {

			/* find the volume */
			$fn = 'getVolumeBy' . (is_numeric($volumeId) ? 'Id' : 'Uid');
			$volume = Craft::$app->volumes->$fn($volumeId);
            if (!$volume) {
                throw new \Error('Volume not found: ' . $volumeId);
            }

            /* find the base folder */
            $folder = Craft::$app->assets->findFolder(['volumeId' => $volume->id, 'parentId' => ':empty:']);

            if (!$folder) {
                throw new \Error('Folder not found for volume: ' . $volumeId);
            }

		} else {
			throw new \Error('Unknown upload target: ' . $target);
		}

		/* check user's permission */
        if (!Craft::$app->user->checkPermission('saveAssets:' . $volume->uid)) {
            $this->requireAdmin();
        }

        /* prepare the photo url */
        if (!preg_match('/^https?\:\/\/[^\/]*pexels.com\//', $url)) {
            throw new \Error('This is not a Pexels URL: ' . $url);
        }

        $filename = preg_replace('/^.*\//', '', $url);

        if (!$filename) {
            throw new \Error('Could not get filename from URL: ' . $url);
        }

        $cacheDir = PexelsPlugin::tempDirectory();
        $apiKey = PexelsPlugin::$plugin->settings->apiKey;

		/* use curl to download the photo, change time limit to avoid timeouts */
        set_time_limit(600);
		$fp = fopen($cacheDir . DIRECTORY_SEPARATOR . $filename, 'w+');
		$ch = curl_init($url);

        /* add the authorization header with the API key */
        $headers = ["Authorization: {$apiKey}"];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		/* save the photo */
		$asset = new Asset();
		$asset->tempFilePath = $cacheDir . DIRECTORY_SEPARATOR . $filename;
        $asset->filename = $filename;
		$asset->newFolderId = $folder->id;
		$asset->setVolumeId($volume->id);
		$asset->uploaderId = Craft::$app->user->id;
		$asset->avoidFilenameConflicts = true;
		$asset->setScenario(Asset::SCENARIO_CREATE);

		$success = Craft::$app->elements->saveElement($asset);

		return $this->asJson(['success' => $success, 'assetId' => $asset->id, 'errors' => $asset->getErrors()]);

	}

    private function _apiUrlHelper($query, $page = 1, $orientation = '', $size = '', $color = '') {
        /* prep the api query URL */
        $settings = PexelsPlugin::$plugin->settings;
        $perPage = $settings->picsPerPage;
        $langLocale = $settings->langLocale;
        return 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . $perPage. '&locale=' . $langLocale . '&page=' . $page . '&orientation=' . $orientation. '&size=' . $size. '&color=' . $color;
    }

}
