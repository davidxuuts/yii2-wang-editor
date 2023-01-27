<?php

namespace davidxu\weditor\assets;

use davidxu\base\assets\BaseAppAsset;
use yii\web\AssetBundle;

class EditorAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/';
    public $css = [
//        'css/style.css',
        '//cdn.jsdelivr.net/npm/@wangeditor/editor/dist/css/style'. (YII_ENV_PROD ? '.min' : '') . '.css',
        'css/editor.css',
    ];
    public $js = [
        '//cdn.jsdelivr.net/npm/@wangeditor/editor/dist/index' . (YII_ENV_PROD ? '.min' : '') . '.js',
//        'index.js',
    ];
    
    /**
     * @var array
     */
    public $depends = [
        BaseAppAsset::class,
    ];
}
