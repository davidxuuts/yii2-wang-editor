<?php

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;
use Yii;

class EditorPluginUploadAttachmentAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        'plugin-upload-attachment/index.js',
    ];
    public function init()
    {

    }
    
    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
