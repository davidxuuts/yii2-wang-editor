<?php
/*
 * Copyright (c) 2023.
 * @author David Xu <david.xu.uts@163.com>
 * All rights reserved.
 */

namespace davidxu\weditor\assets;

use yii\web\AssetBundle;

class EditorPluginUploadAttachmentAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/weditor/dist/plugins/';
    public $css = [
    ];
    public $js = [
        'plugin-upload-attachment/index.js',
    ];

    /**
     * @var array
     */
    public $depends = [
        EditorAsset::class,
    ];
}
