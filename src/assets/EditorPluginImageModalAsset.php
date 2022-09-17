<?php

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;
use Yii;

class EditorPluginImageModalAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        'plugin-image-modal/wangeditor-plugin-image-modal.umd.js',
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
