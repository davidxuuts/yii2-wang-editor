<?php

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;

class EditorPluginUploadAttachmentAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        '//cdn.jsdelivr.net/npm/@wangeditor/plugin-upload-attachment/dist/index' . (YII_ENV_PROD ? '.min' : '') . '.js',
//        'plugin-upload-attachment/index.js',
    ];

    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
