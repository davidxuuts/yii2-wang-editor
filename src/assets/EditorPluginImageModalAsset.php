<?php
/*
 * Copyright (c) 2023.
 * @author David Xu <david.xu.uts@163.com>
 * All rights reserved.
 */

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;

class EditorPluginImageModalAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
//        '//cdn.jsdelivr.net/npm/wangeditor-plugin-image-modal/dist/wangeditor-plugin-image-modal.umd' . (YII_ENV_PROD ? '.min' : '') . '.js',
        'plugin-image-modal/wangeditor-plugin-image-modal.umd.js',
    ];
    
    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
