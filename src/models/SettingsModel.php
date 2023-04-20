<?php
namespace convergine\pexels\models;

use craft\base\Model;

class SettingsModel extends Model
{
	public $apiKey;
    public $uploadLocation = '';
    /* as per Pexels docs, between 15 and 80 max, default 15 */
    public $picsPerPage = '15';
    public $langLocale = 'en-US';

    public function defineRules(): array
    {
        return [
            [['apiKey'], 'required'],
            [['uploadLocation'], 'required'],
        ];
    }

}
