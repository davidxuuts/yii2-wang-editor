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
        'css/editor.css',
    ];
    public $js = [
        'index.js',
    ];
    
    /**
     * @var array
     */
    public $depends = [
        YiiAsset::class,
    ];
}
