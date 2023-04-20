<?php
namespace convergine\pexels\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PexelsAssets extends AssetBundle
{
	public function init() {
		/* define asset bundle and files */

		$this->sourcePath = '@convergine/pexels/assets/dist';
		$this->depends = [CpAsset::class];
		$this->js = ['pexels.js'];
		$this->css = ['pexels.css'];
		parent::init();
	}
}