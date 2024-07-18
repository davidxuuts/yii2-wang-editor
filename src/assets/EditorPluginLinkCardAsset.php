<?php
/*
 * Copyright (c) 2023.
 * @author David Xu <david.xu.uts@163.com>
 * All rights reserved.
 */

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;

class EditorPluginLinkCardAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        'plugin-link-card/index.js',
    ];

    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
