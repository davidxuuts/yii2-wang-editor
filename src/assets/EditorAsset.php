<?php
/*
 * Copyright (c) 2023.
 * @author David Xu <david.xu.uts@163.com>
 * All rights reserved.
 */

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;
use yii\web\YiiAsset;

class EditorAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/';
    public $css = [
        'css/style.css',
//        '//cdn.jsdelivr.net/npm/@wangeditor/editor/dist/css/style'. (YII_ENV_PROD ? '.min' : '') . '.css',
        'css/editor.css',
    ];
    public $js = [
//        '//cdn.jsdelivr.net/npm/@wangeditor/editor/dist/index' . (YII_ENV_PROD ? '.min' : '') . '.js',
        'index.js',
    ];
    
    /**
     * @var array
     */
    public $depends = [
        YiiAsset::class,
    ];
}
