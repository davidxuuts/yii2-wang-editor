<?php

namespace davidxu\weditor\assets;

use davidxu\base\assets\BaseAppAsset;
use yii\web\AssetBundle;
use Yii;

class EditorAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/';
    public $css = [
        'css/style.css',
    ];
    public $js = [
        'index.js',
    ];
    public function init()
    {

    }
    
    /**
     * @var array
     */
    public $depends = [
        BaseAppAsset::class,
    ];
}
