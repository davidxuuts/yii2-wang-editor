<?php

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;

class EditorPluginLinkCardAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        '//cdn.jsdelivr.net/npm/@wangeditor/plugin-link-card/dist/index' . (YII_ENV_PROD ? '.min' : '') . '.js',
//        'plugin-link-card/index.js',
    ];

    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
