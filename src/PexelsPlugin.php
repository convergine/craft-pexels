<?php
namespace convergine\pexels;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\TemplateEvent;
use craft\web\View;
use yii\base\Event;
use convergine\pexels\assets\PexelsAssets;
use convergine\pexels\models\SettingsModel;

class PexelsPlugin extends Plugin
{
	public static $plugin;

	public function init() {

        /* plugin initialization */
		$this->hasCpSection = false;
		$this->hasCpSettings = true;
		parent::init();

        /* register default translations */
        $this->pxDefaultTranslations();
        self::$plugin = $this;

		Event::on(
			View::class,
			View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
			function (TemplateEvent $e) {
                /* check conditions before proceeding */
                if (
                    Craft::$app->request->isCpRequest &&
                    !Craft::$app->user->isGuest &&
                    $e->templateMode == 'cp' &&
                    $this->settings->apiKey
                ){
                    /* render templates & construct JS variables */
                    $jsArr = [
                        'pxHolderText' => Craft::t('convergine-pexels', 'placeholderString'),
                        'pxButton' => Craft::$app->view->renderTemplate('convergine-pexels/_components/pexels-button'),
                        'pxModal' => Craft::$app->view->renderTemplate('convergine-pexels/_components/pexels-modal', [
                            'logo' => Craft::$app->assetManager->getPublishedUrl('@convergine/pexels/assets/dist/pexels.svg', true),
                            'placeholderText' => Craft::t('convergine-pexels', 'placeholderString'),
                            ]),
                        'pxLoading' => Craft::$app->view->renderTemplate('convergine-pexels/_components/pexels-loading'),
                        'pxPerPage' => $this->settings->picsPerPage,
                    ];

                    /* generate JS */
                    $scriptContent = '';
                    foreach ($jsArr as $k => $v) {
                        $scriptContent .= ($scriptContent ? ', ' : 'var ') . $k . ' = ' . json_encode($v);
                    }

                    /* register script */
                    Craft::$app->view->registerScript($scriptContent);
                    /* prepare & register CSS and JS */
                    $prepCss = Craft::$app->assetManager->getPublishedUrl('@convergine/pexels/assets/dist/pexels.css', true);
                    $prepJs = Craft::$app->assetManager->getPublishedUrl('@convergine/pexels/assets/dist/pexels.js', true);
                    Craft::$app->view->registerCssFile($prepCss);
                    Craft::$app->view->registerJsFile($prepJs);
				}
		
			}
		);
	
	}

	protected function createSettingsModel(): SettingsModel {
		/* plugin settings model */
		return new SettingsModel();
	}

	protected function settingsHtml(): string {
		/* render plugin settings page template */
		return Craft::$app->view->renderTemplate('convergine-pexels/_settings', ['settings' => $this->settings]);
	}

    public static function tempDirectory()
    {
        /* create a sub-folder for Pexels temporary files in the craft/storage directory
         (or if that can't be found, the plugin directory) and return the location */
        $storageDir = Craft::$app->path->storagePath;

        if (is_dir($storageDir)) {
            $storageDir .= DIRECTORY_SEPARATOR . 'pexels';
        } else {
            $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
        }

        if (!is_dir($storageDir)) {
            mkdir($storageDir);
        }

        return $storageDir;
    }


    private function pxDefaultTranslations()
    {
        /* register default translations */
        $translations = [
            'Settings',
            'Please Select',
            'pexelsApiNote' => 'Please note, the Pexels API comes with a default rate limit of 200 requests per hour and 20,000 requests per month.<br/> To request a higher limit, you should reach out to Pexels directly (api@pexels.com). If you adhere to Pexels API terms, you may be eligible to receive unlimited requests for free. <br/>',
            'missingApiKey' => 'API key is missing. Add your Pexels API key and click Save button.',
            'missingUploadLocation' => 'Upload location not set. Select location from dropdown and click Save button.',
            'labelApiKey' => 'Pexels API key',
            'instructionsApiKey' => 'Get your API key <a href="{url}" target="_blank">here</a> if you don\'t have one.',
            'labelUploadLocation' => 'Assets Upload Location',
            'instructionsUploadLocation' => 'Select existing Volume or Folder. If none available - create one first.',
            'convergineHelp' => 'Convergine is not affiliated with Pexels.<br/>If you need help, please open issue on github or check out <a href="{url}" target="_blank">docs</a>.',
            'labelPicsPerPage' => 'Photos Per Page',
            'instructionsPicsPerPage' => 'Number of photos to display per page in search results.',
            'labelLangLocale' => 'Search Language',
            'instructionsLangLocale' => 'Select the language in which you will be searching the Pexels\' photos database.',
            'Any Orientation',
            'Minimum Size',
            'Landscape',
            'Portrait',
            'Square',
            'Small (4MP)',
            'Medium (12MP)',
            'Large (24MP)',
            'Predominant Color',
            'Red',
            'Orange',
            'Yellow',
            'Green',
            'Turquoise',
            'Blue',
            'Violet',
            'Pink',
            'Brown',
            'Black',
            'Gray',
            'White',
            'Download Selected',
            'Downloading, please wait.',
            'placeholderString' => 'Use the search box above to search Pexels pictures database.',
            'An error occurred while loading Pexels data',
            '0 photos found for "{query}"',
            'Previous {picPerPage}',
            'Next {picPerPage}',
            'Page {p} of {lp}',
            'Search Pexels',
            '{n,plural,=1{# picture} other{# pictures}} selected',
        ];

        Craft::$app->view->registerTranslations('convergine-pexels', $translations);
    }

}